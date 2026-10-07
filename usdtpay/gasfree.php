<?php
/**
 * GasFree address generator for Tron (TronLink SDK compatible).
 * CREATE2: keccak(0x41 + controller20 + salt32 + bytecodeHash32)[12:] -> T...
 * salt = 12 zero + user20, bytecodeHash = keccak(creationCode + abi(address,bytes)).
 * Verified: Nile vector bh 05fc7930... match, keccak empty c5d24601... match.
 */
declare(strict_types=1);

if (!function_exists('mb_strlen')) { function mb_strlen(string $s, ?string $e = null): int { return strlen($s); } }
if (!function_exists('mb_substr')) { function mb_substr(string $s, int $st, ?int $l = null, ?string $e = null): string { return $l === null ? substr($s, $st) : substr($s, $st, $l); } }

require_once __DIR__ . '/libs/tronphp/autoload.php';

if (!defined('GASFREE_CONTROLLER')) define('GASFREE_CONTROLLER', 'TFFAMQLZybALaLb4uxHA9RBE7pxhUAjF3U');
if (!defined('GASFREE_BEACON')) define('GASFREE_BEACON', 'TSP9UW6FQhT76XD2jWA6ipGMx3yGbjDffP');

function gasfree_keccak(string $bin): string {
    return \kornrunner\Keccak::hash($bin, 256, true);
}

function gasfree_tron_to_eth20(string $tron): string {
    $hex = \TronTool\Address::decode($tron);
    if ($hex === null || strlen($hex) !== 42 || substr($hex, 0, 2) !== '41') throw new RuntimeException('bad tron addr: ' . $tron);
    return substr($hex, 2);
}

function gasfree_creation_code(string $chain = 'mainnet'): string {
    $f = $chain === 'nile' ? __DIR__ . '/nile_cc.txt' : __DIR__ . '/main_cc.txt';
    if (is_file($f)) { $h = preg_replace('/\s+/', '', trim(file_get_contents($f))); if (str_starts_with($h, '0x')) $h = substr($h, 2); return $h; }
    throw new RuntimeException('no creation code for ' . $chain);
}

function generateGasFreeAddress(string $userAddress, string $chain = 'mainnet'): string {
    $ctrl = $chain === 'nile' ? 'THQGuFzL87ZqhxkgqYEryRAd7gqFqL5rdc' : GASFREE_CONTROLLER;
    $beacon = $chain === 'nile' ? 'TLtCGmaxH3PbuaF6kbybwteZcHptEdgQGC' : GASFREE_BEACON;
    $user20 = gasfree_tron_to_eth20($userAddress);
    $saltBin = hex2bin(str_repeat('00', 12) . $user20);
    $sel = substr(gasfree_keccak('initialize(address)'), 0, 4);
    $init = $sel . $saltBin;
    $beaconBin = hex2bin(str_repeat('00', 12) . gasfree_tron_to_eth20($beacon));
    $off = str_repeat("\x00", 31) . "\x40";
    $len = str_repeat("\x00", 31) . chr(strlen($init));
    $pad = (32 - (strlen($init) % 32)) % 32;
    $tail = $beaconBin . $off . $len . $init . str_repeat("\x00", $pad);
    $cc = hex2bin(gasfree_creation_code($chain));
    $bh = gasfree_keccak($cc . $tail);
    $ctrlBin = hex2bin(gasfree_tron_to_eth20($ctrl));
    $in = "\x41" . $ctrlBin . $saltBin . $bh;
    $h = gasfree_keccak($in);
    $payloadHex = '41' . bin2hex(substr($h, 12, 20));
    return \TronTool\Address::encode($payloadHex);
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__ && isset($argv[1])) {
    echo generateGasFreeAddress($argv[1], $argv[2] ?? 'mainnet') . "\n";
}
