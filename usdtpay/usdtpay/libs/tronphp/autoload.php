<?php
/**
 * Minimal PSR-4 autoloader for the vendored Tron PHP libs.
 * ponytail: no Composer inside the Docker image; maps 3 namespaces to dirs.
 */
spl_autoload_register(function (string $class): void {
    $map = [
        'kornrunner\\'      => __DIR__ . '/kornrunner/keccak/',
        'StephenHill\\'     => __DIR__ . '/stephenhill/base58/',
        'Elliptic\\'           => __DIR__ . '/elliptic-php/lib/',
        'TronTool\\'           => __DIR__ . '/TronTool/',
        'BN\\'                 => __DIR__ . '/bn/',
        'BI\\'                 => __DIR__ . '/bi/',
    ];
    foreach ($map as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $rel = substr($class, strlen($prefix));
            // PSR-4: trailing segment is <Dir>/<...>/src/<Class>.php here
            $parts = explode('\\', $rel);
            $file = $base . implode('/', $parts) . '/src/' . end($parts) . '.php';
            if (is_file($file)) { require $file; return; }
            $file2 = $base . implode('/', $parts) . '.php';
            if (is_file($file2)) { require $file2; return; }
            $file3 = $base . 'src/' . implode('/', $parts) . '.php';
            if (is_file($file3)) { require $file3; return; }
            // case-insensitive fallback (vendored dirs are lowercase)
            $lower = strtolower(implode('/', $parts));
            foreach ([
                $base . $lower . '/src/' . strtolower(end($parts)) . '.php',
                $base . $lower . '.php',
                $base . 'src/' . $lower . '.php',
            ] as $c) {                if (is_file($c)) { require $c; return; }
            }
        }
    }
});
