<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConfigurazioneQuiz
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function crea(
        string $titolo,
        int $numeroDomande,
        string $poolTipo,
        ?int $argomentoId,
        string $selezioneTipo
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO configurazioni_quiz
             (titolo, numero_domande, pool_tipo, argomento_id, selezione_tipo, attiva, creata_il)
             VALUES
             (:titolo, :numero_domande, :pool_tipo, :argomento_id, :selezione_tipo, 1, :creata_il)"
        );

        $stmt->execute([
            'titolo' => $titolo,
            'numero_domande' => $numeroDomande,
            'pool_tipo' => $poolTipo,
            'argomento_id' => $argomentoId,
            'selezione_tipo' => $selezioneTipo,
            'creata_il' => time(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function aggiorna(
        int $id,
        string $titolo,
        int $numeroDomande,
        string $poolTipo,
        ?int $argomentoId,
        string $selezioneTipo
    ): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE configurazioni_quiz
             SET titolo = :titolo,
                 numero_domande = :numero_domande,
                 pool_tipo = :pool_tipo,
                 argomento_id = :argomento_id,
                 selezione_tipo = :selezione_tipo
             WHERE id = :id"
        );

        return $stmt->execute([
            'titolo' => $titolo,
            'numero_domande' => $numeroDomande,
            'pool_tipo' => $poolTipo,
            'argomento_id' => $argomentoId,
            'selezione_tipo' => $selezioneTipo,
            'id' => $id,
        ]);
    }

    public function tutte(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM configurazioni_quiz
             ORDER BY creata_il DESC"
        );

        return $stmt->fetchAll();
    }

    public function trova(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM configurazioni_quiz
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $id]);
        $config = $stmt->fetch();

        return $config ?: null;
    }
}