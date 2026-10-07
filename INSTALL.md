# Установка и настройка USDT Payment Gateway

## Требования

- PHP 7.4+
- MySQL 5.7+
- TronGrid API ключ

## Установка

1. Скачайте репозиторий:
   ```bash
   git clone https://github.com/girs1982/usdt_payment_webshop_module.git
   cd usdt_payment_webshop_module
   ```
2. Настройте `config.php` с вашими параметрами.
3. Импортируйте базу данных:
   ```bash
   mysql -u ваш-пользователь -p ваша-база < usdtpay/database.sql
   ```
4. Настройте веб-сервер для работы с PHP.

## Настройка плагина JoomShopping

1. Скопируйте `joomshopping/pm_usdt/` в `components/com_jshopping/payments/pm_usdt/`.
2. В админке JoomShopping добавьте платежный метод USDT.
3. Введите URL API и API-ключ.

## Использование API

### Создание платежа

```php
<?php
$apiUrl = 'http://ваш-домен/api/order.php';
$apiKey = 'ваш-api-ключ';
$orderData = [
    'amount' => 10.00,
    'order_id' => 'order123',
    'customer_name' => 'Иван Иванов',
    'customer_email' => 'ivan@example.com',
    'shop' => 'ваш-магазин',
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