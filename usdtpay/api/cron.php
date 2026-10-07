<?php
// Cron every minute: poll pending orders, expire timed-out, fire webhooks.
// * * * * * curl -fsS -H "X-API-Key: $TOKEN" http://web/api/cron.php
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/lib.php';

header('Content-Type: application/json');
$db = getDBConnection();
apiEnsureSchema($db);
if (!apiAuth($db)) { http_response_code(401); echo json_encode(['error' => 'unauthorized']); exit; }

$st = $db->query("SELECT * FROM transactions WHERE status='pending'");
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
$done = $paid = $expired = 0;
foreach ($rows as $tx) {
    $done++;
    if (!empty($tx['expires_at']) && strtotime($tx['expires_at']) < time()) {
        $db->prepare("UPDATE transactions SET status='cancelled', failure_reason='expired' WHERE order_id=?")->execute([$tx['order_id']]);
        $db->prepare('UPDATE addresses SET assigned=0 WHERE address=?')->execute([$tx['address']]);
        apiSendWebhook($db, array_merge($tx, ['status' => 'cancelled']));
        $expired++;
        continue;
    }
    $r = apiPollOrder($db, $tx);
    if (($r['paid'] ?? false)) $paid++;
}
echo json_encode(['checked' => $done, 'paid' => $paid, 'expired' => $expired]);
