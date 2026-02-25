<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ScreenMedia
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function lista(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, titolo, file_path, attiva, creato_il
             FROM screen_media
             ORDER BY id DESC"
        );

        return $stmt->fetchAll();
    }

    public function crea(string $titolo, string $filePath): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO screen_media (titolo, file_path, attiva)
             VALUES (:titolo, :file_path, 0)"
        );

        $stmt->execute([
            'titolo' => $titolo,
            'file_path' => $filePath,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function impostaAttiva(int $id, bool $attiva): bool
    {
        $existsStmt = $this->pdo->prepare(
            "SELECT id
             FROM screen_media
             WHERE id = :id
             LIMIT 1"
        );
        $existsStmt->execute(['id' => $id]);

        if (!$existsStmt->fetch()) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE screen_media
             SET attiva = :attiva
             WHERE id = :id"
        );

        $stmt->execute([
            'attiva' => $attiva ? 1 : 0,
            'id' => $id,
        ]);

        return true;
    }

    public function disattivaTutte(): bool
    {
        $stmt = $this->pdo->prepare("UPDATE screen_media SET attiva = 0 WHERE attiva = 1");
        return $stmt->execute();
    }

    public function mediaAttive(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, titolo, file_path, attiva
             FROM screen_media
             WHERE attiva = 1
             ORDER BY id DESC"
        );

        return $stmt->fetchAll();
    }

    public function mediaAttivaRandom(): ?array
    {
        $countStmt = $this->pdo->query(
            "SELECT COUNT(*) AS totale
             FROM screen_media
             WHERE attiva = 1"
        );
        $row = $countStmt->fetch();
        $totale = (int) ($row['totale'] ?? 0);

        if ($totale <= 0) {
            return null;
        }

        $offset = random_int(0, $totale - 1);

        $stmt = $this->pdo->prepare(
            "SELECT id, titolo, file_path, attiva
             FROM screen_media
             WHERE attiva = 1
             ORDER BY id DESC
             LIMIT 1 OFFSET :offset"
        );
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    // Compatibilità retroattiva: mantiene il vecchio nome metodo
    public function attiva(int $id): bool
    {
        return $this->impostaAttiva($id, true);
    }

    public function trova(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, titolo, file_path, attiva
             FROM screen_media
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function elimina(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM screen_media WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
