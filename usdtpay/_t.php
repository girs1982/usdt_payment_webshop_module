<?php
require '/var/www/html/config.php';
require '/var/www/html/get_address_from_pool.php';
$db = getDBConnection();
$row = getAddressFromPool();
var_dump($row);
