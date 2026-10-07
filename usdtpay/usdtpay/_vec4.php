<?php
require __DIR__ . '/libs/tronphp/autoload.php';
use kornrunner\Keccak;
$cases = [
  ''      => 'c5d2460186f7233c927e7db2dcc703c0e500b653ca82273b7bfad8045d85a470',
  'abc'   => '4e03657aea45a94fc7d47ba826c8d667c0d1e6e33a64a036ec44f58fa12d6c45',
  'testing' => '5b16a3c9ecdd19e5a3f5e4c4f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2',
];
foreach ($cases as $in => $exp) {
  $h = Keccak::hash($in, 256);
  echo ($h === $exp ? 'OK  ' : 'BAD ') . strlen($in) . " $h (exp $exp)\n";
}
