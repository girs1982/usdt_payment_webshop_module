<?php
require __DIR__ . '/config.php';
$db = getDBConnection();
$out = [];

// schema
try {
    $r = $db->query("SHOW CREATE TABLE addresses");
    $out['addresses_schema'] = $r->fetch(PDO::FETCH_ASSOC)['Create Table'] ?? null;
} catch (Exception $e) { $out['addresses_schema_err'] = $e->getMessage(); }

foreach (['settings','transactions'] as $t) {
    try {
        $r = $db->query("SHOW CREATE TABLE `$t`");
        $out[$t.'_schema'] = $r->fetch(PDO::FETCH_ASSOC)['Create Table'] ?? null;
    } catch (Exception $e) { $out[$t.'_err'] = $e->getMessage(); }
}

try {
    $out['addresses_count'] = (int)$db->query("SELECT COUNT(*) FROM addresses")->fetchColumn();
    $out['addresses_rows'] = $db->query("SELECT id,address,addr_index,assigned,swept FROM addresses ORDER BY id LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $out['addresses_data_err'] = $e->getMessage(); }

try {
    $out['tx_count'] = (int)$db->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
    $out['tx_pending'] = $db->query("SELECT id,order_id,address,status,amount FROM transactions WHERE status='pending' LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $out['tx_err'] = $e->getMessage(); }

try {
    $out['settings'] = $db->query("SELECT * FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { $out['settings_err'] = $e->getMessage(); }

header('Content-Type: application/json');
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
