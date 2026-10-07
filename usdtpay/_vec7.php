<?php
/**
 * Test: derive TRC20 addresses from the project mnemonic.
 * Verifies index 0 matches the address stored in settings.usdt_address.
 */
require __DIR__ . '/config.php';
require __DIR__ . '/libs/tronphp/autoload.php';
require __DIR__ . '/libs/tronphp/derive.php';

$mnemonic = defined('TRON_MNEMONIC') ? TRON_MNEMONIC : null;

// prefer settings table if it has a mnemonic column
$db = getDBConnection();
try {
    $r = $db->query("SELECT mnemonic, usdt_address FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!empty($r['mnemonic'])) $mnemonic = $r['mnemonic'];
} catch (Exception $e) { /* no column -> constant */ }

if (!$mnemonic) {
    header('Content-Type: text/plain');
    echo "no mnemonic\n";
    exit;
}

$out = ['mnemonic' => $mnemonic, 'settings_usdt_address' => null];
try {
    $out['settings_usdt_address'] = $db->query("SELECT usdt_address FROM settings LIMIT 1")->fetchColumn();
} catch (Exception $e) {}

for ($i = 0; $i < 5; $i++) {
    $out['addresses'][] = tronAddressFromMnemonic($mnemonic, $i);
}
$out['index0_matches_settings'] = ($out['settings_usdt_address'] === $out['addresses'][0]['address']);

header('Content-Type: application/json');
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
