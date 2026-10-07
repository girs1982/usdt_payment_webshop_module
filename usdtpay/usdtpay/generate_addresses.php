<?php
/**
 * Generate TRON (TRC20) addresses from BIP39 mnemonic.
 * Derives m/44'/195'/0'/0/i keypairs, stores address+privkey in MySQL `addresses` table.
 *
 * Usage: php generate_addresses.php <mnemonic> <count> [start_index]
 *
 * Requires: gmp, pdo_mysql, openssl, hash (keccak via pure-PHP fallback if missing).
 */
declare(strict_types=1);

// ---------------- secp256k1 + BIP32 + BIP39 ----------------
const P  = 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F';
const N  = 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141';
const Gx = '79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798';
const Gy = '483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8';

function gmp_hex(string $h): GMP { return gmp_init($h, 16); }

function pt_add($P1, $P2) {
    if ($P1 === null) return $P2;
    if ($P2 === null) return $P1;
    [$x1, $y1] = $P1; [$x2, $y2] = $P2;
    $mod = gmp_hex(P);
    if (gmp_cmp($x1, $x2) === 0 && gmp_cmp(gmp_add($y1, $y2), $mod) === 0) return null;
    if (gmp_cmp($x1, $x2) === 0 && gmp_cmp($y1, $y2) === 0) {
        $lam = gmp_mul(gmp_mul(gmp_init(3), gmp_powm($x1, 2, $mod)), gmp_invert(gmp_mul(gmp_init(2), $y1), $mod));
    } else {
        $lam = gmp_mul(gmp_sub($y2, $y1), gmp_invert(gmp_sub($x2, $x1), $mod));
    }
    $lam = gmp_mod($lam, $mod);
    $x3 = gmp_mod(gmp_sub(gmp_sub(gmp_powm($lam, 2, $mod), $x1), $x2), $mod);
    $y3 = gmp_mod(gmp_sub(gmp_mul($lam, gmp_sub($x1, $x3)), $y1), $mod);
    return [$x3, $y3];
}

function pt_mul(GMP $k, $Pt = null) {
    if ($Pt === null) $Pt = [gmp_hex(Gx), gmp_hex(Gy)];
    $R = null;
    while (gmp_cmp($k, 0) > 0) {
        if (gmp_testbit($k, 0)) $R = pt_add($R, $Pt);
        $Pt = pt_add($Pt, $Pt);
        $k = gmp_div_q($k, 2);
    }
    return $R;
}

function privkey_to_pubkey_uncompressed(GMP $priv): string {
    $Pt = pt_mul($priv);
    $x = gmp_strval($Pt[0], 16);
    $y = gmp_strval($Pt[1], 16);
    $x = str_pad($x, 64, '0', STR_PAD_LEFT);
    $y = str_pad($y, 64, '0', STR_PAD_LEFT);
    return '04' . $x . $y;
}

