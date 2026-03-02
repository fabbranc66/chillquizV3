<?php

namespace App\Services\Sessione\Traits;

trait PuntateLiveTrait
{
    public function salvaPuntataLive(int $partecipazioneId, int $importo): void
    {
        $this->ensurePuntateLiveTable();

        $stmt = $this->pdo->prepare("
            INSERT INTO puntate_live (sessione_id, partecipazione_id, importo, aggiornato_il)
            VALUES (:sessione_id, :partecipazione_id, :importo, :aggiornato_il)
            ON DUPLICATE KEY UPDATE
                importo = VALUES(importo),
                aggiornato_il = VALUES(aggiornato_il)
        ");

        $stmt->execute([
            'sessione_id' => $this->sessioneId,
            'partecipazione_id' => $partecipazioneId,
            'importo' => $importo,
            'aggiornato_il' => time(),
        ]);
    }

    public function rimuoviPuntataLive(int $partecipazioneId): void
    {
        $this->ensurePuntateLiveTable();

        $stmt = $this->pdo->prepare("
            DELETE FROM puntate_live
            WHERE sessione_id = :sessione_id
              AND partecipazione_id = :partecipazione_id
        ");

        $stmt->execute([
            'sessione_id' => $this->sessioneId,
            'partecipazione_id' => $partecipazioneId,
        ]);
    }

    private function svuotaPuntateLive(): void
    {
        $this->ensurePuntateLiveTable();

        $stmt = $this->pdo->prepare("
            DELETE FROM puntate_live
            WHERE sessione_id = ?
        ");

        $stmt->execute([$this->sessioneId]);
    }

    private function ensurePuntateLiveTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS puntate_live (
                sessione_id INT NOT NULL,
                partecipazione_id INT NOT NULL,
                importo INT NOT NULL,
                aggiornato_il INT NOT NULL,
                PRIMARY KEY (sessione_id, partecipazione_id),
                KEY idx_sessione (sessione_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}