<?php

namespace App\Services\Sessione\Traits;

use RuntimeException;

trait PoolDomandeTrait
{
    public function generaDomandeSessione(): void
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as totale
            FROM sessione_domande
            WHERE sessione_id = ?
        ");
        $stmt->execute([$this->sessioneId]);
        $check = $stmt->fetch();

        if ($check['totale'] > 0) {
            return;
        }

        $config = $this->loadUnifiedQuizConfig((int) $this->sessione['configurazione_id']);

        if (!$config) {
            throw new RuntimeException("Configurazione quiz non trovata.");
        }

        $numeroDomande = (int) $config['numero_domande'];

        if (($config['source'] ?? '') === 'v2' && ($config['modalita'] ?? '') === 'manuale_domande_argomento_corrente') {
            $domande = $this->loadManualV2Questions((int) $this->sessione['configurazione_id']);

            if (count($domande) < $numeroDomande) {
                throw new RuntimeException("Domande manuali insufficienti per generare la sessione.");
            }

            $domande = array_slice($domande, 0, $numeroDomande);
            $this->persistSessionQuestions($domande);
            return;
        }

        $poolTipo = $config['pool_tipo'] ?? 'tutti';
        $argomentoId = $config['argomento_id'] ?? null;
        $selezione = $config['selezione_tipo'] ?? 'random';

        $query = "SELECT id FROM domande WHERE attiva = 1";
        $params = [];

        if ($poolTipo === 'mono' && $argomentoId) {
            $query .= " AND argomento_id = ?";
            $params[] = $argomentoId;
        }

        $query .= ($selezione === 'random')
            ? " ORDER BY RAND()"
            : " ORDER BY id ASC";

        $query .= " LIMIT " . $numeroDomande;

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $domande = $stmt->fetchAll();

        if (count($domande) < $numeroDomande) {
            throw new RuntimeException("Domande insufficienti per generare la sessione.");
        }

        $this->persistSessionQuestions($domande);
    }

    private function persistSessionQuestions(array $domande): void
    {
        $posizione = 1;

        foreach ($domande as $d) {
            $stmt = $this->pdo->prepare("
                INSERT INTO sessione_domande
                (sessione_id, domanda_id, posizione)
                VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $this->sessioneId,
                $d['id'],
                $posizione
            ]);

            $posizione++;
        }
    }

    public function domandaCorrente(): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.testo, d.difficolta
            FROM sessione_domande sd
            JOIN domande d ON d.id = sd.domanda_id
            WHERE sd.sessione_id = ?
            AND sd.posizione = ?
            LIMIT 1
        ");

        $stmt->execute([
            $this->sessioneId,
            $this->sessione['domanda_corrente']
        ]);

        $domanda = $stmt->fetch();

        if (!$domanda) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT id, testo
            FROM opzioni
            WHERE domanda_id = ?
            ORDER BY id ASC
        ");

        $stmt->execute([$domanda['id']]);
        $domanda['opzioni'] = $stmt->fetchAll();

        return $domanda;
    }

    private function loadManualV2Questions(int $configurazioneId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.id
            FROM configurazioni_quiz_v2_domande qd
            JOIN domande d ON d.id = qd.domanda_id
            WHERE qd.configurazione_id = :configurazione_id
              AND d.attiva = 1
            ORDER BY qd.posizione ASC
        ");

        $stmt->execute(['configurazione_id' => $configurazioneId]);
        return $stmt->fetchAll();
    }

    private function domandaCorrenteId(): int
    {
        $stmt = $this->pdo->prepare("
            SELECT sd.domanda_id
            FROM sessione_domande sd
            WHERE sd.sessione_id = ?
              AND sd.posizione = ?
            LIMIT 1
        ");

        $stmt->execute([
            $this->sessioneId,
            $this->sessione['domanda_corrente'],
        ]);

        $row = $stmt->fetch();

        return (int) ($row['domanda_id'] ?? 0);
    }
}