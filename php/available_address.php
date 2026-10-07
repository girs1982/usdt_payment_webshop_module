<?php
/**
 * Return one free TRC20 address.
 * Called by the UI when "Create Transaction" is pressed.
 */
require_once __DIR__.'/config.php'; // for getDBConnection()   
$db = getDBConnection();

// если табл ничего не создала, её создаём
$db->exec("CREATE TABLE IF NOT EXISTS addresses(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  address TEXT NOT NULL UNIQUE,
  privkey TEXT NOT NULL,
  assigned TINYINT NOT NULL DEFAULT 0
)");

$stmt = $db->prepare('SELECT id,address,privkey FROM addresses WHERE assigned=0 LIMIT 1');
$stmt->execute();
$addr = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$addr) {
    http_response_code(404);
    echo json_encode(['error'=>'no free addresses']);
    exit;
}
// помечаем как использованное
$upd = $db->prepare('UPDATE addresses SET assigned=1 WHERE id=?');
$upd->execute([$addr['id']]);

header('Content-Type: application/json');
echo json_encode($addr);
?>