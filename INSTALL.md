# Установка и настройка USDT Payment Gateway

## Требования

- PHP 7.4+
- MySQL 5.7+ (или MariaDB)
- TronGrid API ключ

## Установка

1. Скачайте репозиторий:
   ```bash
   git clone https://github.com/girs1982/usdt_payment_webshop_module.git
   cd usdt_payment_webshop_module
   ```
2. Создайте базу данных `usdtpay` в панели управления хостингом (или через phpMyAdmin).
3. Настройте `usdtpay/config.php` с вашими данными MySQL:
   - DB_HOST: `localhost` или `127.0.0.1`
   - DB_USER: ваш логин от БД
   - DB_PASS: ваш пароль от БД
   - DB_NAME: `usdtpay`
4. Импортируйте схему через phpMyAdmin (импорт `usdtpay/database.sql`) или через терминал:
   ```bash
   mysql -u ваш-логин -p your_db_name < usdtpay/database.sql
   ```
   Если MySQL слушает на нестандартном порту (например, 3307), укажите хост как `127.0.0.1:3307`.
5. Настройте веб-сервер для работы с PHP.

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