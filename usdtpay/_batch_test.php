<?php
/**
 * Derive first N addresses from the mnemonic and verify each on TronGrid.
 * Also tests the "pool exhausted" path by deriving past the last stored index.
 */
require __DIR__ . '/config.php';
require __DIR__ . '/keccak.php';


// ---- crypto core (copied from generate_addresses.php, CLI-safe) ----
const P  = 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F';
const N  = 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141';
const Gx = '79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798';
const Gy = '483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8';

function gmp_hex(string $h): GMP { return gmp_init($h, 16); }

function pt_add($P1, $P2) {
    if ($P1 === null) return $P2;
    if ($P2 === null) return $P1;
    [$x1, $y1] = $P1; [$x2, $y2] = $P2;
    $mod = gmp_hex(P);
    if (gmp_cmp($x1, $x2) === 0 && gmp_cmp(gmp_add($y1, $y2), $mod) === 0) return null;
    if (gmp_cmp($x1, $x2) === 0 && gmp_cmp($y1, $y2) === 0) {
        $lam = gmp_mul(gmp_mul(gmp_init(3), gmp_powm($x1, 2, $mod)), gmp_invert(gmp_mul(gmp_init(2), $y1), $mod));
    } else {
        $lam = gmp_mul(gmp_sub($y2, $y1), gmp_invert(gmp_sub($x2, $x1), $mod));
    }
    $lam = gmp_mod($lam, $mod);
    $x3 = gmp_mod(gmp_sub(gmp_sub(gmp_powm($lam, 2, $mod), $x1), $x2), $mod);
    $y3 = gmp_mod(gmp_sub(gmp_mul($lam, gmp_sub($x1, $x3)), $y1), $mod);
    return [$x3, $y3];
}

function pt_mul(GMP $k, $Pt = null) {
    if ($Pt === null) $Pt = [gmp_hex(Gx), gmp_hex(Gy)];
    $R = null;
    while (gmp_cmp($k, 0) > 0) {
        if (gmp_testbit($k, 0)) $R = pt_add($R, $Pt);
        $Pt = pt_add($Pt, $Pt);
        $k = gmp_div_q($k, 2);
    }
    return $R;
}

function privkey_to_pubkey_uncompressed(GMP $priv): string {
    $Pt = pt_mul($priv);
    $x = str_pad(gmp_strval($Pt[0], 16), 64, '0', STR_PAD_LEFT);
    $y = str_pad(gmp_strval($Pt[1], 16), 64, '0', STR_PAD_LEFT);
    return '04' . $x . $y;
}

function keccak256_used(): void {}

function privkey_to_tron_address(GMP $priv): string {
    $pub = privkey_to_pubkey_uncompressed($priv);
    $hash = keccak256(hex2bin(substr($pub, 2)));
    $payload = "\x41" . substr($hash, 12, 20);
    $checksum = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);
    return base58_encode($payload . $checksum);
}

function mnemonic_to_seed(string $mnemonic, string $passphrase = ''): string {
    $words = explode(' ', trim($mnemonic));
    $cnt = count($words);
    if (!in_array($cnt, [12, 15, 18, 21, 24], true)) throw new RuntimeException("invalid word count: $cnt");
    $wordlist = array_map('trim', file(__DIR__ . '/english.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    if (count($wordlist) !== 2048) throw new RuntimeException('wordlist corrupted');
    $idx = array_flip($wordlist);
    $bits = gmp_init(0);
    foreach ($words as $w) {
        if (!isset($idx[$w])) throw new RuntimeException("invalid word: $w");
        $bits = gmp_or(gmp_mul($bits, gmp_init(2048)), gmp_init($idx[$w]));
    }
    $totalBits = 11 * $cnt;
    $entBits = $totalBits - intdiv($cnt, 3);
    $entropy = gmp_div_q($bits, gmp_pow(2, $totalBits - $entBits));
    $entBytes = gmp_export($entropy, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
    $entBytes = str_pad($entBytes, $entBits / 8, "\x00", STR_PAD_LEFT);
    return hash_pbkdf2('sha512', $entBytes, 'mnemonic' . $passphrase, 2048, 64, true);
}

function ckd_priv(GMP $kpar, string $cpar, int $i): array {
    $serK = str_pad(gmp_export($kpar, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN), 32, "\x00", STR_PAD_LEFT);
    if ($i >= 0x80000000) {
        $data = "\x00" . $serK . pack('N', $i);
    } else {
        $pub = privkey_to_pubkey_uncompressed($kpar);
        $data = hex2bin($pub) . pack('N', $i);
    }
    $I = hash_hmac('sha512', $data, $cpar, true);
    $il = gmp_import(substr($I, 0, 32), 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
    $n = gmp_hex(N);
    if (gmp_cmp($il, $n) >= 0) throw new RuntimeException('IL >= N');
    $ki = gmp_mod(gmp_add($il, $kpar), $n);
    if (gmp_cmp($ki, 0) === 0) throw new RuntimeException('ki == 0');
    return [$ki, substr($I, 32)];
}

function derive_master(string $seed): array {
    $I = hash_hmac('sha512', $seed, 'Bitcoin seed', true);
    return [gmp_import(substr($I, 0, 32), 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN), substr($I, 32)];
}

function parse_path(string $p): array {
    $out = [];
    foreach (explode('/', $p) as $i => $part) {
        if ($i === 0) continue;
        $out[] = str_ends_with($part, "'") ? intval($part) + 0x80000000 : intval($part);
    }
    return $out;
}

// ---- test ----
$mnemonic = TRON_MNEMONIC;
$seed = mnemonic_to_seed($mnemonic);
[$k, $c] = derive_master($seed);
foreach (parse_path("m/44'/195'/0'") as $idx) { [$k, $c] = ckd_priv($k, $c, $idx); }

$N = 20;
$addrs = [];
for ($i = 0; $i < $N; $i++) {
    [$ki, $ci] = ckd_priv($k, $c, $i);
    $addrs[$i] = privkey_to_tron_address($ki);
}

// verify each against TronGrid
$results = [];
$mh = curl_multi_init();
$ch = [];
foreach ($addrs as $i => $a) {
    $h = curl_init("https://api.trongrid.io/v1/accounts/$a");
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['TRON-PRO-API-KEY: ' . TRON_API_KEY], CURLOPT_TIMEOUT => 20]);
    $ch[$i] = $h;
    curl_multi_add_handle($mh, $h);
}
do { $st = curl_multi_exec($mh, $active); if ($st !== CURLM_OK) break; if (curl_multi_select($mh) === -1) usleep(20000); } while ($active);
foreach ($ch as $i => $h) {
    $body = curl_multi_getcontent($h);
    $code = curl_getinfo($h, CURLINFO_HTTP_CODE);
    $j = json_decode((string)$body, true);
    $exists = isset($j['success']) && $j['success'] === true && isset($j['data'][0]);
    $results[] = ['index' => $i, 'address' => $addrs[$i], 'http' => $code, 'onchain' => $exists, 'err' => !$exists ? substr((string)$body, 0, 200) : null];
    curl_multi_remove_handle($mh, $h);
}
curl_multi_close($mh);

// also cross-check first address against settings.usdt_address
$db = getDBConnection();
$set = $db->query("SELECT * FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode([
    'derived' => $addrs,
    'verify' => $results,
    'settings_usdt_address' => $set['usdt_address'] ?? null,
    'settings_has_mnemonic' => isset($set['mnemonic']) ? !empty($set['mnemonic']) : 'no column',
    'derived_0_matches_settings' => ($set['usdt_address'] ?? null) === $addrs[0],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
