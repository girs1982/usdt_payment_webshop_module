<?php
require_once '../config.php';

// The address is unique per order, so the transfer that lands on it is unambiguous and
// only the 6-decimal quantisation of USDT needs slack — not the 1e-3 a shared-address
// pool would need.
define('AMOUNT_TOLERANCE', 0.0001);

header('Content-Type: application/json');

if (!isset($_GET['order_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Order ID is required']);
    exit();
}

$order_id = $_GET['order_id'];
$db = getDBConnection();

// Get transaction details
$stmt = $db->prepare("SELECT * FROM transactions WHERE order_id = ?");
$stmt->execute([$order_id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    http_response_code(404);
    echo json_encode(['error' => 'Transaction not found']);
    exit();
}

$trc20Id = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
$url = "https://apilist.tronscanapi.com/api/transfer/trc20?address=" . $transaction['address'] . "&trc20Id={$trc20Id}&limit=50&direction=2";

// TronScan rejects TronGrid keys with {"Error":"ApiKey not exists"}, which used to make
// every check answer 'Invalid API response'. Use the key stored in settings; send no
// header at all when there is none (the public endpoint works unauthenticated).
$apiKey = '';
try {
    $apiKey = (string)$db->query('SELECT tron_api_key FROM settings LIMIT 1')->fetchColumn();
} catch (Throwable $e) {
    $apiKey = '';
}
$headers = $apiKey !== '' ? ["TRON-PRO-API-KEY: $apiKey"] : [];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    http_response_code(502);
    echo json_encode(['error' => 'Blockchain API unavailable']);
    curl_close($ch);
    exit();
}
curl_close($ch);

$data = json_decode($response, true);
if (!is_array($data) || !isset($data['data'])) {
    http_response_code(502);
    echo json_encode(['error' => 'Invalid API response']);
    exit();
}
// Check if the transaction is in the response
$transaction_found = false;
foreach ($data['data'] as $item) {
    // A reverted/among-unconfirmed TRC20 transfer shows up in this same list, so only a
    // confirmed, successful transfer may mark the order as paid.
    if (($item['contract_ret'] ?? '') !== 'SUCCESS'
        || (int)($item['revert'] ?? 0) !== 0
        || (int)($item['confirmed'] ?? 0) !== 1
        || ($item['to'] ?? '') !== $transaction['address']) {
        continue;
    }

    // The address goes back into the pool when an order is deleted, so a transfer that
    // predates this order must never pay it. block_timestamp is in milliseconds.
    if ((int)($item['block_timestamp'] ?? 0) < strtotime((string)$transaction['created_at']) * 1000) {
        continue;
    }

    $received = floatval($item['amount']) / (10 ** 6);
    // USDT is quantised to 6 decimals and wallets routinely drop the trailing digits
    // (3.578740 requested vs 3.578700 actually sent), so accept a tiny shortfall rather
    // than refusing a payment that did arrive.
    if ($received >= floatval($transaction['payment_amount']) - AMOUNT_TOLERANCE) {
        $stmt = $db->prepare("SELECT * FROM transactions WHERE tx_hash = ?");
        $stmt->execute([$item['hash']]);
        $transaction_in_db = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$transaction_in_db) {
            $transaction_found = true;
            $tx_hash = $item['hash'];
            break;
        }
    }
}

if ($transaction_found) {
    if (!empty($transaction['customer_email'])) {
        $email = $transaction['customer_email'];
        $subject = 'Payment Completed';
        $message = 'Hi ' . $transaction['customer_name'] . ',<br>';
        $message .= 'Your payment has been completed. Thank you for your purchase.<br><br>';
        $message .= 'Order ID: ' . $transaction['order_id'] . '<br>';
        $message .= 'Amount: ' . $transaction['payment_amount'] . '<br>';
        $message .= 'Transaction Hash: ' . $tx_hash . '<br>';
        $headers = "From: " . ADMIN_EMAIL . "\r\n";
        $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        mail($email, $subject, $message, $headers);
    }
    $stmt = $db->prepare("UPDATE transactions SET status = 'completed', tx_hash = ?, completed_at = NOW() WHERE order_id = ?");
    $stmt->execute([$tx_hash, $order_id]);
    echo json_encode([
        'status' => 'completed',
        'tx_hash' => $tx_hash,
        'order_id' => $transaction['order_id'],
        'amount' => $transaction['payment_amount']
    ]);
} else {
    echo json_encode([
        'status' => $transaction['status'],
        'order_id' => $transaction['order_id'],
        'amount' => $transaction['payment_amount']
    ]);
}