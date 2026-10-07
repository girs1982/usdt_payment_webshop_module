<?php
/**
 * Очистка всех таблиц для тестов
 */
require_once __DIR__.'/config.php';
$db = getDBConnection();
$db->exec('TRUNCATE TABLE addresses');
$db->exec('TRUNCATE TABLE transactions');
$db->exec('TRUNCATE TABLE settings');
echo "All tables cleared.\n";
?>