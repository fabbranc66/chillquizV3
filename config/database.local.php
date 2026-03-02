<?php

$localConfig = [
    'host' => '127.0.0.1',
    'dbname' => 'Sql1874742_4',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
];

$hostingArubaConfig = [
    'host' => '31.11.39.231',
    'dbname' => 'Sql1874742_4',
    'username' => 'Sql1874742',
    'password' => '@GenniH264rgnm',
    'charset' => 'utf8mb4',
];

$forceHosting = getenv('DB_FORCE_HOSTING') === '1';

if ($forceHosting) {
    return $hostingArubaConfig;
}

$localDbReachable = false;
$connection = @fsockopen($localConfig['host'], 3306, $errno, $errstr, 1.0);

if (is_resource($connection)) {
    $localDbReachable = true;
    fclose($connection);
}

return $localDbReachable ? $localConfig : $hostingArubaConfig;