<?php

/*
|--------------------------------------------------------------------------
| CHILLQUIZ - BOOTSTRAP
|--------------------------------------------------------------------------
*/

declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

if (!function_exists('chillquiz_public_base_url')) {
    function chillquiz_public_base_url(): string
    {
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/public/index.php'));
        $dir = str_replace('\\', '/', dirname($scriptName));
        if ($dir === '.' || $dir === '\\' || $dir === '') {
            $dir = '/';
        }
        $dir = rtrim($dir, '/');
        return $dir === '' ? '/' : ($dir . '/');
    }
}

if (!function_exists('chillquiz_public_url')) {
    function chillquiz_public_url(string $path = ''): string
    {
        $base = chillquiz_public_base_url();
        $clean = ltrim($path, '/');
        return $clean === '' ? $base : ($base . $clean);
    }
}

if (!function_exists('chillquiz_api_base_url')) {
    function chillquiz_api_base_url(): string
    {
        return chillquiz_public_url('index.php?url=api');
    }
}

if (!function_exists('chillquiz_asset_url')) {
    function chillquiz_asset_url(string $relativePublicPath): string
    {
        $clean = ltrim($relativePublicPath, '/');
        $file = BASE_PATH . '/public/' . str_replace('/', DIRECTORY_SEPARATOR, $clean);
        $version = is_file($file) ? (string) filemtime($file) : (string) time();
        return chillquiz_public_url($clean) . '?v=' . rawurlencode($version);
    }
}

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
        echo '<pre>';
        echo $e->getMessage();
        echo "\n\n";
        echo $e->getTraceAsString();
        echo '</pre>';
    } else {
        echo 'Errore interno.';
    }
}
