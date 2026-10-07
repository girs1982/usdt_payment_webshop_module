<?php
$mnemonic = trim(file_get_contents(__DIR__ . '/_mnemonic.txt'));
$argv = ['generate_addresses.php', $mnemonic, '1', '0'];
require __DIR__ . '/generate_addresses.php';