// ---------------- keccak256 ----------------
function keccak256(string $data): string {
    // Try hash() first (PHP hash keccak256 = 'keccak256' in some builds, else 'sha3-256' is NIST variant)
    if (in_array('keccak256', hash_algos(), true)) {
        return hash('keccak256', $data, true);
    }
    // Pure-PHP Keccak-f[1600], rate 1088 bits = 136 bytes, no padding suffix (legacy Keccak)
    $RC = [
        0x0000000000000001,0x0000000000008082,0x800000000000808A,0x8000000080008000,
        0x000000000000808B,0x0000000080000001,0x8000000080008081,0x8000000000008009,
        0x000000000000008A,0x0000000000000088,0x0000000080008009,0x000000008000000A,
        0x000000008000808B,0x800000000000008B,0x8000000000008089,0x8000000000008003,
        0x8000000000008002,0x8000000000000080,0x000000000000800A,0x800000008000000A,
        0x8000000080008081,0x8000000000008080,0x0000000080000001,0x8000000080008008,
    ];
    $M = 0xFFFFFFFFFFFFFFFF;
    $rate = 136;

    $rol = function(int $x, int $n): int { $n %= 64; return $n===0 ? $x : (($x<<$n) | ($x>>(64-$n))) & 0xFFFFFFFFFFFFFFFF; };

    $keccakF = function(array $A) use ($RC, $rol, $M): array {
        for ($rnd = 0; $rnd < 24; $rnd++) {
            $C = []; $D = [];
            for ($x = 0; $x < 5; $x++) {
                $C[$x] = $A[$x][0] ^ $A[$x][1] ^ $A[$x][2] ^ $A[$x][3] ^ $A[$x][4];
            }
            for ($x = 0; $x < 5; $x++) {
                $D[$x] = $C[($x + 4) % 5] ^ $rol($C[($x + 1) % 5], 1);
            }
            for ($x = 0; $x < 5; $x++) {
                for ($y = 0; $y < 5; $y++) {
                    $A[$x][$y] = ($A[$x][$y] ^ $D[$x]) & $M;
                }
            }
            $B = [[0,0,0,0,0],[0,0,0,0,0],[0,0,0,0,0],[0,0,0,0,0],[0,0,0,0,0]];
            for ($x = 0; $x < 5; $x++) {
                for ($y = 0; $y < 5; $y++) {
                    $B[$y][(2 * $x + 3 * $y) % 5] = $rol($A[$x][$y], (($x * 5 + $y) * 7) % 64);
                }
            }
            for ($x = 0; $x < 5; $x++) {
                for ($y = 0; $y < 5; $y++) {
                    $A[$x][$y] = ($B[$x][$y] ^ ((~$B[($x + 1) % 5][$y]) & $B[($x + 2) % 5][$y])) & $M;
                }
            }
            $A[0][0] = ($A[0][0] ^ $RC[$rnd]) & $M;
        }
        return $A;
    };

    // absorb
    $padded = $data . "\x01" . str_repeat("\x00", ($rate - strlen($data) - 1) % $rate) . "\x80";
    $A = [[0,0,0,0,0],[0,0,0,0,0],[0,0,0,0,0],[0,0,0,0,0],[0,0,0,0,0]];
    for ($off = 0; $off < strlen($padded); $off += $rate) {
        $block = substr($padded, $off, $rate);
        for ($i = 0; $i < $rate / 8; $i++) {
            $lane = 0;
            for ($b = 7; $b >= 0; $b--) {
                $lane = ($lane << 8) | ord($block[$i * 8 + (7 - $b)]);
            }
            $A[$i % 5][(int)floor($i / 5)] ^= $lane;
        }
        $A = $keccakF($A);
    }
    // squeeze 32 bytes
    $out = '';
    while (strlen($out) < 32) {
        for ($i = 0; $i < $rate / 8 && strlen($out) < 32; $i++) {
            $lane = $A[$i % 5][(int)floor($i / 5)];
            $bytes = '';
            for ($b = 0; $b < 8; $b++) {
                $bytes = chr($lane & 0xFF) . $bytes;
                $lane >>= 8;
            }
            $out .= $bytes;
        }
        if (strlen($out) < 32) $A = $keccakF($A);
    }
    return substr($out, 0, 32);
}

// ---------------- base58 ----------------
function base58_encode(string $bin): string {
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $n = gmp_init(bin2hex($bin), 16);
    $res = '';
    while (gmp_cmp($n, 0) > 0) {
        [$n, $r] = gmp_div_qr($n, 58);
        $res = $alphabet[gmp_intval($r)] . $res;
    }
    $pad = 0;
    while (isset($bin[$pad]) && $bin[$pad] === "\x00") $pad++;
    return str_repeat($alphabet[0], $pad) . $res;
}

function privkey_to_tron_address(GMP $priv): string {
    $pub = privkey_to_pubkey_uncompressed($priv);
    $hash = keccak256(hex2bin(substr($pub, 2)));
    $payload = "\x41" . substr($hash, 12, 20);
    $checksum = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);
    return base58_encode($payload . $checksum);
}

