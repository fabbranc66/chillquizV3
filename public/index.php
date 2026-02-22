<?php

/*
|--------------------------------------------------------------------------
| CHILLQUIZ V3 — BOOTSTRAP
|--------------------------------------------------------------------------
*/

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SESSION START (necessario per puntate)
|--------------------------------------------------------------------------
*/

session_start();

/*
|--------------------------------------------------------------------------
| Costanti base
|--------------------------------------------------------------------------
*/

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('STORAGE_PATH', BASE_PATH . '/storage');

/*
|--------------------------------------------------------------------------
| Autoload PSR-4 semplice
|--------------------------------------------------------------------------
*/

spl_autoload_register(function ($class) {

    $prefix = 'App\\';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = str_replace($prefix, '', $class);
    $file = APP_PATH . '/' . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

/*
|--------------------------------------------------------------------------
| Config applicazione
|--------------------------------------------------------------------------
*/

$config = require CONFIG_PATH . '/app.php';

/*
|--------------------------------------------------------------------------
| Error handling
|--------------------------------------------------------------------------
*/

if (!empty($config['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

/*
|--------------------------------------------------------------------------
| Avvio Router
|--------------------------------------------------------------------------
*/

use App\Core\Router;

try {

    $router = new Router();
    $router->dispatch();

} catch (Throwable $e) {

    http_response_code(500);

    if (!empty($config['debug'])) {
        echo "<pre>";
        echo $e->getMessage();
        echo "\n\n";
        echo $e->getTraceAsString();
        echo "</pre>";
    } else {
        echo "Errore interno.";
    }
}