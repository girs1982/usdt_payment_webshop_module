<?php
require '/var/www/html/libs/tronphp/autoload.php';
$keys = [
 'T9yD14Nj9j7xAB4dbGeiX9h8unkKHxuWwb'=>'b9e43e22be663df8d31a641a058ca4001c93dfc19fdc516a03950ef906afec2c',
 'TUDcMBSeMPyuCbGnJNNXTtAyJ1TGtMKcYt'=>'eeb49d6c0c43e7ca66ea4cd5bdaab021883ad438432fdf7d4aa99c3397e8a3e4',
 'TQ3pJ4TaEVFMZjVZ3QXmXf92gcQJaSbpRc'=>'54b44edc53a85f5c00b319fb92d7197529c658bf9b910bd6140fb2a0df54bb09',
 'TCbZL4LgyBj6UrYhWrno789Y4gKZSLE4Ke'=>'e1e483b036ddb1cc6e20f68eff59dea8f1d98357f47d7b65e1ca0b8fe6d73214',
 'TGp6iTofJqtUtqNcBVgr29aw2BTR8hVUss'=>'eb3a6deee10178a7f15ac86618ab7456bdcb8e2f16e7ef62929ceb62ddad8926',
 'TYkCbcSisKGgndqAyZ7dpFgF5WG4GWfttg'=>'9ad5a1268a84973cff746bfb91f856d9442ea96a67a59683be1ad6beb3bee93d',
];
foreach ($keys as $addr=>$k) {
  $c = TronTool\Credential::fromPrivateKey($k);
  echo $addr.' -> derived '.$c->address()->base58()."\n";
}
