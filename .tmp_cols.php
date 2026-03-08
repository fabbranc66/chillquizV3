<?php
declare(strict_types=1);
define('BASE_PATH', __DIR__);
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('STORAGE_PATH', BASE_PATH . '/storage');
spl_autoload_register(function ($class) {
  $prefix='App\\';
  if (strpos($class,$prefix)!==0) return;
  $relative=str_replace($prefix,'',$class);
  $file=APP_PATH.'/'.str_replace('\\','/',$relative).'.php';
  if (file_exists($file)) require $file;
});
$pdo = App\Core\Database::getInstance();
$stmt=$pdo->query("SHOW COLUMNS FROM risposte");
foreach($stmt->fetchAll() as $row){echo $row['Field'],"\n";}
