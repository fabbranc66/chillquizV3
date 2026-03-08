<?php
declare(strict_types=1);
define('BASE_PATH', __DIR__);
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('STORAGE_PATH', BASE_PATH . '/storage');
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) return;
    $relative = str_replace($prefix, '', $class);
    $file = APP_PATH . '/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require $file;
});
use App\Models\Sessione;
use App\Services\SessioneService;
use App\Controllers\ApiController;
$sessione=(new Sessione())->corrente();
$id=(int)($sessione['id']??0);
$svc=new SessioneService($id);
$svc->resetTotale();
$svc=new SessioneService($id);
$svc->avviaPuntata();
$api=new ApiController();
ob_start();
$api->stato($id);
$out=ob_get_clean();
echo $out, PHP_EOL;
