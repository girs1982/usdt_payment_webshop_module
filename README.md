# USDT Payment Gateway

## Установка на хост

1. Скачайте репозиторий: `git clone https://github.com/girs1982/usdt_payment_webshop_module.git`.
2. Перейдите в директорию проекта: `cd usdt_payment_webshop_module`.
3. Создайте базу данных `usdtpay` в панели хостинга или phpMyAdmin.
4. Настройте параметры в `usdtpay/config.php`:
   - DB_HOST: `localhost` (или `127.0.0.1:3307` если MySQL слушает на другом порту)
   - DB_USER: ваш логин от БД
   - DB_PASS: ваш пароль от БД
   - DB_NAME: `usdtpay`
5. Импортируйте схему через phpMyAdmin (файл `usdtpay/database.sql`) или через терминал:
   ```bash
   mysql -u ваш-логин -p usdtpay < usdtpay/database.sql
   ```
   Если у вас нет mysql CLI — импортируйте через phpMyAdmin: выберите базу `usdtpay`, импорт из `usdtpay/database.sql`.
6. Настройте веб-сервер для работы с PHP (Apache/Nginx).
7. Убедитесь, что таблицы созданы: в админке (`/admin/transactions.php`) должен отображаться список транзакций (если пусто — это нормально).
8. Проверьте доступность страниц:
   - `http://ваш-домен/usdtpay/public/checkout.php` — форма оплаты
   - `http://ваш-домен/usdtpay/admin/login.php` — вход в админку
9. Если страницы не открываются:
   - Проверьте права на файлы: `chmod -R 755 usdtpay/`
   - Проверьте конфиг веб-сервера (Apache/Nginx) — корень должен указывать на `usdtpay/public/`

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