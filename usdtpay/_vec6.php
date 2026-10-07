<?php
require __DIR__ . '/libs/tronphp/autoload.php';
use kornrunner\Keccak;

$pubXY = '79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8';
$hash = Keccak::hash(hex2bin($pubXY), 256);
$hex = '41' . substr($hash, 24);
$bin = hex2bin($hex);
$sum = substr(hash('sha256', hash('sha256', $bin, true), true), 0, 4);
$alph = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
$n = gmp_init(bin2hex($bin) . bin2hex($sum), 16);
$res = '';
while (gmp_cmp($n, 0) > 0) { [$n,$r] = gmp_div_qr($n, 58); $res = $alph[gmp_intval($r)] . $res; }
echo "TRON addr priv=1 = $res\n";
