<?php
/**
 * Import existing wallets.json into MySQL table `addresses`.
 * Requires getDBConnection() from config.php.
 */
require_once __DIR__.'/config.php';
$db = getDBConnection();

$db->exec("CREATE TABLE IF NOT EXISTS addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    address VARCHAR(64) NOT NULL UNIQUE,
    privkey VARCHAR(64) NOT NULL,
    assigned TINYINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$items = json_decode(file_get_contents(__DIR__.'/wallets.json'), true);
$stmt = $db->prepare('INSERT IGNORE INTO addresses (address, privkey, assigned) VALUES (?,?,0)');
$added = 0;
foreach ($items as $item) {
    $stmt->execute([$item['address'], $item['privkey']]);
    if ($stmt->rowCount()) $added++;
}
echo "Inserted $added records from wallets.json\n";
?>