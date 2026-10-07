<?php
/**
 * Restore default settings row after truncate.
 */
require_once __DIR__.'/config.php';
$db = getDBConnection();
$db->exec("INSERT INTO settings (id, usdt_address, tron_api_key, network, checkout_timeout, check_interval) VALUES (1, 'TC2apTWVEZ3HMRbcgEDCr9vXaUtmKhCPWo', 'YOUR_TRON_API_KEY', 'TRC20', 3600, 60)");
echo "Default settings restored.\n";
?>