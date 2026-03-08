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
use App\Core\Database;
$pdo = Database::getInstance();
$stmt = $pdo->prepare("SELECT d.id, d.codice_domanda, d.tipo_domanda, d.testo FROM sessioni s JOIN sessione_domande sd ON sd.sessione_id=s.id AND sd.posizione=s.domanda_corrente JOIN domande d ON d.id=sd.domanda_id WHERE s.id=1 LIMIT 1");
$stmt->execute();
var_export($stmt->fetch());
