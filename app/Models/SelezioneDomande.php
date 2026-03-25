<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class SelezioneDomande
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function genera(int $sessioneId, int $configurazioneId): void
    {
        $sessionConfig = $this->loadSessionConfig($sessioneId);

        if ($sessionConfig === null) {
            throw new \RuntimeException('Snapshot sessione non trovato');
        }

        $numero = (int) $sessionConfig['numero_domande'];
        $poolTipo = $sessionConfig['pool_tipo'];
        $argomentoId = $sessionConfig['argomento_id'];
        $selezioneTipo = $sessionConfig['selezione_tipo'];
        $maxPerArgomento = (int) ($sessionConfig['max_per_argomento'] ?? 0);

        $sql = 'SELECT id, argomento_id FROM domande WHERE attiva = 1';
        $params = [];

        if ($poolTipo === 'mono' && $argomentoId) {
            $sql .= ' AND argomento_id = :argomento_id';
            $params['argomento_id'] = $argomentoId;
        }

        if ($poolTipo === 'sarabanda') {
            $sql .= ' AND UPPER(COALESCE(tipo_domanda, \'CLASSIC\')) = :tipo_domanda';
            $params['tipo_domanda'] = 'SARABANDA';
        }

        if ($selezioneTipo === 'random') {
            $sql .= ' ORDER BY RAND()';
        } else {
            $sql .= ' ORDER BY id ASC';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $domande = $stmt->fetchAll() ?: [];
        if ($poolTipo === 'tutti' && $selezioneTipo === 'random' && $maxPerArgomento > 0) {
            $domande = $this->applyTopicLimit($domande, $numero, $maxPerArgomento);
        } else {
            $domande = array_slice(
                array_map(static fn (array $row): array => ['id' => (int) $row['id']], $domande),
                0,
                $numero
            );
        }

        $this->salvaDomandeSessione($sessioneId, $domande);
    }

    private function applyTopicLimit(array $domande, int $numero, int $maxPerArgomento): array
    {
        $selected = [];
        $countsByTopic = [];

        foreach ($domande as $row) {
            $topicId = (int) ($row['argomento_id'] ?? 0);
            $currentCount = (int) ($countsByTopic[$topicId] ?? 0);
            if ($currentCount >= $maxPerArgomento) {
                continue;
            }

            $selected[] = ['id' => (int) $row['id']];
            $countsByTopic[$topicId] = $currentCount + 1;

            if (count($selected) >= $numero) {
                break;
            }
        }

        return $selected;
    }

    private function loadSessionConfig(int $sessioneId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT numero_domande, pool_tipo, argomento_id, selezione_tipo, max_per_argomento FROM sessioni WHERE id = :id LIMIT 1'
        );

        $stmt->execute(['id' => $sessioneId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $numero = (int) ($row['numero_domande'] ?? 0);
        if ($numero <= 0) {
            return null;
        }

        $poolRaw = (string) ($row['pool_tipo'] ?? 'tutti');
        $poolTipo = $poolRaw === 'sarabanda'
            ? 'sarabanda'
            : (($poolRaw === 'mono') ? 'mono' : 'tutti');

        return [
            'numero_domande' => $numero,
            'pool_tipo' => $poolTipo,
            'argomento_id' => $row['argomento_id'] !== null ? (int) $row['argomento_id'] : null,
            'selezione_tipo' => ($row['selezione_tipo'] ?? 'random') === 'manuale' ? 'manuale' : 'random',
            'max_per_argomento' => $row['max_per_argomento'] !== null ? (int) $row['max_per_argomento'] : null,
        ];
    }

    public function generaDaSessione(int $sessioneId, int $numero, string $poolTipo, ?int $argomentoId): void
    {
        $sql = 'SELECT id FROM domande WHERE attiva = 1';
        $params = [];

        if ($poolTipo === 'fisso' && $argomentoId !== null) {
            $sql .= ' AND argomento_id = :argomento_id';
            $params['argomento_id'] = $argomentoId;
        }

        if ($poolTipo === 'sarabanda') {
            $sql .= ' AND UPPER(COALESCE(tipo_domanda, \'CLASSIC\')) = :tipo_domanda';
            $params['tipo_domanda'] = 'SARABANDA';
        }

        $sql .= ' ORDER BY RAND() LIMIT ' . max(1, $numero);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $domande = $stmt->fetchAll();

        if (count($domande) < $numero) {
            throw new \RuntimeException('Domande insufficienti per il pool richiesto.');
        }

        $this->salvaDomandeSessione($sessioneId, $domande);
    }

    private function salvaDomandeSessione(int $sessioneId, array $domande): void
    {
        $posizione = 1;

        foreach ($domande as $domanda) {
            $insert = $this->pdo->prepare(
                "INSERT INTO sessione_domande
                 (sessione_id, domanda_id, posizione)
                 VALUES (:sessione_id, :domanda_id, :posizione)"
            );

            $insert->execute([
                'sessione_id' => $sessioneId,
                'domanda_id' => $domanda['id'],
                'posizione' => $posizione,
            ]);

            $posizione++;
        }
    }

    public function domandaCorrente(int $sessioneId, int $numero): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT d.*
             FROM sessione_domande sd
             JOIN domande d ON d.id = sd.domanda_id
             WHERE sd.sessione_id = :sessione_id
             AND sd.posizione = :posizione
             LIMIT 1"
        );

        $stmt->execute([
            'sessione_id' => $sessioneId,
            'posizione' => $numero,
        ]);

        return $stmt->fetch() ?: null;
    }
}
