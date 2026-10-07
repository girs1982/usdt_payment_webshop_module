<?php
require __DIR__ . '/config.php';
error_reporting(E_ALL);
ini_set('display_errors',1);
require __DIR__ . '/libs/tronphp/autoload.php';
require __DIR__ . '/libs/tronphp/derive.php';

$mnemonic = defined('TRON_MNEMONIC') ? TRON_MNEMONIC : 'tissue suggest badge roast vintage tomato emerge prefer orbit night front divorce';
$res = tronAddressFromMnemonic($mnemonic, 0);
echo $res['address'];
