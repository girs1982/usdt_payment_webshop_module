<?php
require_once __DIR__ . '/../config.php';

if (!isAdminLoggedIn()) {
    http_response_code(403);
    exit('Forbidden');
}

$db = getDBConnection();
$orderId = $_GET['id'] ?? null;
if (!$orderId) {
    header('Location: ' . BASE_URL . '/admin/transactions');
    exit();
}

$query = "SELECT address FROM transactions WHERE order_id = :order_id";
$stmt = $db->prepare($query);
$stmt->bindValue(':order_id', $orderId, PDO::PARAM_STR);
$stmt->execute();
$address = (string)$stmt->fetchColumn();

$stmt = $db->prepare("DELETE FROM transactions WHERE order_id = :order_id");
$stmt->bindValue(':order_id', $orderId, PDO::PARAM_STR);
$stmt->execute();

// Deleting the order must release its address back into the pool, otherwise the
// row stays assigned=1 forever and the pool keeps generating fresh addresses.
if ($address !== '') {
    $db->prepare('UPDATE addresses SET assigned = 0 WHERE address = ?')->execute([$address]);
}

$_SESSION['success_message'] = 'Transaction deleted successfully';
header('Location: ' . BASE_URL . '/admin/transactions');
exit();

