<?php
// GET /api/status.php?order_id=...  X-API-Key
// -> {order_id, status, address, payment_amount, tx_hash, checkout_url}
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/lib.php';

header('Content-Type: application/json');
$db = getDBConnection();
apiEnsureSchema($db);
if (!apiAuth($db)) { http_response_code(401); echo json_encode(['error' => 'unauthorized']); exit; }

$orderId = $_GET['order_id'] ?? '';
if ($orderId === '') { http_response_code(400); echo json_encode(['error' => 'order_id required']); exit; }
$st = $db->prepare('SELECT * FROM transactions WHERE order_id = ?');
$st->execute([$orderId]);
$tx = $st->fetch(PDO::FETCH_ASSOC);
if (!$tx) { http_response_code(404); echo json_encode(['error' => 'not found']); exit; }

if ($tx['status'] === 'pending') {
    $r = apiPollOrder($db, $tx);
    if (!$r['ok']) { http_response_code(502); echo json_encode(['error' => $r['error']]); exit; }
    if ($r['paid']) { $tx['status'] = 'completed'; $tx['tx_hash'] = $r['tx_hash']; }
}

$out = ['order_id' => $tx['order_id'], 'status' => $tx['status'], 'address' => $tx['address'],
    'payment_amount' => $tx['payment_amount'], 'tx_hash' => $tx['tx_hash'],
    'checkout_url' => BASE_URL . '/checkout/' . $tx['order_id']];
if (!empty($tx['return_url']) && $tx['status'] === 'completed') $out['return_url'] = $tx['return_url'];
echo json_encode($out);
