<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../libs/tronphp/autoload.php';

header('Content-Type: application/json; charset=utf-8');

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$address = trim($in['address'] ?? '');
$privkey = trim($in['privkey'] ?? '');
$amount  = (float)($in['amount'] ?? 0);

if ($address === '' || $privkey === '' || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid input']);
    exit;
}

$hdr = ['TRON-PRO-API-KEY: '.TRON_API_KEY, 'Content-Type: application/json'];
$acc = TronTool\Address::fromBase58($address)->hex();

// 1) balance (TRX sun)
$ch = curl_init('https://api.trongrid.io/wallet/getaccount');
curl_setopt_array($ch, [CURLOPT_POST=>1, CURLOPT_POSTFIELDS=>json_encode(['address'=>$acc, 'visible'=>false]), CURLOPT_HTTPHEADER=>$hdr, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
$balResp = json_decode(curl_exec($ch), true);
curl_close($ch);

$balance = (int)($balResp['balance'] ?? 0);
$need = (int)round($amount * 1e6);
if ($balance < $need) {
    http_response_code(400);
    echo json_encode(['error' => "insufficient balance: $balance sun < $need sun"]);
    exit;
}

// 2) create transaction
$ch = curl_init('https://api.trongrid.io/wallet/createtransaction');
curl_setopt_array($ch, [CURLOPT_POST=>1, CURLOPT_POSTFIELDS=>json_encode(['to_address'=>$acc,'owner_address'=>$acc,'amount'=>$need]), CURLOPT_HTTPHEADER=>$hdr, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
$tx = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($tx['txID'])) {
    http_response_code(500);
    echo json_encode(['error' => 'create failed: '.json_encode($tx)]);
    exit;
}

// 3) sign + broadcast
$cred = TronTool\Credential::fromPrivateKey($privkey);
$tx['signature'] = [$cred->sign($tx['txID'])];

$ch = curl_init('https://api.trongrid.io/wallet/broadcasttransaction');
curl_setopt_array($ch, [CURLOPT_POST=>1, CURLOPT_POSTFIELDS=>json_encode($tx), CURLOPT_HTTPHEADER=>$hdr, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
$br = json_decode(curl_exec($ch), true);
curl_close($ch);

if (($br['result'] ?? false) === true || ($br['code'] ?? '') === 'SUCCESS') {
    echo json_encode(['message' => 'sent', 'tx' => $tx['txID'], 'result' => $br]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'broadcast failed: '.json_encode($br)]);
}
