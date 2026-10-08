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
                WHERE a.assigned = 0
                  AND NOT EXISTS (
                    SELECT 1 FROM transactions t
                    WHERE t.address = a.address AND t.status = 'pending'
                )
                ORDER BY a.id ASC LIMIT 1";

    $stmt = $db->query($freeSql);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Count free
    $avail = $db->query("SELECT COUNT(*) FROM addresses WHERE assigned = 0")->fetchColumn();
    error_log('--- pool: free rows='.$avail);
    $need = max(0, 5 - $avail);
    if ($need > 0) {
        error_log('--- pool: need='.$need.' calling generateNewAddresses');
        if (!function_exists('generateNewAddresses')) {
            require_once __DIR__ . '/generate_addresses.php';
        }
        $generatedNew = generateNewAddresses($need);
        error_log('--- pool: generated='.count($generatedNew));
    }
    $stmt = $db->query($freeSql);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return ['address' => null, 'privkey' => null];
    }

    // помечаем адрес занятым, чтобы не выдавать повторно
    $db->prepare('UPDATE addresses SET assigned=1 WHERE id=?')->execute([$row['id']]);

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

    // Fallback: try shell exec; if it fails (e.g. shell_exec disabled on shared hosting),
    // generate in-process using the pure-PHP derivation from generate_addresses.php.
    $cmd = sprintf('php %s %s %d %d 2>&1',
        escapeshellarg($script), escapeshellarg($mnemonic), $count, $startIndex);

    $out = @shell_exec($cmd);
    if ($out === null || $out === '') {
        // in-process fallback
        $addresses = [];
        if (!function_exists('mnemonic_to_seed')) {
            // generate_addresses.php has a CLI-only main section; strip it before eval
            $genSrc = file_get_contents($script);
            $cutPos = strpos($genSrc, "// ---------------- main ----------------");
            if ($cutPos !== false) {
                $genSrc = substr($genSrc, 0, $cutPos);
            }
            eval('?>' . $genSrc);
        }
        $seed = mnemonic_to_seed($mnemonic);
        [$k, $c] = derive_master($seed);
        foreach (parse_path("m/44'/195'/0'") as $idx) {
            [$k, $c] = ckd_priv($k, $c, $idx);
        }
        $stmtIns = $db->prepare("INSERT IGNORE INTO addresses (address, privkey, assigned, addr_index) VALUES (?,?,0,?)");
        for ($i = $startIndex; $i < $startIndex + $count; $i++) {
            [$ki, $ci] = ckd_priv($k, $c, $i);
            $addr = privkey_to_tron_address($ki);
            $hex = str_pad(gmp_export($ki, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN), 32, "\x00", STR_PAD_LEFT);
            $stmtIns->execute([$addr, bin2hex($hex), $i]);
            if ($stmtIns->rowCount() > 0) {
                $addresses[] = $addr;
            }
        }
        return $addresses;
    }

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
