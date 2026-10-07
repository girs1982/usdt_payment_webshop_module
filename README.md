# USDT Payment Gateway

## Установка на хост

1. Скачайте репозиторий: `git clone https://github.com/girs1982/usdt_payment_webshop_module.git`.
2. Перейдите в директорию проекта: `cd usdt_payment_webshop_module`.
3. Настройте параметры в `config.php` (DB_HOST, DB_USER, DB_PASS, TRON_API_KEY, TRON_MNEMONIC).
4. Импортируйте базу данных: `mysql -u ваш-пользователь -p ваша-база < usdtpay/database.sql`.
5. Настройте веб-сервер для работы с PHP (Apache/Nginx).

## Подключение API к магазину

1. Установите плагин в ваш магазин (например, JoomShopping).
2. В админке магазина найдите настройки платежа USDT.
3. Введите URL API: `http://ваш-домен/api/`.
4. Введите API-ключ (из `settings.php` в админке USDT Pay).
5. Сохраните настройки.

## Подключение без магазина

### Через форму

1. Откройте `public/checkout.php` в браузере.
2. Введите сумму и нажмите "Создать платеж".
3. Скопируйте адрес и отправьте USDT на него.
4. После оплаты вы увидите подтверждение.

### Через PHP-код

```php
<?php
// Создание платежа
$apiUrl = 'http://ваш-домен/api/order.php';
$apiKey = 'ваш-api-ключ';
$orderData = [
    'amount' => 10.00, // сумма
    'order_id' => 'order123', // уникальный ID заказа
    'customer_name' => 'Иван Иванов', // имя клиента
    'customer_email' => 'ivan@example.com', // email клиента
    'shop' => 'ваш-магазин', // название магазина
];

$ch = curl_init($apiUrl);
$headers = [
    'Content-Type: application/json',
    'X-API-Key: ' . $apiKey
];
$options = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($orderData),
    CURLOPT_HTTPHEADER => $headers
];
curl_setopt_array($ch, $options);
$response = curl_exec($ch);
curl_close($ch);

$responseData = json_decode($response, true);
if (isset($responseData['address'])) {
    echo "Адрес для оплаты: " . $responseData['address'] . "\n";
    echo "Ссылка на оплату: " . $responseData['checkout_url'] . "\n";
} else {
    echo "Ошибка: " . ($responseData['error'] ?? 'неизвестная ошибка');
}
?>
```