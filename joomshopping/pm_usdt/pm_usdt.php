<?php
defined('_JEXEC') or die();

class pm_usdt extends PaymentRoot {

    function showAdminFormParams($params) {
        foreach (['api_url', 'api_key', 'transaction_end_status', 'transaction_pending_status', 'transaction_failed_status'] as $k) {
            if (!isset($params[$k])) $params[$k] = '';
        }
        $orders = \Joomla\Component\Jshopping\Site\Lib\JSFactory::getModel('orders');
        include(dirname(__FILE__) . "/adminparamsform.php");
    }

    private function api($pmconfigs, $path, $data = null) {
        $ch = curl_init(rtrim($pmconfigs['api_url'], '/') . $path);
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-API-Key: ' . $pmconfigs['api_key']]];
        if ($data !== null) { $opts[CURLOPT_POST] = true; $opts[CURLOPT_POSTFIELDS] = json_encode($data); }
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        curl_close($ch);
        return json_decode((string)$resp, true) ?: [];
    }

    private function gwOrderId($order) { return 'jshop-' . $order->order_id; }

    function showEndForm($pmconfigs, $order) {
        $r = $this->api($pmconfigs, '/api/order.php', [
            'amount' => (float)$order->order_total,
            'order_id' => $this->gwOrderId($order),
            'customer_name' => trim(($order->f_name ?? '') . ' ' . ($order->l_name ?? '')),
            'customer_email' => $order->email ?? '',
            'shop' => 'joomshopping',
        ]);
        if (empty($r['address'])) {
            echo '<div class="alert alert-error">USDT gateway error: ' . htmlspecialchars($r['error'] ?? 'no address') . '</div>';
            return;
        }
        $amount = htmlspecialchars($r['payment_amount']);
        $addr = htmlspecialchars($r['address']);
        $url = htmlspecialchars($r['checkout_url']);
        echo <<<HTML
        <div class="usdt-pay" style="text-align:center;padding:20px">
          <p>Send exactly <b>{$amount} USDT (TRC20)</b> to:</p>
          <p style="font-family:monospace;word-break:break-all">{$addr}</p>
          <p><a class="btn btn-primary" target="_blank" href="{$url}">Open payment page</a></p>
          <p><small>After paying, press Continue — the shop checks the blockchain automatically.</small></p>
        </div>
HTML;
    }

    // JShopping calls this on return/notify; we re-poll the gateway (webhook is hint only).
    // Returns array($rescode, $restext, $transaction, $transactiondata).
    function checkTransaction($pmconfigs, $order, $act) {
        $r = $this->api($pmconfigs, '/api/status.php?order_id=' . urlencode($this->gwOrderId($order)));
        if (($r['status'] ?? '') === 'completed') return [1, '', $r['tx_hash'] ?? '', $r];
        if (($r['status'] ?? '') === 'pending') return [2, 'Waiting for USDT payment', '', $r];
        return [3, 'USDT payment ' . ($r['status'] ?? $r['error'] ?? 'failed'), '', $r];
    }
}
