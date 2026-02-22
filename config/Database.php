<?php

/*
|--------------------------------------------------------------------------
| DATABASE CONFIG LOADER
|--------------------------------------------------------------------------
| Carica il file di configurazione in base all'ambiente attivo
|--------------------------------------------------------------------------
*/

$environmentPath = __DIR__ . '/environment.php';

if (!file_exists($environmentPath)) {
    throw new RuntimeException("File environment.php non trovato.");
}

$environment = require $environmentPath;

$allowed = ['local', 'production', 'lan'];

if (!in_array($environment, $allowed, true)) {
    throw new RuntimeException("Ambiente non valido: {$environment}");
}

$databaseFile = __DIR__ . "/database.{$environment}.php";

if (!file_exists($databaseFile)) {
    throw new RuntimeException("File configurazione database non trovato: {$databaseFile}");
}

$config = require $databaseFile;

if (!is_array($config)) {
    throw new RuntimeException("Configurazione database non valida.");
}

return $config;