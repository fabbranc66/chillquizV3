<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Sistema
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function tutteConfigurazioni(): array
    {
        $stmt = $this->pdo->query(
            "SELECT chiave, valore FROM configurazioni_sistema"
        );

        return $stmt->fetchAll();
    }

    public function get(string $chiave): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT valore FROM configurazioni_sistema
             WHERE chiave = :chiave
             LIMIT 1"
        );

        $stmt->execute(['chiave' => $chiave]);
        $result = $stmt->fetch();

        return $result['valore'] ?? null;
    }
}