// ---------------- BIP39 ----------------
function mnemonic_to_seed(string $mnemonic, string $passphrase = ''): string {
    $words = explode(' ', trim($mnemonic));
    $cnt = count($words);
    if (!in_array($cnt, [12, 15, 18, 21, 24], true)) {
        fwrite(STDERR, "invalid word count: $cnt\n");
        exit(1);
    }
    $wordlist = array_map('trim', file(__DIR__ . '/english.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    if (count($wordlist) !== 2048) {
        fwrite(STDERR, "wordlist corrupted: " . count($wordlist) . " words\n");
        exit(1);
    }
    $idx = array_flip($wordlist);
    $bits = gmp_init(0);
    foreach ($words as $w) {
        if (!isset($idx[$w])) {
            fwrite(STDERR, "invalid word: $w\n");
            exit(1);
        }
        $bits = gmp_or(gmp_mul($bits, gmp_init(2048)), gmp_init($idx[$w]));
    }
    $totalBits = 11 * $cnt;
    $entBits = $totalBits - intdiv($cnt, 3);
    $entropy = gmp_div_q($bits, gmp_pow(2, $totalBits - $entBits));
    $entBytes = gmp_export($entropy, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
    $entBytes = str_pad($entBytes, $entBits / 8, "\x00", STR_PAD_LEFT);
    return hash_pbkdf2('sha512', $entBytes, 'mnemonic' . $passphrase, 2048, 64, true);
}

// ---------------- BIP32 ----------------
function ckd_priv(GMP $kpar, string $cpar, int $i): array {
    $serK = str_pad(gmp_export($kpar, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN), 32, "\x00", STR_PAD_LEFT);
    if ($i >= 0x80000000) {
        $data = "\x00" . $serK . pack('N', $i);
    } else {
        $Pt = pt_mul($kpar);
        $pub = privkey_to_pubkey_uncompressed($kpar);
        $data = hex2bin($pub) . pack('N', $i);
    }
    $I = hash_hmac('sha512', $data, $cpar, true);
    $IL = substr($I, 0, 32);
    $IR = substr($I, 32);
    $il = gmp_import($IL, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
    $n = gmp_hex(N);
    if (gmp_cmp($il, $n) >= 0) throw new RuntimeException('IL >= N');
    $ki = gmp_mod(gmp_add($il, $kpar), $n);
    if (gmp_cmp($ki, 0) === 0) throw new RuntimeException('ki == 0');
    return [$ki, $IR];
}

function derive_master(string $seed): array {
    $I = hash_hmac('sha512', $seed, 'Bitcoin seed', true);
    $IL = substr($I, 0, 32);
    $IR = substr($I, 32);
    $k = gmp_import($IL, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
    return [$k, $IR];
}

function parse_path(string $p): array {
    $out = [];
    foreach (explode('/', $p) as $i => $part) {
        if ($i === 0) continue; // 'm'
        if (str_ends_with($part, "'")) $out[] = intval($part) + 0x80000000;
        else $out[] = intval($part);
    }
    return $out;
}

// ---------------- main ----------------
$mnemonic = $argv[1] ?? '';
$count = isset($argv[2]) ? intval($argv[2]) : 10;
$start = isset($argv[3]) ? intval($argv[3]) : 0;

if (trim($mnemonic) === '') {
    fwrite(STDERR, "usage: php generate_addresses.php <mnemonic> <count> [start]\n");
    exit(1);
}

$seed = mnemonic_to_seed($mnemonic);
[$k, $c] = derive_master($seed);

// m/44'/195'/0'
foreach (parse_path("m/44'/195'/0'") as $idx) {
    [$k, $c] = ckd_priv($k, $c, $idx);
}

// connect MySQL
require_once __DIR__ . '/config.php';
$db = getDBConnection();

$db->exec("CREATE TABLE IF NOT EXISTS addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    address VARCHAR(64) NOT NULL UNIQUE,
    privkey VARCHAR(64) NOT NULL,
    assigned TINYINT NOT NULL DEFAULT 0,
    swept DATETIME NULL,
    sweep_tx VARCHAR(128) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmt = $db->prepare("INSERT IGNORE INTO addresses (address, privkey, assigned, addr_index) VALUES (?,?,0,?)");

$added = 0;
for ($i = $start; $i < $start + $count; $i++) {
    [$ki, $ci] = ckd_priv($k, $c, $i);
    $addr = privkey_to_tron_address($ki);
    $hex = str_pad(gmp_export($ki, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN), 32, "\x00", STR_PAD_LEFT);
    $stmt->execute([$addr, bin2hex($hex), $i]);
    if ($stmt->rowCount() > 0) {
        $added++;
        echo "$addr\n";
    }
}
echo "added $added addresses (index $start.." . ($start + $count - 1) . ")\n";
