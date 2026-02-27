<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Risposta
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function registra(
        int $partecipazioneId,
        int $opzioneId,
        int $puntata,
        int $tempoRisposta
    ): array {

        // Recupera capitale attuale
        $stmt = $this->pdo->prepare(
            "SELECT capitale_attuale
             FROM partecipazioni
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $partecipazioneId]);
        $partecipazione = $stmt->fetch();

        if (!$partecipazione) {
            throw new \RuntimeException("Partecipazione non trovata");
        }

        $capitale = (int) $partecipazione['capitale_attuale'];

        // Recupera correttezza + difficoltà + domanda_id
        $stmt = $this->pdo->prepare(
            "SELECT o.corretta, o.domanda_id, d.difficolta
             FROM opzioni o
             JOIN domande d ON d.id = o.domanda_id
             WHERE o.id = :opzione_id
             LIMIT 1"
        );

        $stmt->execute(['opzione_id' => $opzioneId]);
        $data = $stmt->fetch();

        if (!$data) {
            throw new \RuntimeException("Opzione non valida");
        }

        $corretta = (int) $data['corretta'];
        $domandaId = (int) $data['domanda_id'];
        $difficolta = (float) $data['difficolta'];

        $sistema = new Sistema();

        $fattoreMax = (float) $sistema->get('fattore_velocita_max');
        $durata = (int) $sistema->get('durata_domanda');

        $tempoRimanente = max(0, $durata - $tempoRisposta);
        $percentuale = $durata > 0 ? ($tempoRimanente / $durata) : 0;
        $fattoreTempo = round($percentuale * $fattoreMax, 2);

        $bonusPrimoAttivo = (int) $sistema->get('bonus_primo_attivo');
        $coeffBonus = 0.25;

        $punti = 0;
        $bonusPrimo = 0;

        if ($corretta) {

            $bonusDifficolta = $puntata * $difficolta;
            $bonusVelocita = $puntata * $fattoreTempo;
            $puntiBase = $bonusDifficolta + $bonusVelocita;

            if ($bonusPrimoAttivo) {

                $stmt = $this->pdo->prepare(
                    "SELECT COUNT(*) as totale
                     FROM risposte
                     WHERE domanda_id = :domanda_id"
                );
                $stmt->execute(['domanda_id' => $domandaId]);
                $count = $stmt->fetch();

                if ((int)$count['totale'] === 0) {
                    $bonusPrimo = $puntata * $coeffBonus;
                }
            }

            $punti = (int) round($puntiBase + $bonusPrimo);
            $capitale += $punti;

        } else {

            $capitale -= $puntata;
        }

        // Inserisci risposta
        $insert = $this->pdo->prepare(
            "INSERT INTO risposte
             (partecipazione_id, domanda_id, puntata, corretta, punti, tempo_risposta, data_risposta)
             VALUES
             (:partecipazione_id, :domanda_id, :puntata, :corretta, :punti, :tempo_risposta, :data_risposta)"
        );

        $insert->execute([
            'partecipazione_id' => $partecipazioneId,
            'domanda_id' => $domandaId,
            'puntata' => $puntata,
            'corretta' => $corretta,
            'punti' => $punti,
            'tempo_risposta' => $tempoRisposta,
            'data_risposta' => time(),
        ]);

        // Aggiorna capitale
        $update = $this->pdo->prepare(
            "UPDATE partecipazioni
             SET capitale_attuale = :capitale
             WHERE id = :id"
        );

        $update->execute([
            'capitale' => $capitale,
            'id' => $partecipazioneId
        ]);

        return [
            'corretta' => (bool) $corretta,
            'punti' => $punti,
            'capitale' => $capitale
        ];
    }
}
