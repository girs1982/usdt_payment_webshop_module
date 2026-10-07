<?php
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
define('QR_LOG_DIR', __DIR__ . '/qr_images/');
include __DIR__ . '/phpqrcode/qrlib.php';

function newUsdtAddress(): string {
    return 'T' . substr(hash('sha256', random_bytes(32)), 0, 25);
}

$addr = newUsdtAddress();
ob_start();
QRcode::png($addr, null, QR_ECLEVEL_M, 8, 2);
$png = ob_get_clean();
$qrB64 = base64_encode($png);
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>USDT Payment</title></head>
<body>
<h1>USDT Payment</h1>
<p>Address: <strong><?php echo $addr; ?></strong></p>
<img src="data:image/png;base64,<?php echo $qrB64; ?>" alt="QR" width="220" height="220">
</body></html>
