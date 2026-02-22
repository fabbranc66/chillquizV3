<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Utente
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function creaTemporaneo(string $nome): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO utenti (nome, tipo, creato_il, attivo)
             VALUES (:nome, 'temporaneo', :creato_il, 1)"
        );

        $stmt->execute([
            'nome' => $nome,
            'creato_il' => time(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function creaOPrelevaPermanente(string $nome, string $token): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM utenti WHERE token = :token LIMIT 1"
        );

        $stmt->execute(['token' => $token]);
        $utente = $stmt->fetch();

        if ($utente) {
            $update = $this->pdo->prepare(
                "UPDATE utenti
                 SET ultimo_accesso = :ultimo_accesso
                 WHERE id = :id"
            );

            $update->execute([
                'ultimo_accesso' => time(),
                'id' => $utente['id']
            ]);

            return (int) $utente['id'];
        }

        $insert = $this->pdo->prepare(
            "INSERT INTO utenti
             (nome, token, tipo, creato_il, ultimo_accesso, attivo)
             VALUES (:nome, :token, 'permanente', :creato_il, :ultimo_accesso, 1)"
        );

        $insert->execute([
            'nome' => $nome,
            'token' => $token,
            'creato_il' => time(),
            'ultimo_accesso' => time(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function trovaPerId(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM utenti WHERE id = :id LIMIT 1"
        );

        $stmt->execute(['id' => $id]);
        $utente = $stmt->fetch();

        return $utente ?: null;
    }
}