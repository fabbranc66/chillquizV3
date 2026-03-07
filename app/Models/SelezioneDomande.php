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

        if ($sessionConfig !== null) {
            $numero = (int) $sessionConfig['numero_domande'];
            $poolTipo = $sessionConfig['pool_tipo'];
            $argomentoId = $sessionConfig['argomento_id'];
            $selezioneTipo = $sessionConfig['selezione_tipo'];
        } else {
            $configModel = new ConfigurazioneQuiz();
            $config = $configModel->trova($configurazioneId);

            if (!$config) {
                throw new \RuntimeException('Configurazione non trovata');
            }

            $numero = (int) $config['numero_domande'];
            $poolTipo = ($config['pool_tipo'] ?? 'misto') === 'mono' ? 'mono' : 'tutti';
            $argomentoId = $config['argomento_id'] !== null ? (int) $config['argomento_id'] : null;
            $selezioneTipo = ($config['selezione_tipo'] ?? 'random') === 'manuale' ? 'manuale' : 'random';
        }

        $sql = 'SELECT id FROM domande WHERE attiva = 1';
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

        $sql .= ' LIMIT ' . $numero;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $domande = $stmt->fetchAll();

        $this->salvaDomandeSessione($sessioneId, $domande);
    }

    private function loadSessionConfig(int $sessioneId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT numero_domande, pool_tipo, argomento_id, selezione_tipo FROM sessioni WHERE id = :id LIMIT 1'
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