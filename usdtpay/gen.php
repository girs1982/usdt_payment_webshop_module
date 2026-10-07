<?php
// usdtpay/gen.php — Tron (TRC20) address generator, matches Rust trc20-adrrgen
// Usage: php gen.php [count]           -> random keys
//        php gen.php -k <hexpriv>      -> address for given private key

const ADDR_PREFIX = "\x41";

function b58check(string $data): string {
    $h = hash('sha256', $data, true);
    $h = hash('sha256', $h, true);
    return base58_encode($data . substr($h, 0, 4));
}

function base58_encode(string $data): string {
    $AL = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $n = gmp_init('0');
    foreach (str_split($data) as $byte) {
        $n = gmp_mul($n, 256);
        $n = gmp_add($n, ord($byte));
    }
    $out = '';
    while (gmp_cmp($n, 0) > 0) {
        [$n, $r] = gmp_div_qr($n, 58);
        $out = $AL[gmp_intval($r)] . $out;
    }
    $pad = 0;
    for ($i = 0; $i < strlen($data); $i++) {
        if ($data[$i] !== "\x00") break;
        $pad++;
    }
    return str_repeat($AL[0], $pad) . $out;
}

function priv_to_pub_uncompressed(string $priv32): string {
    // openssl needs DER/PEM; build PEM from raw 32-byte scalar
    $hex = bin2hex($priv32);
    $der = gen_der($hex);
    $pem = "-----BEGIN EC PRIVATE KEY-----\n"
         . chunk_split(base64_encode($der), 64, "\n")
         . "-----END EC PRIVATE KEY-----\n";
    $key = openssl_pkey_get_private($pem);
    $details = openssl_pkey_get_details($key);
    // pub key: 04 || X(32) || Y(32)
    $pub = $details['ec']['pub'];  // 65 bytes incl 0x04
    if ($pub[0] !== "\x04") {
        throw new RuntimeException('unexpected pubkey format');
    }
    return substr($pub, 1); // 64 bytes X||Y
}

function gen_der(string $priv_hex): string {
    // minimal DER ECPrivateKey, secp256k1 OID, uncompressed public key
    $priv = hex2bin($priv_hex);
    $oid = hex2bin('06052b8104000a');                    // OID secp256k1
    $pub65 = "\x04" . pub_from_priv($priv);             // 65 bytes
    $params = "\xa0" . der_len(strlen($oid)) . $oid;
    $pubbit = "\x03" . der_len(strlen($pub65)) . $pub65;
    $body = "\x02\x01\x01"                                        // version 1
          . "\x02" . der_len(strlen($priv)) . $priv
          . $params
          . $pubbit;
    return "\x30" . der_len(strlen($body)) . $body;
}

function der_len(int $len): string {
    return $len < 0x80 ? chr($len) : chr(0x81) . chr($len);
}

// ---- pure PHP secp256k1 scalar->point (only for DER construction) ----
function pub_from_priv(string $priv): string {
    $Gx = gmp_init('79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798', 16);
    $Gy = gmp_init('483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8', 16);
    $P  = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F', 16);
    $k = gmp_init(bin2hex($priv), 16);
    $x = $Gx; $y = $Gy;
    $Rx = gmp_init(0); $Ry = gmp_init(0);
    $bits = gmp_strval($k, 2);
    $bits = str_pad($bits, 256, '0', STR_PAD_LEFT);
    $first = true;
    for ($i = 0; $i < 256; $i++) {
        if ($bits[$i] === '0') continue;
        if ($first) { $Rx = $x; $Ry = $y; $first = false; continue; }
        // point addition (P + Q) where P=(x,y) generator, Q=(Rx,Ry)
        $lam = gmp_mul(gmp_sub($y, $Ry), gmp_invert(gmp_sub($x, $Rx), $P));
        $lam = gmp_mod($lam, $P);
        $x3 = gmp_mod(gmp_sub(gmp_sub(gmp_pow($lam, 2), $x), $Rx), $P);
        $y3 = gmp_mod(gmp_sub(gmp_mul($lam, gmp_sub($x, $x3)), $y), $P);
        $Rx = $x3; $Ry = $y3;
    }
    return str_pad(gmp_strval($Rx, 16), 64, '0', STR_PAD_LEFT)
         . str_pad(gmp_strval($Ry, 16), 64, '0', STR_PAD_LEFT);
}

function address_from_priv(string $priv_hex): string {
    $priv = hex2bin($priv_hex);
    if (strlen($priv) !== 32) throw new InvalidArgumentException('priv must be 32 bytes');
    $pub64 = pub_from_priv($priv);              // X||Y (64 bytes)
    $hash = hash('sha3-256', $pub64, true);     // keccak256
    $payload = ADDR_PREFIX . substr($hash, -20);
    return b58check($payload);
}

// ---------------- main ----------------
$opts = getopt('k:', ['count:']);
if (isset($opts['k'])) {
    $k = $opts['k'];
    if (strlen($k) === 64) $k = hex2bin($k);
    printf("%s:%s\n", address_from_priv(bin2hex($k)), bin2hex($k));
    exit(0);
}
$count = (int)($opts['count'] ?? $argv[1] ?? 1);
echo "ADDRESS                           :                                                         PRIVATE\n";
for ($i = 0; $i < $count; $i++) {
    $priv = random_bytes(32);
    printf("%s:%s\n", address_from_priv(bin2hex($priv)), bin2hex($priv));
}
