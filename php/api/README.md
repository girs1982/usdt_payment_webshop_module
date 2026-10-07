# USDT Pay Shop API

Base: `https://gateway-host/` (routes in `.htaccess`: `/api/order`, `/api/status`, `/api/cancel`, `/api/cron`).
Auth: header `X-API-Key: <token>` (token in admin Settings, auto-generated). All responses JSON.

## Create payment

`POST /api/order.php`

```json
{"amount": 12.5, "order_id": "shop-123", "customer_name": "Ivan",
 "customer_email": "i@x.ru", "shop": "joomshopping",
 "webhook_url": "https://shop/notify", "return_url": "https://shop/done", "expire_min": 60}
```

`order_id` optional — generated if empty. `-> {order_id, address, payment_amount, network, checkout_url, expires_at}`.
Address = EOA from pool (unique per order). Pay exact `payment_amount` (amount + 0.0001–0.9999 tag).

## Check status (polls chain, completes order, fires webhook)

`GET /api/status.php?order_id=shop-123` → `{order_id, status, address, payment_amount, tx_hash, checkout_url}`
`status`: `pending` | `completed` | `cancelled` | `failed`.

## Cancel (frees address back to pool)

`GET /api/cancel.php?order_id=shop-123` → `{status: cancelled}`.

## Cron (every minute: poll pendings, expire timed-out, webhooks)

```
* * * * * curl -fsS -H "X-API-Key: TOKEN" https://gateway-host/api/cron.php
```
→ `{checked, paid, expired}`.

## Webhook

`POST {webhook_url}` body `{event, order_id, status, address, payment_amount, tx_hash}`,
header `X-Signature: HMAC-SHA256(body, api_token)`. Verify:

```php
$ok = hash_equals(hash_hmac('sha256', file_get_contents('php://input'), $TOKEN),
    $_SERVER['HTTP_X_SIGNATURE'] ?? '');
```

Re-poll `/api/status.php` on notify — treat webhook as hint only.

## JoomShopping

1. Copy `joomshopping/pm_usdt/` into `components/com_jshopping/payments/pm_usdt/`.
2. Admin → JoomShopping → Payment methods → create method, class `pm_usdt`, set gateway URL + API key.
3. Flow: checkout shows address/amount → customer pays → step7 polls gateway → order status updates.

## Any other shop (PHP snippet)

```php
$api = 'https://gateway-host'; $key = 'TOKEN';
$ctx = stream_context_create(['http' => ['method' => 'POST',
    'header' => "Content-Type: application/json\r\nX-API-Key: $key",
    'content' => json_encode(['amount' => $total, 'order_id' => "order-$id])]]);
$pay = json_decode(file_get_contents("$api/api/order.php", false, $ctx), true);
// show $pay['address'] + $pay['payment_amount'], link $pay['checkout_url']
// on return/webhook: GET $api/api/status.php?order_id=... (same header) → completed? deliver.
```
