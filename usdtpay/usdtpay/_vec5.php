<?php
require __DIR__ . '/libs/tronphp/autoload.php';
use kornrunner\Keccak;
use StephenHill\Base58;

// decode expected address to hex, get last 20 bytes
$b = new Base58('123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz');
$dec = $b->decode('TUP1CVdrCCVMSDAoRDkzZ3VosmueeaQXfV');
$hex = bin2hex($dec);
echo "decoded hex = $hex\n";
$last20 = substr($hex, 2, 40);
echo "last20      = $last20\n";

$pubXY = '79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8';
$hash = Keccak::hash(hex2bin($pubXY), 256);
echo "keccak hex  = $hash\n";
echo "hash[24:]   = " . substr($hash, 24) . "\n";
echo "match: " . (substr($hash, 24) === $last20 ? 'YES' : 'NO') . "\n";
