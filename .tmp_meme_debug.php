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
use App\Controllers\ApiController;
use App\Services\SessioneService;
use App\Services\Question\MemeModeService;

$sessione = (new Sessione())->corrente();
$sessioneId = (int)($sessione['id'] ?? 0);
$api = new ApiController();
$ref = new ReflectionClass($api);
$m = $ref->getMethod('loadCurrentQuestionForSession');
$m->setAccessible(true);
$current = $m->invoke($api, $sessioneId);
$domandaId = (int)($current['id'] ?? 0);
$svc = new MemeModeService();
$svc->setEnabledForQuestion($sessioneId, $domandaId, true, 'TEST MEME');
$before = $svc->getRuntimeState($sessioneId);
$sessionSvc = new SessioneService($sessioneId);
$sessionSvc->avviaDomanda();
$after = $svc->getRuntimeState($sessioneId);
ob_start();
(new ApiController())->stato($sessioneId);
$stato = ob_get_clean();
$stateFile = STORAGE_PATH . '/runtime/meme/session_' . $sessioneId . '_current.json';
var_export([
  'before' => $before,
  'after' => $after,
  'file_exists' => is_file($stateFile),
  'stato_json' => $stato,
]);
