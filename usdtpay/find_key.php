<?php
require_once __DIR__ . '/libs/tronphp/autoload.php';
require_once __DIR__ . '/libs/tronphp/derive.php';

$mnemonic = 'tissue suggest badge roast vintage tomato emerge prefer orbit night front divorce';
$target = 'TUDcMBSeMPyuCbGnJNNXTtAyJ1TGtMKcYt';

for ($i = 0; $i < 60; $i++) {
    $r = tronAddressFromMnemonic($mnemonic, $i);
    echo $i . " | " . $r['address'] . " | " . $r['privkey'] . "\n";
    if ($r['address'] === $target) {
        echo "FOUND idx=$i privkey=" . $r['privkey'] . "\n";
    }
}
