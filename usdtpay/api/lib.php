<?php
// Shared helpers for REST API + checkout polling.
declare(strict_types=1);

function apiEnsureSchema(PDO $db): void {
    foreach ([
        "ALTER TABLE settings ADD COLUMN api_token VARCHAR(64) NULL",
        "ALTER TABLE transactions ADD COLUMN shop VARCHAR(64) NULL",
        "ALTER TABLE transactions ADD COLUMN webhook_url TEXT NULL",
        "ALTER TABLE transactions ADD COLUMN return_url TEXT NULL",
        "ALTER TABLE transactions ADD COLUMN expires_at DATETIME NULL",
    ] as $sql) {
        try { $db->exec($sql); } catch (Throwable $e) { /* col exists */ }
    }
    try {
        $tok = (string)$db->query('SELECT api_token FROM settings LIMIT 1')->fetchColumn();
        if ($tok === '') {
            $tok = bin2hex(random_bytes(32));
            $db->prepare('UPDATE settings SET api_token=? WHERE id=1')->execute([$tok]);
        }
    } catch (Throwable $e) { /* settings missing */ }
}

function apiGetToken(PDO $db): string {
    try { return (string)$db->query('SELECT api_token FROM settings LIMIT 1')->fetchColumn(); }
    catch (Throwable $e) { return ''; }
}

function apiAuth(PDO $db): bool {
    $tok = apiGetToken($db);
    if ($tok === '') return false;
    $got = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($got === '' && isset($_SERVER['HTTP_AUTHORIZATION']) && str_starts_with($_SERVER['HTTP_AUTHORIZATION'], 'Bearer ')) {
        $got = substr($_SERVER['HTTP_AUTHORIZATION'], 7);
    }
    if ($got === '') $got = $_GET['api_key'] ?? '';
    return $got !== '' && hash_equals($tok, $got);
}

function apiSendWebhook(PDO $db, array $tx): void {
    if (empty($tx['webhook_url'])) return;
    $body = json_encode([
        'event' => 'payment.' . $tx['status'],
        'order_id' => $tx['order_id'],
        'status' => $tx['status'],
        'address' => $tx['address'],
        'payment_amount' => $tx['payment_amount'],
        'tx_hash' => $tx['tx_hash'] ?? null,
    ]);
    $sig = hash_hmac('sha256', $body, apiGetToken($db));
    $ch = curl_init($tx['webhook_url']);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Signature: ' . $sig],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    curl_exec($ch);
    curl_close($ch);
}

// Scan chain for one pending order. Same logic as public/check_payment.php.
function apiPollOrder(PDO $db, array $tx): array {
    $trc20Id = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
    $url = 'https://apilist.tronscanapi.com/api/transfer/trc20?address='
        . $tx['address'] . "&trc20Id={$trc20Id}&limit=50&direction=2";
    $apiKey = '';
    try { $apiKey = (string)$db->query('SELECT tron_api_key FROM settings LIMIT 1')->fetchColumn(); }
    catch (Throwable $e) {}
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $apiKey !== '' ? ["TRON-PRO-API-KEY: $apiKey"] : [],
        CURLOPT_TIMEOUT => 10]);
    $resp = curl_exec($ch);
    if (curl_errno($ch)) { curl_close($ch); return ['ok' => false, 'error' => 'Blockchain API unavailable']; }
    curl_close($ch);
    $data = json_decode($resp, true);
    if (!is_array($data) || !isset($data['data'])) return ['ok' => false, 'error' => 'Invalid API response'];
    foreach ($data['data'] as $item) {
        if (($item['contract_ret'] ?? '') !== 'SUCCESS'
            || (int)($item['revert'] ?? 0) !== 0
            || (int)($item['confirmed'] ?? 0) !== 1
            || ($item['to'] ?? '') !== $tx['address']) continue;
        if ((int)($item['block_timestamp'] ?? 0) < strtotime((string)$tx['created_at']) * 1000) continue;
        if (floatval($item['amount']) / 1e6 < floatval($tx['payment_amount']) - 0.0001) continue;
        $st = $db->prepare('SELECT id FROM transactions WHERE tx_hash = ?');
        $st->execute([$item['hash']]);
        if ($st->fetch()) continue;
        $db->prepare("UPDATE transactions SET status='completed', tx_hash=?, completed_at=NOW() WHERE order_id=?")
            ->execute([$item['hash'], $tx['order_id']]);
        $tx['status'] = 'completed';
        $tx['tx_hash'] = $item['hash'];
        if (!empty($tx['customer_email'])) {
            @mail($tx['customer_email'], 'Payment Completed',
                "Hi {$tx['customer_name']},<br>Order {$tx['order_id']} paid: {$tx['payment_amount']} USDT.<br>Tx: {$item['hash']}<br>",
                "From: " . ADMIN_EMAIL . "\r\nContent-Type: text/html; charset=UTF-8\r\n");
        }
        apiSendWebhook($db, $tx);
        return ['ok' => true, 'paid' => true, 'tx_hash' => $item['hash']];
    }
    return ['ok' => true, 'paid' => false];
}
