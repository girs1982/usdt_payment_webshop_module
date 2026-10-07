<?php
// POST /api/order.php  X-API-Key: <token>
// {amount, order_id?, customer_name?, customer_email?, shop?, webhook_url?, return_url?, expire_min?}
// -> {order_id, address, payment_amount, checkout_url, expires_at}
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/lib.php';
require_once dirname(__DIR__) . '/get_address_from_pool.php';

header('Content-Type: application/json');
$db = getDBConnection();
apiEnsureSchema($db);
if (!apiAuth($db)) { http_response_code(401); echo json_encode(['error' => 'unauthorized']); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$realAmount = floatval($in['amount'] ?? 0);
if ($realAmount <= 0) { http_response_code(400); echo json_encode(['error' => 'amount must be > 0']); exit; }

$orderId = trim((string)($in['order_id'] ?? '')) !== '' ? trim((string)$in['order_id']) : generateOrderId();
$addr = getAddressFromPool();
if (!$addr['address']) { http_response_code(503); echo json_encode(['error' => 'no free addresses']); exit; }

$paymentAmount = $realAmount + generateRandomDecimal();
$st = $db->query('SELECT * FROM settings LIMIT 1');
$settings = $st->fetch(PDO::FETCH_ASSOC);
$expireMin = max(5, min(1440, intval($in['expire_min'] ?? ($settings['checkout_timeout'] ?? 3600) / 60)));

try {
    $db->prepare('INSERT INTO transactions (order_id, customer_name, customer_email, real_amount, payment_amount, address, network, status, shop, webhook_url, return_url, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, \'pending\', ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))')
        ->execute([$orderId, trim((string)($in['customer_name'] ?? '')), trim((string)($in['customer_email'] ?? '')),
            $realAmount, $paymentAmount, $addr['address'], 'TRC20',
            trim((string)($in['shop'] ?? '')), trim((string)($in['webhook_url'] ?? '')),
            trim((string)($in['return_url'] ?? '')), $expireMin]);
} catch (PDOException $e) {
    // order_id taken by same shop retry — hand address back
    $db->prepare('UPDATE addresses SET assigned=0 WHERE address=?')->execute([$addr['address']]);
    http_response_code(409); echo json_encode(['error' => 'order_id already exists']); exit;
}

echo json_encode([
    'order_id' => $orderId,
    'address' => $addr['address'],
    'payment_amount' => number_format($paymentAmount, 4, '.', ''),
    'network' => 'TRC20',
    'checkout_url' => BASE_URL . '/checkout/' . $orderId,
    'expires_at' => date('c', time() + $expireMin * 60),
]);
