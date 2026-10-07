<?php
/**
 * Sweep all pool addresses → master. Master key NOT needed (destination only).
 * Order matters: USDT first (its energy is burned from the address TRX balance),
 * the leftover TRX is swept afterwards.
 *
 * Balances are prefetched with curl_multi: one serial TronGrid call takes ~1s, which
 * blows past any browser/proxy timeout once the pool holds a few hundred addresses.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../libs/tronphp/autoload.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdminLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'not authenticated']);
    exit;
}

const USDT_CONTRACT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'; // mainnet USDT (TRC20)
const USDT_DECIMALS = 6;
const MIN_TRX_FOR_ENERGY = 20000000; // ~20 TRX: a bare USDT transfer burns 13-15 TRX of energy

/** base58 Tron address → 0x41-prefixed 21-byte hex */
function b58_to_hex41(string $b58): string
{
    $alpha = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $n = gmp_init(0);
    foreach (str_split($b58) as $ch) {
        $n = gmp_add(gmp_mul($n, 58), strpos($alpha, $ch));
    }
    $hex = str_pad(gmp_strval($n, 16), 50, '0', STR_PAD_LEFT);
    return substr($hex, 0, 42); // drop the 4-byte checksum
}

function tron_headers(): array
{
    $h = ['Content-Type: application/json'];
    if (defined('TRON_API_KEY') && TRON_API_KEY !== '') {
        $h[] = 'TRON-PRO-API-KEY: ' . TRON_API_KEY;
    }
    return $h;
}

/** POST to TronGrid; returns the decoded body, or ['_error' => ...] */
function tron_api(string $path, array $body): array
{
    $ch = curl_init('https://api.trongrid.io' . $path);
    curl_setopt_array($ch, [
        CURLOPT_POST           => 1,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => tron_headers(),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $out = json_decode((string)$raw, true);
    if (!is_array($out)) {
        return ['_error' => "http $code: " . substr((string)$raw, 0, 200)];
    }
    return $out;
}

/**
 * Run many TronGrid POSTs concurrently. $reqs is key => ['path'=>..., 'body'=>...];
 * returns key => decoded body (a missing key means the request never answered).
 */
function tron_multi(array $reqs, int $conc = 10): array
{
    $pending = $reqs; $done = []; $mh = curl_multi_init(); $live = [];
    $headers = tron_headers();

    while ($pending || $live) {
        while ($pending && count($live) < $conc) {
            $k = array_key_first($pending); $r = $pending[$k]; unset($pending[$k]);
            $ch = curl_init('https://api.trongrid.io' . $r['path']);
            curl_setopt_array($ch, [
                CURLOPT_POST           => 1,
                CURLOPT_POSTFIELDS     => json_encode($r['body']),
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
            ]);
            curl_multi_add_handle($mh, $ch);
            $live[(int)$ch] = [$k, $ch];
        }
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 1.0);
        while ($info = curl_multi_info_read($mh)) {
            $ch  = $info['handle'];
            $key = $live[(int)$ch][0];
            $done[$key] = json_decode((string)curl_multi_getcontent($ch), true) ?: [];
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            unset($live[(int)$ch]);
        }
    }
    curl_multi_close($mh);
    return $done;
}

/** Sign and broadcast a transaction built by TronGrid. */
function sign_broadcast(array $tx, $cred): array
{
    $tx['signature'] = [$cred->sign($tx['txID'])];
    return tron_api('/wallet/broadcasttransaction', $tx);
}

$db      = getDBConnection();
$payload = json_decode(file_get_contents('php://input'), true) ?: [];

$master = trim($payload['master'] ?? '');
if ($master === '') {
    $master = (string)$db->query('SELECT usdt_address FROM settings LIMIT 1')->fetchColumn();
}
if (!preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $master)) {
    http_response_code(400);
    echo json_encode(['error' => 'master address missing']);
    exit;
}

$usdtHex   = b58_to_hex41(USDT_CONTRACT);
$masterHex = b58_to_hex41($master);
$masterArg = str_pad(substr($masterHex, 2), 64, '0', STR_PAD_LEFT);

$rows = $db->prepare('SELECT id, address, privkey FROM addresses WHERE address <> ?');
$rows->execute([$master]);
$rows = $rows->fetchAll(PDO::FETCH_ASSOC);

