<?php

namespace App\Services\Sessione\Traits;

use PDO;
use RuntimeException;

trait SessionLoaderTrait
{
    private bool $revealSchemaChecked = false;
    private bool $stateSchemaChecked = false;

    private function loadSessione(): void
    {
        $this->ensureRevealSchema();
        $this->ensurePreviewStateSchema();

        $stmt = $this->pdo->prepare('SELECT * FROM sessioni WHERE id = ?');
        $stmt->execute([$this->sessioneId]);

        $result = $stmt->fetch();

        if (!$result) {
            throw new RuntimeException('Sessione non trovata.');
        }

        $state = trim((string) ($result['stato'] ?? ''));
        if ($state === '') {
            $state = 'attesa';
            $fixStmt = $this->pdo->prepare('UPDATE sessioni SET stato = ? WHERE id = ?');
            $fixStmt->execute([$state, $this->sessioneId]);
            $result['stato'] = $state;
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

    private function ensurePreviewStateSchema(): void
    {
        if ($this->stateSchemaChecked) {
            return;
        }

        $this->stateSchemaChecked = true;

        $databaseName = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($databaseName === '') {
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1'
        );
        $stmt->execute([$databaseName, 'sessioni', 'stato']);
        $column = $stmt->fetch();

        if (!$column) {
            return;
        }

        $dataType = strtolower((string) ($column['DATA_TYPE'] ?? ''));
        $columnType = strtolower((string) ($column['COLUMN_TYPE'] ?? ''));
        if ($dataType !== 'enum') {
            return;
        }

        if (strpos($columnType, "'preview'") !== false) {
            return;
        }

        $this->pdo->exec(
            "ALTER TABLE sessioni
             MODIFY COLUMN stato ENUM('attesa','puntata','preview','domanda','risultati','conclusa')
             NOT NULL DEFAULT 'attesa'"
        );
    }
}
