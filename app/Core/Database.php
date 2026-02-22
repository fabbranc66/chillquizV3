<?php

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {

            // Carica configurazione multi-ambiente
            $configPath = BASE_PATH . '/config/database.php';

            if (!file_exists($configPath)) {
                throw new RuntimeException("File config database non trovato: {$configPath}");
            }

            $config = require $configPath;

            if (!is_array($config)) {
                throw new RuntimeException("Configurazione database non valida.");
            }

            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                $config['host'],
                $config['dbname'],
                $config['charset']
            );

            try {

                self::$instance = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );

            } catch (PDOException $e) {

                throw new PDOException(
                    "Errore connessione DB: " . $e->getMessage(),
                    (int)$e->getCode()
                );
            }
        }

        return self::$instance;
    }
}