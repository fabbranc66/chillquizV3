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
        $configModel = new ConfigurazioneQuiz();
        $config = $configModel->trova($configurazioneId);

        if (!$config) {
            throw new \RuntimeException("Configurazione non trovata");
        }

        $numero = (int) $config['numero_domande'];
        $poolTipo = $config['pool_tipo'];
        $argomentoId = $config['argomento_id'];
        $selezioneTipo = $config['selezione_tipo'];

        $sql = "SELECT id FROM domande WHERE attiva = 1";

        if ($poolTipo === 'mono' && $argomentoId) {
            $sql .= " AND argomento_id = :argomento_id";
        }

        if ($selezioneTipo === 'random') {
            $sql .= " ORDER BY RAND()";
        } else {
            $sql .= " ORDER BY id ASC";
        }

        $sql .= " LIMIT " . $numero;

        $stmt = $this->pdo->prepare($sql);

        if ($poolTipo === 'mono' && $argomentoId) {
            $stmt->execute(['argomento_id' => $argomentoId]);
        } else {
            $stmt->execute();
        }

        $domande = $stmt->fetchAll();

        $this->salvaDomandeSessione($sessioneId, $domande);
    }

    public function generaDaSessione(int $sessioneId, int $numero, string $poolTipo, ?int $argomentoId): void
    {
        $sql = "SELECT id FROM domande WHERE attiva = 1";
        $params = [];

        if ($poolTipo === 'fisso' && $argomentoId !== null) {
            $sql .= " AND argomento_id = :argomento_id";
            $params['argomento_id'] = $argomentoId;
        }

        $sql .= " ORDER BY RAND() LIMIT " . max(1, $numero);

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
                'posizione' => $posizione
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
            'posizione' => $numero
        ]);

        return $stmt->fetch() ?: null;
    }
}
