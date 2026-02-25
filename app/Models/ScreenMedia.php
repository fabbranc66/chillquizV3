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

    public function attiva(int $id): bool
    {
        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec("UPDATE screen_media SET attiva = 0");

            $stmt = $this->pdo->prepare(
                "UPDATE screen_media
                 SET attiva = 1
                 WHERE id = :id"
            );

            $stmt->execute(['id' => $id]);
            $ok = $stmt->rowCount() > 0;

            if ($ok) {
                $this->pdo->commit();
                return true;
            }

            $this->pdo->rollBack();
            return false;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function disattivaTutte(): bool
    {
        $stmt = $this->pdo->prepare("UPDATE screen_media SET attiva = 0 WHERE attiva = 1");
        return $stmt->execute();
    }

    public function mediaAttiva(): ?array
    {
        $stmt = $this->pdo->query(
            "SELECT id, titolo, file_path, attiva
             FROM screen_media
             WHERE attiva = 1
             ORDER BY id DESC
             LIMIT 1"
        );

        return $stmt->fetch() ?: null;
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
