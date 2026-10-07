<?php
/**
 * Verify kornrunner Keccak against the standard test vector and
 * derive the TRON address for a known private key.
 */
require __DIR__ . '/libs/tronphp/autoload.php';

use kornrunner\Keccak;

// Known vector: keccak256("") = c5d2460186f7233c927e7db2dcc703c0e500b653ca82273b7bfad8045d85a470
$empty = Keccak::hash('', 256);
echo "keccak('') = $empty\n";
echo "expected    = c5d2460186f7233c927e7db2dcc703c0e500b653ca82273b7bfad8045d85a470\n";
echo "match: " . ($empty === 'c5d2460186f7233c927e7db2dcc703c0e500b653ca82273b7bfad8045d85a470' ? 'YES' : 'NO') . "\n\n";

// priv = 1 → known TRON address TUP1CVdrCCVMSDAoRDkzZ3VosmueeaQXfV
use TronTool\Credential;
$cred = Credential::fromPrivateKey('0000000000000000000000000000000000000000000000000000000000000001');
echo "addr(priv=1) = " . $cred->address()->base58() . "\n";
echo "expected      = TUP1CVdrCCVMSDAoRDkzZ3VosmueeaQXfV\n";
