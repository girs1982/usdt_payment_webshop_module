<?php
/**
 * Runnable check: every key in the addresses table must derive its own address.
 * Non-zero exit = the pool contains unusable keys. Run: php tools/verify_pool.php
 */
require_once __DIR__ . '/../config.php';

$ALPHA = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

function b58encode(string $bin): string {
    global $ALPHA;
    $n = gmp_init(bin2hex($bin), 16);
    $out = '';
    while (gmp_cmp($n, 0) > 0) {
        [$n, $r] = gmp_div_qr($n, 58);
        $out = $ALPHA[gmp_intval($r)] . $out;
    }
    for ($i = 0; isset($bin[$i]) && $bin[$i] === "\x00"; $i++) { $out = $ALPHA[0] . $out; }
    return $out;
}

function keccak256(string $data): string {
    if (in_array('keccak256', hash_algos(), true)) return hash('keccak256', $data, true);
    require_once __DIR__ . '/../libs/tronphp/autoload.php';
    return hex2bin(kornrunner\Keccak::hash($data, 256));
}

/** secp256k1 public key (uncompressed, 64 bytes X||Y) from a private key */
function pub64(string $privHex): string {
    $p  = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F', 16);
    $gx = gmp_init('79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798', 16);
    $gy = gmp_init('483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8', 16);

    $add = function ($P1, $P2) use ($p) {
        if ($P1 === null) return $P2;
        if ($P2 === null) return $P1;
        [$x1, $y1] = $P1; [$x2, $y2] = $P2;
        if (gmp_cmp($x1, $x2) === 0) {
            if (gmp_cmp($y1, $y2) !== 0) return null;
            $lam = gmp_mul(gmp_mul(gmp_init(3), gmp_powm($x1, 2, $p)), gmp_invert(gmp_mul(gmp_init(2), $y1), $p));
        } else {
            $lam = gmp_mul(gmp_sub($y2, $y1), gmp_invert(gmp_sub($x2, $x1), $p));
        }
        $lam = gmp_mod($lam, $p);
        $x3  = gmp_mod(gmp_sub(gmp_sub(gmp_powm($lam, 2, $p), $x1), $x2), $p);
        $y3  = gmp_mod(gmp_sub(gmp_mul($lam, gmp_sub($x1, $x3)), $y1), $p);
        return [$x3, $y3];
    };

    $R = null; $Q = [$gx, $gy]; $k = gmp_init($privHex, 16);
    while (gmp_cmp($k, 0) > 0) {
        if (gmp_intval(gmp_mod($k, 2)) === 1) $R = $add($R, $Q);
        $Q = $add($Q, $Q);
        $k = gmp_div_q($k, 2);
    }
    return str_pad(gmp_strval($R[0], 16), 64, '0', STR_PAD_LEFT)
         . str_pad(gmp_strval($R[1], 16), 64, '0', STR_PAD_LEFT);
}

function address_from_priv(string $privHex): string {
    // TRON hashes the raw 64-byte X||Y — NOT the 65-byte 0x04-prefixed form.
    $hash    = keccak256(hex2bin(pub64($privHex)));
    $payload = "\x41" . substr($hash, 12, 20);
    return b58encode($payload . substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4));
}

// Known-answer vector: priv=1 must give the canonical address of pubkey G.
$vector = address_from_priv(str_pad('1', 64, '0', STR_PAD_LEFT));
if ($vector !== 'TMVQGm1qAQYVdetCeGRRkTWYYrLXuHK2HC') {
    fwrite(STDERR, "FAIL: KAT priv=1 -> $vector (expected TMVQGm1qAQYVdetCeGRRkTWYYrLXuHK2HC)\n");
    exit(1);
}
echo "KAT ok: priv=1 -> $vector\n";

$db   = getDBConnection();
$rows = $db->query('SELECT id, address, privkey FROM addresses ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$bad  = 0;

foreach ($rows as $r) {
    $calc = address_from_priv($r['privkey']);
    if ($calc !== $r['address']) {
        $bad++;
        echo "MISMATCH id={$r['id']} db={$r['address']} calc=$calc\n";
    }
}

printf("%d rows checked, %d mismatches\n", count($rows), $bad);
exit($bad === 0 ? 0 : 1);