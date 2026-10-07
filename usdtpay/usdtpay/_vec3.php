<?php
require __DIR__ . '/libs/tronphp/autoload.php';
use TronTool\Credential;
use kornrunner\Keccak;

$cred = Credential::fromPrivateKey('0000000000000000000000000000000000000000000000000000000000000001');
$pub = $cred->publicKey();
echo "publicKey hex len=" . strlen($pub) . "\n$pub\n";

// pubkey for k=1 (known)
$known = '0479BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8';
$knownNoPrefix = substr($known, 2);

foreach ([substr($pub,2), $pub, $knownNoPrefix] as $i => $cand) {
    $hash = Keccak::hash(hex2bin($cand), 256);
    $hex = '41' . substr($hash, 24);
    // base58check
    $bin = hex2bin($hex);
    $sum = substr(hash('sha256', hash('sha256', $bin, true), true), 0, 4);
    $alph = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $n = gmp_init($hex . bin2hex($sum), 16);
    $res = '';
    while (gmp_cmp($n, 0) > 0) { [$n,$r] = gmp_div_qr($n, 58); $res = $alph[gmp_intval($r)] . $res; }
    echo "cand $i addr = $res\n";
}
echo "expected = TUP1CVdrCCVMSDAoRDkzZ3VosmueeaQXfV\n";
