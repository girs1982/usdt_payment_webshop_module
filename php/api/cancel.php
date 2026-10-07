<?php
// GET /api/cancel.php?order_id=...  X-API-Key  -> frees address back to pool
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/lib.php';

header('Content-Type: application/json');
$db = getDBConnection();
apiEnsureSchema($db);
if (!apiAuth($db)) { http_response_code(401); echo json_encode(['error' => 'unauthorized']); exit; }

$orderId = $_GET['order_id'] ?? $_POST['order_id'] ?? '';
if ($orderId === '') { http_response_code(400); echo json_encode(['error' => 'order_id required']); exit; }
$st = $db->prepare("SELECT * FROM transactions WHERE order_id = ? AND status = 'pending'");
$st->execute([$orderId]);
$tx = $st->fetch(PDO::FETCH_ASSOC);
if (!$tx) { http_response_code(404); echo json_encode(['error' => 'not found or already processed']); exit; }

$db->prepare("UPDATE transactions SET status='cancelled', failure_reason='api cancel' WHERE order_id=?")->execute([$orderId]);
$db->prepare('UPDATE addresses SET assigned=0 WHERE address=?')->execute([$tx['address']]);
apiSendWebhook($db, array_merge($tx, ['status' => 'cancelled']));
echo json_encode(['status' => 'cancelled', 'order_id' => $orderId]);
