<?php

namespace App\Services\Sessione\Traits;

use PDO;
use RuntimeException;

trait SessionLoaderTrait
{
    private bool $revealSchemaChecked = false;

    private function loadSessione(): void
    {
        $this->ensureRevealSchema();

        $stmt = $this->pdo->prepare('SELECT * FROM sessioni WHERE id = ?');
        $stmt->execute([$this->sessioneId]);

        $result = $stmt->fetch();

        if (!$result) {
            throw new RuntimeException('Sessione non trovata.');
        }

        $this->sessione = $result;
    }

    private function loadSessioneOrFail(int $sessioneId): void
    {
        $this->loadSessione();
    }

    private function ensureRevealSchema(): void
    {
        if ($this->revealSchemaChecked) {
            return;
        }

        $this->revealSchemaChecked = true;

        $databaseName = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($databaseName === '') {
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$databaseName, 'sessioni', 'mostra_corretta_fino']);
        $exists = (int) $stmt->fetchColumn() > 0;

        if ($exists) {
            return;
        }

        $this->pdo->exec('ALTER TABLE sessioni ADD COLUMN mostra_corretta_fino DECIMAL(13,3) NULL DEFAULT NULL AFTER inizio_domanda');
    }
}
