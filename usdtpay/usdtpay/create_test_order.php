<?php
require_once 'config.php';
$db = getDBConnection();
$settingsStmt = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
$address = $settings['usdt_address'];
$order_id = 'tusdt1';
$customer_name = 'Test User';
$customer_email = 'test@example.com';
$real_amount = 1.0;
$payment_amount = 1.0;
$network = 'TRON';
$stmt = $db->prepare('INSERT INTO transactions (order_id, customer_name, customer_email, real_amount, payment_amount, address, network, status) VALUES (?,?,?,?,?,?,?,?)');
$stmt->execute([$order_id,$customer_name,$customer_email,$real_amount,$payment_amount,$address,$network,'pending']);
echo "Order ID: $order_id created.\n";
echo "Checkout URL: http://192.168.80.200:8080/checkout/$order_id\n";
?>