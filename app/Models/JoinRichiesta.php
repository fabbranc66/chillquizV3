<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class JoinRichiesta
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    private function normalizzaNome(string $nome): string
    {
        return mb_strtolower(trim($nome));
    }

    public function creaORiprendiPending(int $sessioneId, string $nome, int $partecipazioneId): array
    {
        $nomeNorm = $this->normalizzaNome($nome);

        $check = $this->pdo->prepare(
            "SELECT id, sessione_id, nome, nome_norm, stato, partecipazione_id
             FROM join_richieste
             WHERE sessione_id = :sessione_id
               AND nome_norm = :nome_norm
               AND stato = 'pending'
             ORDER BY id DESC
             LIMIT 1"
        );

        $check->execute([
            'sessione_id' => $sessioneId,
            'nome_norm' => $nomeNorm,
        ]);

        $esistente = $check->fetch();

        if ($esistente) {
            return $esistente;
        }

        $insert = $this->pdo->prepare(
            "INSERT INTO join_richieste
             (sessione_id, nome, nome_norm, stato, partecipazione_id, creata_il)
             VALUES (:sessione_id, :nome, :nome_norm, 'pending', :partecipazione_id, :creata_il)"
        );

        $insert->execute([
            'sessione_id' => $sessioneId,
            'nome' => $nome,
            'nome_norm' => $nomeNorm,
            'partecipazione_id' => $partecipazioneId,
            'creata_il' => time(),
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'sessione_id' => $sessioneId,
            'nome' => $nome,
            'nome_norm' => $nomeNorm,
            'stato' => 'pending',
            'partecipazione_id' => $partecipazioneId,
        ];
    }

    public function listaPending(int $sessioneId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, sessione_id, nome, stato, partecipazione_id, creata_il
             FROM join_richieste
             WHERE sessione_id = :sessione_id
               AND stato = 'pending'
             ORDER BY id ASC"
        );

        $stmt->execute(['sessione_id' => $sessioneId]);
        return $stmt->fetchAll();
    }

    public function trovaPerId(int $id, int $sessioneId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, sessione_id, nome, stato, partecipazione_id
             FROM join_richieste
             WHERE id = :id
               AND sessione_id = :sessione_id
             LIMIT 1"
        );

        $stmt->execute([
            'id' => $id,
            'sessione_id' => $sessioneId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function gestisci(int $id, int $sessioneId, string $stato): bool
    {
        if (!in_array($stato, ['approvata', 'rifiutata'], true)) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE join_richieste
             SET stato = :stato,
                 gestita_il = :gestita_il
             WHERE id = :id
               AND sessione_id = :sessione_id
               AND stato = 'pending'"
        );

        $stmt->execute([
            'stato' => $stato,
            'gestita_il' => time(),
            'id' => $id,
            'sessione_id' => $sessioneId,
        ]);

        return $stmt->rowCount() > 0;
    }
}
