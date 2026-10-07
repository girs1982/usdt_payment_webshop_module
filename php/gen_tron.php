<?php
// gen_tron.php
// Simple Tron address generator (pure PHP, no external deps).
// Generates N random private keys or address from specific key.
// Uses GMP for secp256k1 math and Keccak-256 algorithm.

// Curve constants
const P_HEX = 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F';
const Gx_HEX = '79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798';
const Gy_HEX = '483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8';
const ADDR_PREFIX = "\x41"; // mainnet prefix

// Keccak-256 constants and functions (from keccak.php)
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
        $B = array_fill(0,5,array_fill(0,5,0));
        for ($x = 0; $x < 5; $x++) {
            for ($y = 0; $y < 5; $y++) {
                $B[$y][(2*$x+3*$y)%5] = keccak_rotl($A[$x][$y], KECCAK_R[$x][$y]);
            }
        }
        for ($x = 0; $x < 5; $x++) {
            for ($y = 0; $y < 5; $y++) {
                $A[$x][$y] = $B[$x][$y] ^ ((~$B[($x+1)%5][$y]) & $B[($x+2)%5][$y]);
            }
        }
        $A[0][0] ^= KECCAK_RC[$round];
    }
}
function keccak_rotl(int $x, int $n): int
{
    if ($n === 0) return $x;
    $mask = PHP_INT_MAX;
    return (($x << $n) & $mask) | (($x & $mask) >> (64 - $n));
}
function keccak256(string $msg): string
{
    $rate = 136; // 1088 bits
    $A = array_fill(0,5,array_fill(0,5,0));
    $len = strlen($msg);
    $padLen = $rate - ($len % $rate);
    if ($padLen === 1) {
        $msg .= "\x81";
    } else {
        $msg .= "\x01" . str_repeat("\0", $padLen-2) . "\x80";
    }
    for ($off=0,$n=strlen($msg);$off<$n;$off+=$rate) {
        for ($i=0;$i<$rate/8;$i++) {
            $lane=0;
            for ($b=0;$b<8;$b++) {
                $lane |= ord($msg[$off+$i*8+$b]) << ($b*8);
            }
            $A[$i%5][intdiv($i,5)] ^= $lane;
        }
        keccak_f1600($A);
    }
    $out='';
    for ($i=0;$i<4;$i++) {
        $lane=$A[$i%5][intdiv($i,5)];
        for ($b=0;$b<8;$b++) $out .= chr(($lane >> ($b*8)) & 0xFF);
    }
    return $out;
}

function base58_check(string $data): string
{
    $hash = hash('sha256',$data,true);
    $hash = hash('sha256',$hash,true);
    $bin = $data . substr($hash,0,4);
    return base58_encode($bin);
}
function base58_encode(string $data): string{
    $AL='123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $n=gmp_init('0');
    foreach (str_split($data) as $byte){$n=gmp_mul($n,256);$n=gmp_add($n,ord($byte));}
    $out='';
    while(gmp_cmp($n,0)>0){[$n,$r]=gmp_div_qr($n,58);$out=$AL[gmp_intval($r)].$out;}
    $pad=0;for($i=0;$i<strlen($data);$i++){if($data[$i]!=="\x00")break;$pad++;}
    return str_repeat($AL[0],$pad).$out;
}

// EC point math
function ec_double(array $pt): array
{
    [$x,$y] = $pt;
    if ($y === null || gmp_cmp($y,0)===0) return [null,null];
    $P = gmp_init(P_HEX,16);
    $lam = gmp_mod(gmp_mul(gmp_init(3), gmp_pow($x,2)), $P);
    $lam = gmp_mul($lam, gmp_invert(gmp_mul(2,$y),$P));
    $lam = gmp_mod($lam,$P);
    $x3 = gmp_mod(gmp_sub(gmp_sub(gmp_pow($lam,2),$x),$x),$P); // lam^2 - 2*x
    $y3 = gmp_mod(gmp_sub(gmp_mul($lam,gmp_sub($x,$x3)),$y),$P);
    return [$x3,$y3];
}
function ec_add(array $pt1, array $pt2): array
{
    [$x1,$y1] = $pt1;
    [$x2,$y2] = $pt2;
    if ($x1 === null) return [$x2,$y2];
    if ($x2 === null) return [$x1,$y1];
    $P = gmp_init(P_HEX,16);
    if (gmp_cmp($x1,$x2)===0){
        if (gmp_cmp($y1,$y2)===0) return ec_double($pt1);
        return [null,null];
    }
    $lam = gmp_mod(gmp_mul(gmp_sub($y2,$y1), gmp_invert(gmp_sub($x2,$x1),$P)),$P);
    $x3 = gmp_mod(gmp_sub(gmp_sub(gmp_pow($lam,2),$x1),$x2),$P);
    $y3 = gmp_mod(gmp_sub(gmp_mul($lam,gmp_sub($x1,$x3)),$y1),$P);
    return [$x3,$y3];
}
function ec_mul(string $privHex): array
{
    $k = gmp_init($privHex,16);
    $x = null; $y = null;
    $Gx = gmp_init(Gx_HEX,16);
    $Gy = gmp_init(Gy_HEX,16);
    while (gmp_cmp($k,0)>0) {
        if (gmp_testbit($k,0)) { [$x,$y] = ec_add([$x,$y],[ $Gx,$Gy]); }
        [$Gx,$Gy] = ec_double([$Gx,$Gy]);
        $k = gmp_div_q($k,2);
    }
    return [$x,$y];
}

function address_from_private(string $privHex): string
{
    [$x,$y] = ec_mul($privHex);
    if ($x === null) throw new RuntimeException('invalid key');
    $xHex = str_pad(gmp_strval($x,16),64,'0',STR_PAD_LEFT);
    $yHex = str_pad(gmp_strval($y,16),64,'0',STR_PAD_LEFT);
    $pub = hex2bin($xHex.$yHex);
    $hash = keccak256($pub);
    $payload = ADDR_PREFIX . substr($hash,-20);
    return base58_check($payload);
}

// CLI interface
$options = getopt('k:', ['count:']);
if (isset($options['k'])) {
    $key = strtolower($options['k']);
    echo address_from_private($key)."\n";
    exit(0);
}
$count = (int) ($options['count'] ?? $argv[1] ?? 1);
echo "ADDRESS                           :                                                         PRIVATE\n";
for ($i=0;$i<$count;$i++) {
    $priv = bin2hex(random_bytes(32));
    $addr = address_from_private($priv);
    echo "$addr:$priv\n";
}
?>