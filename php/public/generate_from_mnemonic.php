<?php
$mnemonic = $_GET['mnemonic'] ?? '';
$index = (int)($_GET['index'] ?? 0);
if (!$mnemonic){ http_response_code(400); echo json_encode(['error'=>'missing mnemonic']); exit; }
$cmd = sprintf('php %s %s 1 %d 2>&1', escapeshellarg(__DIR__.'/../generate_addresses.php'), escapeshellarg($mnemonic), $index);
$out = shell_exec($cmd);
$parts = preg_split('/\s+/', trim($out));
$addr = $parts[0] ?? null;
$priv = $parts[1] ?? null;
echo json_encode(['address'=>$addr,'privkey'=>$priv,'output'=>$out]);
?>