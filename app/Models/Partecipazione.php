<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Partecipazione
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /* ======================
       ENTRA SESSIONE
    ====================== */

    public function entra(int $sessioneId, int $utenteId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM partecipazioni
             WHERE sessione_id = :sessione_id
             AND utente_id = :utente_id
             LIMIT 1"
        );

        $stmt->execute([
            'sessione_id' => $sessioneId,
            'utente_id' => $utenteId
        ]);

        $esiste = $stmt->fetch();

        if ($esiste) {
            return (int) $esiste['id'];
        }

        $sistema = new Sistema();
        $capitale = (int) $sistema->get('capitale_iniziale');

        $insert = $this->pdo->prepare(
            "INSERT INTO partecipazioni
             (sessione_id, utente_id, capitale_attuale, entrato_il)
             VALUES
             (:sessione_id, :utente_id, :capitale, :entrato_il)"
        );

        $insert->execute([
            'sessione_id' => $sessioneId,
            'utente_id' => $utenteId,
            'capitale' => $capitale,
            'entrato_il' => time(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /* ======================
       REGISTRA PUNTATA
    ====================== */

    public function registraPuntata(int $partecipazioneId, int $importo): bool
    {
        $partecipazione = $this->trova($partecipazioneId);

        if (!$partecipazione) return false;
        if ($importo <= 0 || $importo > $partecipazione['capitale_attuale']) return false;

        $_SESSION['puntate'][$partecipazioneId] = $importo;

        return true;
    }

    /* ======================
       REGISTRA RISPOSTA
    ====================== */

    public function registraRisposta(
        int $partecipazioneId,
        int $domandaId,
        int $opzioneId
    ): ?array {

        $partecipazione = $this->trova($partecipazioneId);
        if (!$partecipazione) return null;

        $puntata = $_SESSION['puntate'][$partecipazioneId] ?? 0;
        if ($puntata <= 0) return null;

        // Recupera sessione e timer
        $stmt = $this->pdo->prepare(
            "SELECT s.inizio_domanda, c.id as config_id
             FROM partecipazioni p
             JOIN sessioni s ON s.id = p.sessione_id
             JOIN configurazioni_quiz c ON c.id = s.configurazione_id
             WHERE p.id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $partecipazioneId]);
        $sessionData = $stmt->fetch();

        if (!$sessionData || !$sessionData['inizio_domanda']) {
            return null;
        }

        $inizioDomanda = (int) $sessionData['inizio_domanda'];

        $sistema = new Sistema();
        $durata = (int) $sistema->get('durata_domanda');
        $fattoreMax = (float) $sistema->get('fattore_velocita_max');
        $bonusPrimoAttivo = (int) $sistema->get('bonus_primo_attivo');
        $coeffBonusPrimo = (float) $sistema->get('coefficiente_bonus_primo');

        $tempoRisposta = time() - $inizioDomanda;
        $tempoRimanente = max(0, $durata - $tempoRisposta);

        $percentuale = $durata > 0
            ? ($tempoRimanente / $durata)
            : 0;

        $fattoreVelocita = round($percentuale * $fattoreMax, 2);

        // Verifica correttezza
        $stmt = $this->pdo->prepare(
            "SELECT corretta FROM opzioni
             WHERE id = :id LIMIT 1"
        );

        $stmt->execute(['id' => $opzioneId]);
        $opzione = $stmt->fetch();

        if (!$opzione) return null;

        $corretta = (int) $opzione['corretta'];

        // Difficoltà
        $stmt = $this->pdo->prepare(
            "SELECT difficolta FROM domande
             WHERE id = :id LIMIT 1"
        );

        $stmt->execute(['id' => $domandaId]);
        $domanda = $stmt->fetch();

        $difficolta = $domanda ? (float) $domanda['difficolta'] : 1.0;

        $punti = 0;

        if ($corretta) {

            $puntiBase = $puntata * $difficolta * $fattoreVelocita;

            $bonusPrimo = 0;

            if ($bonusPrimoAttivo) {
                $bonusPrimo = $puntata * $coeffBonusPrimo;
            }

            $punti = (int) round($puntiBase + $bonusPrimo);

        } else {

            $punti = -$puntata;
        }

        // Aggiorna capitale
        $update = $this->pdo->prepare(
            "UPDATE partecipazioni
             SET capitale_attuale = capitale_attuale + :punti
             WHERE id = :id"
        );

        $update->execute([
            'punti' => $punti,
            'id' => $partecipazioneId
        ]);

        // Salva risposta
        $insert = $this->pdo->prepare(
            "INSERT INTO risposte
             (partecipazione_id, domanda_id, puntata, corretta, punti, tempo_risposta, data_risposta)
             VALUES
             (:partecipazione_id, :domanda_id, :puntata, :corretta, :punti, :tempo, :data)"
        );

        $insert->execute([
            'partecipazione_id' => $partecipazioneId,
            'domanda_id' => $domandaId,
            'puntata' => $puntata,
            'corretta' => $corretta,
            'punti' => $punti,
            'tempo' => $tempoRisposta,
            'data' => time()
        ]);

        unset($_SESSION['puntate'][$partecipazioneId]);

        return [
            'corretta' => (bool) $corretta,
            'punti' => $punti,
            'fattore_velocita' => $fattoreVelocita,
            'tempo_risposta' => $tempoRisposta
        ];
    }

    public function trova(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM partecipazioni
             WHERE id = :id LIMIT 1"
        );

        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function classifica(int $sessioneId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.nome,
                    p.capitale_attuale
             FROM partecipazioni p
             JOIN utenti u ON u.id = p.utente_id
             WHERE p.sessione_id = :sessione_id
             ORDER BY p.capitale_attuale DESC"
        );

        $stmt->execute(['sessione_id' => $sessioneId]);

        return $stmt->fetchAll();
    }
}