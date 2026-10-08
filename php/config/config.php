<?php
// Основные настройки
$config = [
    'db_host' => 'localhost',
    'db_name' => 'joomshopping_usdt',
    'db_user' => 'root',
    'db_pass' => '',
    'db_charset' => 'utf8mb4',
    'site_url' => 'http://localhost/joomshopping_usdt_php80',
];

// Загрузка базы данных
require_once __DIR__ . '/database.php';

// Загрузка настроек
require_once __DIR__ . '/settings.php';
?>