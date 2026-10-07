<?php
/**
 * TRC20 address derivation from a BIP39 mnemonic.
 * Path m/44'/195'/0'/0/i. Uses vendored elliptic-php + kornrunner Keccak
 * (the pure-PHP keccak that shipped with this project produced wrong addresses).
 *
 * tronAddressFromMnemonic($mnemonic, $index): ['address' => base58, 'privkey' => hex]
 */
require_once __DIR__ . '/autoload.php';

use Elliptic\EC;
use kornrunner\Keccak;

const DER_N  = 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141';

function mnemonic_to_seed(string $mnemonic, string $passphrase = ''): string {
    $words = explode(' ', trim($mnemonic));
    $cnt = count($words);
    if (!in_array($cnt, [12, 15, 18, 21, 24], true)) {
        throw new RuntimeException("invalid word count: $cnt");
    }
    $wordlist = array_map('trim', file(__DIR__ . '/../../english.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    if (count($wordlist) !== 2048) {
        throw new RuntimeException('BIP39 wordlist corrupted: ' . count($wordlist));
    }
    $idx = array_flip($wordlist);
    $bits = gmp_init(0);
    foreach ($words as $w) {
        if (!isset($idx[$w])) {
            throw new RuntimeException("invalid BIP39 word: $w");
        }
        $bits = gmp_or(gmp_mul($bits, gmp_init(2048)), gmp_init($idx[$w]));
    }
    $totalBits = 11 * $cnt;
    $entBits = $totalBits - intdiv($cnt, 3);
    $entropy = gmp_div_q($bits, gmp_pow(2, $totalBits - $entBits));
    $entBytes = gmp_export($entropy, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
    $entBytes = str_pad($entBytes, intdiv($entBits, 8), "\x00", STR_PAD_LEFT);
    return hash_pbkdf2('sha512', $entBytes, 'mnemonic' . $passphrase, 2048, 64, true);
}

function ckd_priv(GMP $kpar, string $cpar, int $i): array {
    $serK = str_pad(gmp_export($kpar, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN), 32, "\x00", STR_PAD_LEFT);
    if ($i >= 0x80000000) {
        $data = "\x00" . $serK . pack('N', $i);
    } else {
        $pub = privkey_to_pubkey_hex($kpar);
        $data = hex2bin($pub) . pack('N', $i);
    }
    $I = hash_hmac('sha512', $data, $cpar, true);
    $il = gmp_import(substr($I, 0, 32), 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
    $n = gmp_init(DER_N, 16);
    if (gmp_cmp($il, $n) >= 0) {
        throw new RuntimeException('IL >= N');
    }
    $ki = gmp_mod(gmp_add($il, $kpar), $n);
    if (gmp_cmp($ki, 0) === 0) {
        throw new RuntimeException('ki == 0');
    }
    return [$ki, substr($I, 32)];
}

function privkey_to_pubkey_hex(GMP $priv): string {
    $hex = str_pad(gmp_export($priv, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN), 32, "\x00", STR_PAD_LEFT);
    $ec = new EC('secp256k1');
    return $ec->keyFromPrivate($hex)->getPublic()->encode('hex');
}

function base58check_encode(string $hex): string {
    $bin = hex2bin($hex);
    $sum = substr(hash('sha256', hash('sha256', $bin, true), true), 0, 4);
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $n = gmp_init($hex . bin2hex($sum), 16);
    $res = '';
    while (gmp_cmp($n, 0) > 0) {
        [$n, $r] = gmp_div_qr($n, 58);
        $res = $alphabet[gmp_intval($r)] . $res;
    }
    // leading zero bytes -> '1'
    foreach (str_split($hex) as $pair) {
        // hex pairs; simpler: iterate bytes
    }
    $pad = 0;
    $bytes = hex2bin($hex);
    while (isset($bytes[$pad]) && $bytes[$pad] === "\x00") {
        $pad++;
    }
    return str_repeat($alphabet[0], $pad) . $res;
}

function tronAddressFromMnemonic(string $mnemonic, int $index): array {
    $seed = mnemonic_to_seed($mnemonic);
    $I = hash_hmac('sha512', $seed, 'Bitcoin seed', true);
    $k = gmp_import(substr($I, 0, 32), 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
    $c = substr($I, 32);

    foreach ([44 + 0x80000000, 195 + 0x80000000, 0 + 0x80000000, 0] as $idx) {
        [$k, $c] = ckd_priv($k, $c, $idx);
    }
    [$ki,] = ckd_priv($k, $c, $index);

    $privHex = str_pad(gmp_export($ki, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN), 32, "\x00", STR_PAD_LEFT);
    $pub = privkey_to_pubkey_hex($ki);           // 04||X||Y
    $hash = Keccak::hash(hex2bin(substr($pub, 2)), 256);
    $address = base58check_encode('41' . substr($hash, 24));

    return ['address' => $address, 'privkey' => $privHex];
}
