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

    public function lista(string $contesto = 'screen', ?string $tipoFile = null): array
    {
        $sql = "SELECT id, titolo, file_path, contesto, tipo_file, attiva, creato_il
                FROM screen_media
                WHERE contesto = :contesto";

        $params = ['contesto' => $contesto];

        if ($tipoFile !== null && $tipoFile !== '') {
            $sql .= " AND tipo_file = :tipo_file";
            $params['tipo_file'] = $tipoFile;
        }

        $sql .= " ORDER BY id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function crea(string $titolo, string $filePath, string $contesto = 'screen', string $tipoFile = 'image'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO screen_media (titolo, file_path, contesto, tipo_file, attiva)
             VALUES (:titolo, :file_path, :contesto, :tipo_file, 0)"
        );

        $stmt->execute([
            'titolo' => $titolo,
            'file_path' => $filePath,
            'contesto' => $contesto,
            'tipo_file' => $tipoFile,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function impostaAttiva(int $id, bool $attiva, string $contesto = 'screen'): bool
    {
        $existsStmt = $this->pdo->prepare(
            "SELECT id
             FROM screen_media
             WHERE id = :id
               AND contesto = :contesto
             LIMIT 1"
        );
        $existsStmt->execute([
            'id' => $id,
            'contesto' => $contesto,
        ]);

        if (!$existsStmt->fetch()) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE screen_media
             SET attiva = :attiva
             WHERE id = :id
               AND contesto = :contesto"
        );

        $stmt->execute([
            'attiva' => $attiva ? 1 : 0,
            'id' => $id,
            'contesto' => $contesto,
        ]);

        return true;
    }

    public function disattivaTutte(string $contesto = 'screen'): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE screen_media
             SET attiva = 0
             WHERE attiva = 1
               AND contesto = :contesto"
        );

        return $stmt->execute(['contesto' => $contesto]);
    }

    public function mediaAttive(string $contesto = 'screen'): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, titolo, file_path, contesto, tipo_file, attiva
             FROM screen_media
             WHERE attiva = 1
               AND contesto = :contesto
             ORDER BY id DESC"
        );

        $stmt->execute(['contesto' => $contesto]);
        return $stmt->fetchAll();
    }

    public function mediaAttivaRandom(string $contesto = 'screen'): ?array
    {
        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS totale
             FROM screen_media
             WHERE attiva = 1
               AND contesto = :contesto"
        );

        $countStmt->execute(['contesto' => $contesto]);
        $row = $countStmt->fetch();
        $totale = (int) ($row['totale'] ?? 0);

        if ($totale <= 0) {
            return null;
        }

        $offset = random_int(0, $totale - 1);

        $stmt = $this->pdo->prepare(
            "SELECT id, titolo, file_path, contesto, tipo_file, attiva
             FROM screen_media
             WHERE attiva = 1
               AND contesto = :contesto
             ORDER BY id DESC
             LIMIT 1 OFFSET :offset"
        );

        $stmt->bindValue(':contesto', $contesto, PDO::PARAM_STR);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    public function attiva(int $id): bool
    {
        return $this->impostaAttiva($id, true, 'screen');
    }

    public function trova(int $id, ?string $contesto = null): ?array
    {
        if ($contesto !== null && $contesto !== '') {
            $stmt = $this->pdo->prepare(
                "SELECT id, titolo, file_path, contesto, tipo_file, attiva
                 FROM screen_media
                 WHERE id = :id
                   AND contesto = :contesto
                 LIMIT 1"
            );

            $stmt->execute([
                'id' => $id,
                'contesto' => $contesto,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT id, titolo, file_path, contesto, tipo_file, attiva
                 FROM screen_media
                 WHERE id = :id
                 LIMIT 1"
            );

            $stmt->execute(['id' => $id]);
        }

        return $stmt->fetch() ?: null;
    }

    public function elimina(int $id, ?string $contesto = null): bool
    {
        if ($contesto !== null && $contesto !== '') {
            $stmt = $this->pdo->prepare(
                "DELETE FROM screen_media
                 WHERE id = :id
                   AND contesto = :contesto"
            );

            $stmt->execute([
                'id' => $id,
                'contesto' => $contesto,
            ]);
        } else {
            $stmt = $this->pdo->prepare("DELETE FROM screen_media WHERE id = :id");
            $stmt->execute(['id' => $id]);
        }

        return $stmt->rowCount() > 0;
    }
}
