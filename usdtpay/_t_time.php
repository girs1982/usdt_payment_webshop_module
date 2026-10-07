<?php
require __DIR__.'/config.php';
$t0=microtime(true); $n=0;
for($i=0;$i<10;$i++){
  $a='41b56626609ec7a93d8fdebd4234ce2d8b188040bc';
  $ch=curl_init('https://api.trongrid.io/wallet/getaccount');
  curl_setopt_array($ch,[CURLOPT_POST=>1,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json','TRON-PRO-API-KEY: '.TRON_API_KEY],
    CURLOPT_POSTFIELDS=>json_encode(['address'=>$a,'visible'=>false])]);
  $r=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if($code!==200) echo "call $i http=$code ".substr($r,0,80)."\n";
  $n++;
}
printf("%d calls in %.1fs\n",$n,microtime(true)-$t0);
