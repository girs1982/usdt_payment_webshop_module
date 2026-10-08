<?php
// Подключение к базе данных
try {
    $pdo = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']};charset={$config['db_charset']}", $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}
?>