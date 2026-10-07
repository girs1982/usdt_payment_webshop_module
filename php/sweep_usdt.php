<?php
require_once '/var/www/html/config.php';
require_once '/var/www/html/libs/tronphp/autoload.php';

$addr   = 'TUDcMBSeMPyuCbGnJNNXTtAyJ1TGtMKcYt';
$master = 'TXFT5NNmTBFWpUA577avVDK2eSR9FpvzBz';
$usdt   = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
$amount = 3573199; // 3.573199 USDT (6 decimals)

$db = getDBConnection();
$stmt = $db->prepare('SELECT privkey FROM addresses WHERE address = ?');
$stmt->execute([$addr]);
$privkey = $stmt->fetchColumn();
if (!$privkey) { echo "privkey not found\n"; exit(1); }

$ownerHex = TronTool\Address::decode($addr);
$toHex    = TronTool\Address::decode($master);
$usdtHex  = TronTool\Address::decode($usdt);

$data = 'a9059cbb'
  . str_pad($toHex, 64, '0', STR_PAD_LEFT)
  . str_pad(dechex($amount), 64, '0', STR_PAD_LEFT);

$hdr = ['TRON-PRO-API-KEY: '.TRON_API_KEY, 'Content-Type: application/json'];
$payload = [
  'contract_address' => $usdtHex,
  'function_selector' => 'transfer(address,uint256)',
  'parameter' => $data,
  'fee_limit' => 1000000000,
  'call_value' => 0,
  'owner_address' => $ownerHex,
  'visible' => true,
];

$ch = curl_init('https://api.trongrid.io/wallet/triggersmartcontract');
curl_setopt_array($ch, [CURLOPT_POST=>1, CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_HTTPHEADER=>$hdr, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30]);
$trig = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($trig['transaction'])) { echo "trigger failed: ".json_encode($trig)."\n"; exit(1); }

$tx = $trig['transaction'];
$cred = TronTool\Credential::fromPrivateKey($privkey);
$tx['signature'] = [$cred->sign($tx['txID'])];

$ch = curl_init('https://api.trongrid.io/wallet/broadcasttransaction');
curl_setopt_array($ch, [CURLOPT_POST=>1, CURLOPT_POSTFIELDS=>json_encode($tx), CURLOPT_HTTPHEADER=>$hdr, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30]);
$br = json_decode(curl_exec($ch), true);
curl_close($ch);

echo "broadcast: ".json_encode($br)."\n";
