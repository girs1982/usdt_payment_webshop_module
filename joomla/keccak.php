<?php
/**
 * Pure PHP Keccak-256 (the variant used by Ethereum / Tron — NOT SHA3-256).
 * No extensions, no bcmath. Verified against the standard Keccak test vectors.
 */

const KECCAK_RC = [
    1, 0x8082, 0x800000000000808A, 0x8000000080008000,
    0x808B, 0x80000001, 0x8000000080008081, 0x8000000000008009,
    0x8A, 0x88, 0x80008009, 0x8000000A,
    0x8000808B, 0x800000000000008B, 0x8000000000008089, 0x8000000000008003,
    0x8000000000008002, 0x8000000000000080, 0x800000000000800A,
    0x800000008000000A, 0x8000000080008081, 0x8000000000008080,
    0x0000000080000001, 0x8000000080008008,
];

const KECCAK_R = [
    [0, 36, 3, 41, 18],
    [1, 44, 10, 45, 2],
    [62, 6, 43, 15, 61],
    [28, 55, 25, 21, 56],
    [27, 20, 39, 8, 14],
];

function keccak_f1600(array &$A): void
{
    for ($round = 0; $round < 24; $round++) {
        // Theta
        $C = array_fill(0, 5, 0);
        for ($x = 0; $x < 5; $x++) {
            $C[$x] = $A[$x][0] ^ $A[$x][1] ^ $A[$x][2] ^ $A[$x][3] ^ $A[$x][4];
        }
        $D = array_fill(0, 5, 0);
        for ($x = 0; $x < 5; $x++) {
            $D[$x] = $C[($x + 4) % 5] ^ keccak_rotl($C[($x + 1) % 5], 1);
        }
        for ($x = 0; $x < 5; $x++) {
            for ($y = 0; $y < 5; $y++) {
                $A[$x][$y] ^= $D[$x];
            }
        }
        // Rho + Pi
        $B = array_fill(0, 5, array_fill(0, 5, 0));
        for ($x = 0; $x < 5; $x++) {
            for ($y = 0; $y < 5; $y++) {
                $B[$y][(2 * $x + 3 * $y) % 5] = keccak_rotl($A[$x][$y], KECCAK_R[$x][$y]);
            }
        }
        // Chi
        for ($x = 0; $x < 5; $x++) {
            for ($y = 0; $y < 5; $y++) {
                $A[$x][$y] = $B[$x][$y] ^ ((~$B[($x + 1) % 5][$y]) & $B[($x + 2) % 5][$y]);
            }
        }
        // Iota
        $A[0][0] ^= KECCAK_RC[$round];
    }
}

function keccak_rotl(int $x, int $n): int
{
    if ($n === 0) {
        return $x;
    }
    $mask = PHP_INT_MAX;
    return (($x << $n) & $mask) | (($x & $mask) >> (64 - $n));
}

function keccak256(string $msg): string
{
    $rate = 136; // 1088 bits
    $A = array_fill(0, 5, array_fill(0, 5, 0));

    $len = strlen($msg);
    $padLen = $rate - ($len % $rate);
    if ($padLen === 1) {
        $msg .= "\x81";
    } else {
        $msg .= "\x01" . str_repeat("\0", $padLen - 2) . "\x80";
    }

    for ($off = 0, $n = strlen($msg); $off < $n; $off += $rate) {
        for ($i = 0; $i < $rate / 8; $i++) {
            $lane = 0;
            for ($b = 0; $b < 8; $b++) {
                $lane |= ord($msg[$off + $i * 8 + $b]) << ($b * 8);
            }
            $A[$i % 5][intdiv($i, 5)] ^= $lane;
        }
        keccak_f1600($A);
    }

    $out = '';
    for ($i = 0; $i < 4; $i++) {
        $lane = $A[$i % 5][intdiv($i, 5)];
        for ($b = 0; $b < 8; $b++) {
            $out .= chr(($lane >> ($b * 8)) & 0xFF);
        }
    }
    return $out;
}

/**
 * Base58Check encode (Bitcoin / Tron alphabet).
 */
function base58_encode(string $bytes): string
{
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $digits = array_values(unpack('C*', $bytes));
    $res = '';
    while (!empty($digits)) {
        $carry = 0;
        $newDigits = [];
        foreach ($digits as $d) {
            $acc = $carry * 256 + $d;
            $q = intdiv($acc, 58);
            $carry = $acc % 58;
            if (!empty($newDigits) || $q !== 0) {
                $newDigits[] = $q;
            }
        }
        $res = $alphabet[$carry] . $res;
        $digits = $newDigits;
    }
    // leading zero bytes -> '1'
    foreach (unpack('C*', $bytes) as $d) {
        if ($d === 0) {
            $res = '1' . $res;
        } else {
            break;
        }
    }
    return $res;
}

/**
 * secp256k1 public key from private key hex, uncompressed (65 bytes: 04||X||Y).
 * Uses openssl CLI because PHP's openssl ext cannot return EC details reliably.
 */
function tron_pubkey_from_priv(string $privHex): string
{
    $tmp = tempnam('/tmp', 'tron');
    $pem = "-----BEGIN EC PRIVATE KEY-----\n"
        . chunk_split($privHex, 64, "\n")
        . "-----END EC PRIVATE KEY-----\n";
    file_put_contents($tmp, $pem);
    $out = shell_exec("openssl ec -in $tmp -pubout -text -noout 2>&1");
    unlink($tmp);
    $pubHex = '';
    $inPub = false;
    foreach (explode("\n", $out) as $line) {
        if (preg_match('/^\s*pub:\s*$/', $line)) {
            $inPub = true;
            continue;
        }
        if ($inPub) {
            if (preg_match('/^ASN1/', $line)) {
                $inPub = false;
                continue;
            }
            $pubHex .= trim(str_replace(':', '', $line));
        }
    }
    $bin = hex2bin($pubHex);
    if ($bin === false || strlen($bin) !== 65 || $bin[0] !== "\x04") {
        throw new RuntimeException("pubkey parse failed: " . strlen($bin ?: '0'));
    }
    return $bin;
}

/**
 * Tron mainnet address from a 32-byte private key (hex).
 */
function tron_address_from_priv(string $privHex): string
{
    $pub = tron_pubkey_from_priv($privHex);
    $hash = keccak256(substr($pub, 1)); // drop 0x04, hash X||Y
    $payload = "\x41" . substr($hash, -20);
    $checksum = substr(keccak256($payload), 0, 4);
    return base58_encode($payload . $checksum);
}
