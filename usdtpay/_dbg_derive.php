<?php
require __DIR__ . '/config.php';
error_reporting(E_ALL);
ini_set('display_errors',1);
require __DIR__ . '/libs/tronphp/autoload.php';
require __DIR__ . '/libs/tronphp/derive.php';

$mnemonic = TRON_MNEMONIC;
echo "mnemonic ok, len=" . strlen($mnemonic) . "\n";
$wordlist = file(__DIR__ . '/english.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
echo "wordlist=" . count($wordlist) . "\n";
try {
  $seed = mnemonic_to_seed($mnemonic);
  echo "seed len=" . strlen($seed) . "\n";
} catch (Throwable $e) {
  echo "ERR mnemonic: " . $e->getMessage() . "\n";
}
try {
  $res = tronAddressFromMnemonic($mnemonic, 1);
  echo "i=1 addr=" . $res['address'] . " priv=" . $res['privkey'] . "\n";
} catch (Throwable $e) {
  echo "ERR derive: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
