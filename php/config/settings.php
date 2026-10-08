<?php
// Дополнительные настройки
$settings = [
    'debug' => true,
    'timezone' => 'UTC',
    'default_language' => 'ru',
];

// Установка временной зоны
date_default_timezone_set($settings['timezone']);
?>