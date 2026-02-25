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
            if (empty($esistente['partecipazione_id']) && $partecipazioneId > 0) {
                $update = $this->pdo->prepare(
                    "UPDATE join_richieste
                     SET partecipazione_id = :partecipazione_id
                     WHERE id = :id"
                );

                $update->execute([
                    'partecipazione_id' => $partecipazioneId,
                    'id' => $esistente['id'],
                ]);

                $esistente['partecipazione_id'] = $partecipazioneId;
            }

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

        if ($stato === 'approvata') {
            return $this->approva($id, $sessioneId);
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

    private function approva(int $id, int $sessioneId): bool
    {
        $richiesta = $this->trovaPerId($id, $sessioneId);

        if (!$richiesta || $richiesta['stato'] !== 'pending') {
            return false;
        }

        $partecipazioneId = (int) ($richiesta['partecipazione_id'] ?? 0);

        // 1) Se già presente, deve appartenere alla sessione corrente
        if ($partecipazioneId > 0) {
            $checkPartecipazione = $this->pdo->prepare(
                "SELECT id
                 FROM partecipazioni
                 WHERE id = :id
                   AND sessione_id = :sessione_id
                 LIMIT 1"
            );

            $checkPartecipazione->execute([
                'id' => $partecipazioneId,
                'sessione_id' => $sessioneId,
            ]);

            if (!$checkPartecipazione->fetch()) {
                $partecipazioneId = 0;
            }
        }

        // 2) Fallback robusto: recupera la partecipazione della STESSA sessione via nome
        if ($partecipazioneId <= 0) {
            $recuperaPartecipazione = $this->pdo->prepare(
                "SELECT p.id
                 FROM partecipazioni p
                 JOIN utenti u ON u.id = p.utente_id
                 WHERE p.sessione_id = :sessione_id
                   AND LOWER(u.nome) = LOWER(:nome)
                 ORDER BY p.id DESC
                 LIMIT 1"
            );

            $recuperaPartecipazione->execute([
                'sessione_id' => $sessioneId,
                'nome' => $richiesta['nome'],
            ]);

            $partecipazione = $recuperaPartecipazione->fetch();

            if ($partecipazione) {
                $partecipazioneId = (int) $partecipazione['id'];
            }
        }

        // 3) Se non troviamo una partecipazione valida, NON approviamo (evita approvazioni "orfane")
        if ($partecipazioneId <= 0) {
            return false;
        }

        $this->ripristinaCapitaleRientroSeAzzerato($partecipazioneId, $sessioneId);

        $stmt = $this->pdo->prepare(
            "UPDATE join_richieste
             SET stato = 'approvata',
                 partecipazione_id = :partecipazione_id,
                 gestita_il = :gestita_il
             WHERE id = :id
               AND sessione_id = :sessione_id
               AND stato = 'pending'"
        );

        $stmt->execute([
            'partecipazione_id' => $partecipazioneId,
            'gestita_il' => time(),
            'id' => $id,
            'sessione_id' => $sessioneId,
        ]);

        return $stmt->rowCount() > 0;
    }

    private function ripristinaCapitaleRientroSeAzzerato(int $partecipazioneId, int $sessioneId): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT capitale_attuale
             FROM partecipazioni
             WHERE id = :id
               AND sessione_id = :sessione_id
             LIMIT 1"
        );

        $stmt->execute([
            'id' => $partecipazioneId,
            'sessione_id' => $sessioneId,
        ]);

        $row = $stmt->fetch();
        $capitaleAttuale = (int) ($row['capitale_attuale'] ?? 0);

        if ($capitaleAttuale > 0) {
            return;
        }

        $ultimoInClassificaStmt = $this->pdo->prepare(
            "SELECT capitale_attuale
             FROM partecipazioni
             WHERE sessione_id = :sessione_id
               AND id <> :partecipazione_id
               AND capitale_attuale > 0
             ORDER BY capitale_attuale ASC, id DESC
             LIMIT 1"
        );

        $ultimoInClassificaStmt->execute([
            'sessione_id' => $sessioneId,
            'partecipazione_id' => $partecipazioneId,
        ]);

        $ultimo = $ultimoInClassificaStmt->fetch();

        if (!$ultimo) {
            return;
        }

        $capitaleRientro = (int) ($ultimo['capitale_attuale'] ?? 0);

        $update = $this->pdo->prepare(
            "UPDATE partecipazioni
             SET capitale_attuale = :capitale
             WHERE id = :id
               AND sessione_id = :sessione_id"
        );

        $update->execute([
            'capitale' => $capitaleRientro,
            'id' => $partecipazioneId,
            'sessione_id' => $sessioneId,
        ]);
    }
}