// ---- prefetch TRX + USDT for every pool address, concurrently ----
$hex = []; $balReq = []; $usdtReq = [];
foreach ($rows as $r) {
    $h = b58_to_hex41($r['address']);
    $hex[$r['address']]     = $h;
    $balReq[$r['address']]  = ['path' => '/wallet/getaccount', 'body' => ['address' => $h, 'visible' => false]];
    $usdtReq[$r['address']] = ['path' => '/wallet/triggerconstantcontract', 'body' => [
        'owner_address'     => $h,
        'contract_address'  => $usdtHex,
        'function_selector' => 'balanceOf(address)',
        'parameter'         => '70a08231' . str_pad(substr($h, 2), 64, '0', STR_PAD_LEFT),
        'visible'           => false,
    ]];
}
$bal  = tron_multi($balReq);
$usdt = tron_multi($usdtReq);

$detail = [];
$sent   = 0;

foreach ($rows as $r) {
    $addr     = $r['address'];
    $ownerHex = $hex[$addr];
    $trxSun   = (int)($bal[$addr]['balance'] ?? 0);
    $usdtRaw  = isset($usdt[$addr]['constant_result'][0]) ? hexdec($usdt[$addr]['constant_result'][0]) : 0;

    if ($trxSun <= 0 && $usdtRaw <= 0) {
        $detail[] = "$addr: empty";
        continue;
    }
    if (!preg_match('/^[0-9a-f]{64}$/i', (string)$r['privkey'])) {
        $detail[] = sprintf('%s: no valid key, skipped (holds %.6f USDT)', $addr, $usdtRaw / 10 ** USDT_DECIMALS);
        continue;
    }

    try {
        $cred = TronTool\Credential::fromPrivateKey($r['privkey']);

        // ---- 1) USDT first: it burns energy paid from this address' TRX ----
        if ($usdtRaw > 0 && $trxSun < MIN_TRX_FOR_ENERGY) {
            $detail[] = sprintf(
                '%s: USDT %.6f NOT swept — needs ~%d TRX for energy, has %.6f',
                $addr, $usdtRaw / 10 ** USDT_DECIMALS, MIN_TRX_FOR_ENERGY / 1e6, $trxSun / 1e6
            );
        } elseif ($usdtRaw > 0) {
            $trig = tron_api('/wallet/triggersmartcontract', [
                'owner_address'     => $ownerHex,
                'contract_address'  => $usdtHex,
                'function_selector' => 'transfer(address,uint256)',
                'parameter'         => 'a9059cbb' . $masterArg . str_pad(dechex($usdtRaw), 64, '0', STR_PAD_LEFT),
                'fee_limit'         => 1000000000,
                'call_value'        => 0,
                'visible'           => false,
            ]);
            if (!isset($trig['transaction'])) {
                $detail[] = "$addr: USDT trigger failed " . json_encode($trig);
            } else {
                $br = sign_broadcast($trig['transaction'], $cred);
                if (($br['result'] ?? false) === true) {
                    $sent++;
                    $txid = $trig['transaction']['txID'];
                    $detail[] = sprintf('%s: USDT %.6f -> master (%s)', $addr, $usdtRaw / 10 ** USDT_DECIMALS, $txid);
                    $db->prepare('UPDATE addresses SET swept = NOW(), sweep_tx = ? WHERE id = ?')->execute([$txid, $r['id']]);
                    $db->prepare("UPDATE transactions SET status = 'completed', tx_hash = ?, completed_at = NOW()
                                  WHERE address = ? AND status = 'pending'")->execute([$txid, $addr]);
                    sleep(3); // let the energy burn settle before touching TRX
                } else {
                    $detail[] = "$addr: USDT broadcast failed " . json_encode($br);
                }
            }
        }

        // ---- 2) leftover TRX (re-read: the USDT transfer just burned 13-15 TRX) ----
        $acc    = tron_api('/wallet/getaccount', ['address' => $ownerHex, 'visible' => false]);
        $trxSun = (int)($acc['balance'] ?? 0);

        if ($trxSun > 0) {
            $tx = tron_api('/wallet/createtransaction', [
                'owner_address' => $ownerHex,
                'to_address'    => $masterHex,
                'amount'        => $trxSun,
                'visible'       => false,
            ]);
            if (!isset($tx['txID'])) {
                $detail[] = "$addr: TRX create failed " . json_encode($tx);
            } else {
                $br = sign_broadcast($tx, $cred);
                if (($br['result'] ?? false) === true) {
                    $sent++;
                    $detail[] = sprintf('%s: TRX %.6f -> master (%s)', $addr, $trxSun / 1e6, $tx['txID']);
                } else {
                    $detail[] = "$addr: TRX broadcast failed " . json_encode($br);
                }
            }
        }
    } catch (Throwable $e) {
        $detail[] = "$addr: " . $e->getMessage();
    }
}

echo json_encode([
    'message' => "swept $sent transfer(s) to $master",
    'detail'  => implode("\n", $detail),
]);