<?php
require __DIR__ . '/keccak.php';

// --- Known test vector: priv = 1 ---
// pubkey for k=1 on secp256k1:
// X = 79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798
// Y = 483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8
$x = '79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798';
$y = '483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8';
$xy = hex2bin($x . $y);

$hash = keccak256($xy);
echo "keccak: " . bin2hex($hash) . "\n";
$payload = chr(0x41) . substr($hash, -20);
$checksum = substr(keccak256($payload), 0, 4);
$addr = base58_encode($payload . $checksum);
echo "addr: $addr\n";
echo "expected: " . trim(shell_exec('echo -n "TUP1CVdrCCVMSDAoRDkzZ3VosmueeaQXfV" | head -c 34')) . "\n";
