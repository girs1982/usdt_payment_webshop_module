<?php
require __DIR__ . '/keccak.php';

$privHex = bin2hex(random_bytes(32));
$addr = tron_address_from_priv($privHex);

print "Priv: $privHex\n";
print "Addr: $addr\n";
