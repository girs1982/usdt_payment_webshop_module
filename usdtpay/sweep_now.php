<?php
require_once '/var/www/html/config.php';
require_once '/var/www/html/libs/tronphp/autoload.php';

$pool   = 'TUDcMBSeMPyuCbGnJNNXTtAyJ1TGtMKcYt';
$master = 'TXFT5NNmTBFWpUA577avVDK2eSR9FpvzBz';
$usdt   = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
$amount = 3573199; // 3.573199 USDT

$db = getDBConnection();
$st = $db->prepare('SELECT privkey FROM addresses WHERE address=?');
$st->execute([$pool]);
$poolKey = $st->fetchColumn();
if (!$poolKey) { echo "pool key missing\n"; exit(1); }

$hdr = ['TRON-PRO-API-KEY: '.TRON_API_KEY, 'Content-Type: application/json'];
$cred = TronTool\Credential::fromPrivateKey($poolKey);

// USDT transfer(pool -> master)
$usdtHex = '41' . strtoupper(substr(TronTool\Address::decode($usdt), 2));
$ownerHex = '41' . strtoupper(substr(TronTool\Address::decode($pool), 2));
$toHex = str_pad(strtoupper(substr(TronTool\Address::decode($master), 2)), 64, '0', STR_PAD_LEFT);
$data = 'a9059cbb' . $toHex . str_pad(dechex($amount), 64, '0', STR_PAD_LEFT);
$ch = curl_init('https://api.trongrid.io/wallet/triggersmartcontract');
curl_setopt_array($ch, [CURLOPT_POST=>1,
  CURLOPT_POSTFIELDS=>json_encode([
    'contract_address'=>$usdtHex,
    'function_selector'=>'transfer(address,uint256)',
    'parameter'=>$data,
    'fee_limit'=>1000000000,
    'call_value'=>0,
    'owner_address'=>$ownerHex,
    'visible'=>false]),
  CURLOPT_HTTPHEADER=>$hdr, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30]);
$trig = json_decode(curl_exec($ch), true);
curl_close($ch);
if (!isset($trig['transaction'])) { echo "trigger failed: ".json_encode($trig)."\n"; exit(1); }
$tx = $trig['transaction'];
$tx['signature'] = [$cred->sign($tx['txID'])];
$ch = curl_init('https://api.trongrid.io/wallet/broadcasttransaction');
curl_setopt_array($ch, [CURLOPT_POST=>1, CURLOPT_POSTFIELDS=>json_encode($tx), CURLOPT_HTTPHEADER=>$hdr, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30]);
$br = json_decode(curl_exec($ch), true);
curl_close($ch);
echo "usdt broadcast: ".json_encode($br)."\n";

// mark order completed
if (!empty($br['txID'])) {
  $upd = $db->prepare("UPDATE transactions SET status='completed', tx_hash=?, completed_at=NOW() WHERE address=? AND status='pending'");
  $upd->execute([$br['txID'], $pool]);
  echo "order updated: ".$upd->rowCount()."\n";
}
