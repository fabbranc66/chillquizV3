<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Sessione
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function crea(int $configurazioneId): int
    {
        $pin = $this->generaPin();

        $stmt = $this->pdo->prepare(
            "INSERT INTO sessioni
             (configurazione_id, pin, stato, domanda_corrente, creata_il)
             VALUES
             (:configurazione_id, :pin, 'attesa', 1, :creata_il)"
        );

        $stmt->execute([
            'configurazione_id' => $configurazioneId,
            'pin' => $pin,
            'creata_il' => time(),
        ]);

        $sessioneId = (int) $this->pdo->lastInsertId();

        $selezione = new \App\Models\SelezioneDomande();
        $selezione->genera($sessioneId, $configurazioneId);

        return $sessioneId;
    }

    private function generaPin(): string
    {
        do {
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $stmt = $this->pdo->prepare(
                "SELECT id FROM sessioni WHERE pin = :pin LIMIT 1"
            );

            $stmt->execute(['pin' => $pin]);
            $esiste = $stmt->fetch();

        } while ($esiste);

        return $pin;
    }

    public function trova(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM sessioni WHERE id = :id LIMIT 1"
        );

        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function cambiaStato(int $id, string $stato): bool
    {
        if ($stato === 'domanda') {

            $stmt = $this->pdo->prepare(
                "UPDATE sessioni
                 SET stato = :stato,
                     inizio_domanda = :inizio
                 WHERE id = :id"
            );

            return $stmt->execute([
                'stato' => $stato,
                'inizio' => time(),
                'id' => $id
            ]);
        }

        $stmt = $this->pdo->prepare(
            "UPDATE sessioni
             SET stato = :stato
             WHERE id = :id"
        );

        return $stmt->execute([
            'stato' => $stato,
            'id' => $id
        ]);
    }

    public function avanzaDomanda(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.domanda_corrente, c.numero_domande
             FROM sessioni s
             JOIN configurazioni_quiz c ON c.id = s.configurazione_id
             WHERE s.id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        if (!$data) {
            return;
        }

        $corrente = (int) $data['domanda_corrente'];
        $totale = (int) $data['numero_domande'];

        if ($corrente >= $totale) {

            $update = $this->pdo->prepare(
                "UPDATE sessioni
                 SET stato = 'conclusa'
                 WHERE id = :id"
            );

            $update->execute(['id' => $id]);

        } else {

            $update = $this->pdo->prepare(
                "UPDATE sessioni
                 SET domanda_corrente = domanda_corrente + 1,
                     stato = 'puntata',
                     inizio_domanda = NULL
                 WHERE id = :id"
            );

            $update->execute(['id' => $id]);
        }
    }
}