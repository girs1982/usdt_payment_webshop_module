<?php
require_once '/var/www/html/config.php';
require_once '/var/www/html/libs/tronphp/autoload.php';

$pool   = 'TUDcMBSeMPyuCbGnJNNXTtAyJ1TGtMKcYt'; // has USDT, no TRX
$master = 'TXFT5NNmTBFWpUA577avVDK2eSR9FpvzBz'; // funds the fee
$usdt   = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
$amount = 3573199; // 3.573199 USDT
$commission = 10000; // 0.010 USDT (1e-6 multiplier)
$feeSun = 35000;   // fee for pool address

$db = getDBConnection();
foreach ([[$pool,'pool'],[$master,'master']] as [$a,$lbl]) {
  $st = $db->prepare('SELECT privkey FROM addresses WHERE address=?');
  $st->execute([$a]);
  $k = $st->fetchColumn();
  if (!$k) { echo "$lbl key missing in DB\n"; exit(1); }
  ${$lbl.'Key'} = $k;
}

$hdr = ['TRON-PRO-API-KEY: '.TRON_API_KEY, 'Content-Type: application/json'];
$credM = TronTool\Credential::fromPrivateKey($masterKey);

// 1) master -> pool TRX fee
$ch = curl_init('https://api.trongrid.io/wallet/createtransaction');
curl_setopt_array($ch, [CURLOPT_POST=>1,
  CURLOPT_POSTFIELDS=>json_encode(['owner_address'=>TronTool\Address::decode($master),'to_address'=>TronTool\Address::decode($pool),'amount'=>$feeSun,'visible'=>true]),
  CURLOPT_HTTPHEADER=>$hdr, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30]);
$feeTx = json_decode(curl_exec($ch), true);
curl_close($ch);
if (!isset($feeTx['txID'])) { echo "fee create failed: ".json_encode($feeTx)."\n"; exit(1); }
$feeTx['signature'] = [$credM->sign($feeTx['txID'])];
$ch = curl_init('https://api.trongrid.io/wallet/broadcasttransaction');
curl_setopt_array($ch, [CURLOPT_POST=>1, CURLOPT_POSTFIELDS=>json_encode($feeTx), CURLOPT_HTTPHEADER=>$hdr, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30]);
$br = json_decode(curl_exec($ch), true);
curl_close($ch);
echo "fee broadcast: ".json_encode($br)."\n";
if (!($br['result'] ?? false)) { echo "fee send failed\n"; exit(1); }

sleep(3); // wait block

// 2) pool -> master USDT
$credP = TronTool\Credential::fromPrivateKey($poolKey);
$data = 'a9059cbb'
  . str_pad(TronTool\Address::decode($master), 64, '0', STR_PAD_LEFT)
  . str_pad(dechex($amount), 64, '0', STR_PAD_LEFT);
$ch = curl_init('https://api.trongrid.io/wallet/triggersmartcontract');
curl_setopt_array($ch, [CURLOPT_POST=>1,
  CURLOPT_POSTFIELDS=>json_encode([
    'contract_address'=>TronTool\Address::decode($usdt),
    'function_selector'=>'transfer(address,uint256)',
    'parameter'=>$data,
    'fee_limit'=>1000000000,
    'call_value'=>0,
    'owner_address'=>TronTool\Address::decode($pool),
    'visible'=>true]),
  CURLOPT_HTTPHEADER=>$hdr, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30]);
$trig = json_decode(curl_exec($ch), true);
curl_close($ch);
if (!isset($trig['transaction'])) { echo "trigger failed: ".json_encode($trig)."\n"; exit(1); }
$tx = $trig['transaction'];
$tx['signature'] = [$credP->sign($tx['txID'])];
$ch = curl_init('https://api.trongrid.io/wallet/broadcasttransaction');
curl_setopt_array($ch, [CURLOPT_POST=>1, CURLOPT_POSTFIELDS=>json_encode($tx), CURLOPT_HTTPHEADER=>$hdr, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30]);
$br = json_decode(curl_exec($ch), true);
curl_close($ch);
echo "usdt broadcast: ".json_encode($br)."\n";
