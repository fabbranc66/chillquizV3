<?php

/*
|--------------------------------------------------------------------------
| CHILLQUIZ V3 — BOOTSTRAP
|--------------------------------------------------------------------------
| Entry point applicazione
|--------------------------------------------------------------------------
*/

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 1️⃣ Costanti base
|--------------------------------------------------------------------------
*/

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('STORAGE_PATH', BASE_PATH . '/storage');

/*
|--------------------------------------------------------------------------
| 2️⃣ Autoload semplice PSR-4 style
|--------------------------------------------------------------------------
*/

spl_autoload_register(function ($class) {

    // Namespace base
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
| 3️⃣ Caricamento configurazione
|--------------------------------------------------------------------------
*/

$config = require CONFIG_PATH . '/app.php';

/*
|--------------------------------------------------------------------------
| 4️⃣ Gestione errori base
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
| 5️⃣ Avvio applicazione
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