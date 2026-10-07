<?php
require_once '/var/www/html/config.php';
require_once '/var/www/html/libs/tronphp/autoload.php';

$addr = 'TUDcMBSeMPyuCbGnJNNXTtAyJ1TGtMKcYt';
$master = 'TXFT5NNmTBFWpUA577avVDK2eSR9FpvzBz';

$accHex = TronTool\Address::decode($addr);
$masHex = TronTool\Address::decode($master);

$hdr = ['TRON-PRO-API-KEY: '.TRON_API_KEY, 'Content-Type: application/json'];
$ch = curl_init('https://api.trongrid.io/wallet/getaccount');
curl_setopt_array($ch, [CURLOPT_POST=>1, CURLOPT_POSTFIELDS=>json_encode(['address'=>$accHex,'visible'=>false]), CURLOPT_HTTPHEADER=>$hdr, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
$bal = json_decode(curl_exec($ch), true);
curl_close($ch);

$balance = (int)($bal['balance'] ?? 0);
echo "balance: $balance sun\n";
if ($balance <= 0) { echo "nothing to sweep\n"; exit; }

$ch = curl_init('https://api.trongrid.io/wallet/createtransaction');
curl_setopt_array($ch, [CURLOPT_POST=>1, CURLOPT_POSTFIELDS=>json_encode(['to_address'=>$masHex,'owner_address'=>$accHex,'amount'=>$balance]), CURLOPT_HTTPHEADER=>$hdr, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
$tx = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($tx['txID'])) { echo "create failed: ".json_encode($tx)."\n"; exit; }

$cred = TronTool\Credential::fromPrivateKey('eeb49d6c0c43e7ca66ea4cd5bdaab021883ad438432fdf7d4aa99c3397e8a3e4');
$tx['signature'] = [$cred->sign($tx['txID'])];

$ch = curl_init('https://api.trongrid.io/wallet/broadcasttransaction');
curl_setopt_array($ch, [CURLOPT_POST=>1, CURLOPT_POSTFIELDS=>json_encode($tx), CURLOPT_HTTPHEADER=>$hdr, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
$br = json_decode(curl_exec($ch), true);
curl_close($ch);

echo "broadcast: ".json_encode($br)."\n";
