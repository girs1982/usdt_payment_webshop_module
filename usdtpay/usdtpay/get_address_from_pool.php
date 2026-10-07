<?php
/**
 * Возвращает свободный TRON-адрес из пула.
 * Свободный = нет pending-транзакции на этот адрес.
 * Если таких нет — генерирует новые из мнемоники и берёт первый.
 */
function getAddressFromPool(): array
{
    global $db;
    if (!$db) {
        $db = getDBConnection();
    }

    // 1. Таблица существует?
    try {
        $db->query("SELECT 1 FROM addresses LIMIT 1");
    } catch (Exception $e) {
        $db->exec("CREATE TABLE IF NOT EXISTS addresses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            address VARCHAR(64) NOT NULL UNIQUE,
            privkey VARCHAR(64) NOT NULL,
            addr_index INT NOT NULL DEFAULT 0,
            assigned TINYINT(1) NOT NULL DEFAULT 0,
            swept TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    $freeSql = "SELECT a.id, a.address, a.privkey
                FROM addresses a
                WHERE NOT EXISTS (
                    SELECT 1 FROM transactions t
                    WHERE t.address = a.address AND t.status = 'pending'
                )
                ORDER BY a.id ASC LIMIT 1";

    $stmt = $db->query($freeSql);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Нет свободных — генерируем пул
    if (!$row) {
        generateNewAddresses(5);
        $stmt = $db->query($freeSql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$row) {
        return ['address' => null, 'privkey' => null];
    }

    return $row;
}

/**
 * Генерирует новые TRON-адреса из мнемоники.
 * Мнемоника: settings.mnemonic (если есть) → TRON_MNEMONIC (config.php).
 */
function generateNewAddresses(int $count): array
{
    global $db;
    if (!$db) {
        $db = getDBConnection();
    }

    $mnemonic = null;
    try {
        $stmt = $db->query("SELECT mnemonic FROM settings LIMIT 1");
        if ($stmt) {
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($r['mnemonic'])) {
                $mnemonic = $r['mnemonic'];
            }
        }
    } catch (Exception $e) {
        // колонки нет — константа из config.php
    }
    if (!$mnemonic && defined('TRON_MNEMONIC')) {
        $mnemonic = TRON_MNEMONIC;
    }
    if (!$mnemonic) {
        $mnemonic = 'tissue suggest badge roast vintage tomato emerge prefer orbit night front divorce';
    }

    // Стартовый индекс = max(addr_index) + 1
    $maxIndex = -1;
    try {
        $stmt = $db->query("SELECT MAX(addr_index) AS m FROM addresses");
        if ($stmt) {
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if (isset($r['m']) && $r['m'] !== null) {
                $maxIndex = (int)$r['m'];
            }
        }
    } catch (Exception $e) {
        // колонки нет — стартуем с 0
    }
    $startIndex = $maxIndex + 1;

    $script = __DIR__ . '/generate_addresses.php';
    if (!file_exists($script)) {
        throw new RuntimeException('Генератор не найден: ' . $script);
    }

    $cmd = sprintf('php %s %s %d %d 2>&1',
        escapeshellarg($script), escapeshellarg($mnemonic), $count, $startIndex);
    $out = shell_exec($cmd);

    $addresses = [];
    if ($out) {
        foreach (explode("\n", $out) as $line) {
            $line = trim($line);
            // TRON mainnet адреса начинаются на 'T', base58, ~34 символа
            if (preg_match('/^T[1-9A-HJ-NP-Za-km-z]{30,44}$/', $line)) {
                $addresses[] = $line;
            }
        }
    }
    return $addresses;
}
