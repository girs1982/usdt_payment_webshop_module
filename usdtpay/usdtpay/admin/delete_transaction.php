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

$query = "DELETE FROM transactions WHERE order_id = :order_id";
$stmt = $db->prepare($query);
$stmt->bindValue(':order_id', $orderId, PDO::PARAM_STR);
$stmt->execute();

$_SESSION['success_message'] = 'Transaction deleted successfully';
header('Location: ' . BASE_URL . '/admin/transactions');
exit();

