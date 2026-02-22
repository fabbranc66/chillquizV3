<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Partecipazione
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function entra(int $sessioneId, int $utenteId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM partecipazioni
             WHERE sessione_id = :sessione_id
             AND utente_id = :utente_id
             LIMIT 1"
        );

        $stmt->execute([
            'sessione_id' => $sessioneId,
            'utente_id' => $utenteId
        ]);

        $esiste = $stmt->fetch();

        if ($esiste) {
            return (int) $esiste['id'];
        }

        $sistema = new Sistema();
        $capitale = (int) $sistema->get('capitale_iniziale');

        $insert = $this->pdo->prepare(
            "INSERT INTO partecipazioni
             (sessione_id, utente_id, capitale_attuale, entrato_il)
             VALUES
             (:sessione_id, :utente_id, :capitale, :entrato_il)"
        );

        $insert->execute([
            'sessione_id' => $sessioneId,
            'utente_id' => $utenteId,
            'capitale' => $capitale,
            'entrato_il' => time(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function trova(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM partecipazioni
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function classifica(int $sessioneId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.nome,
                    p.capitale_attuale
             FROM partecipazioni p
             JOIN utenti u ON u.id = p.utente_id
             WHERE p.sessione_id = :sessione_id
             ORDER BY p.capitale_attuale DESC"
        );

        $stmt->execute(['sessione_id' => $sessioneId]);

        return $stmt->fetchAll();
    }
}