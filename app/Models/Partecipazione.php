<?php

namespace App\Models;

use App\Core\Database;
use App\Services\Question\ImpostoreModeService;
use App\Services\Question\QuestionModeResolver;
use App\Services\Question\Score\ScoreEngine;
use PDO;

class Partecipazione
{
    private PDO $pdo;

    private function formatTempoRispostaDisplay(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return number_format($value, 2, ',', '');
    }

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

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

    public function registraPuntata(int $partecipazioneId, int $importo): bool
    {
        $partecipazione = $this->trova($partecipazioneId);

        if (!$partecipazione) {
            return false;
        }

        if ($importo <= 0 || $importo > $partecipazione['capitale_attuale']) {
            return false;
        }

        $_SESSION['puntate'][$partecipazioneId] = $importo;

        return true;
    }

    public function registraRisposta(
        int $partecipazioneId,
        int $domandaId,
        int $opzioneId,
        ?float $tempoClient = null
    ): ?array {
        $partecipazione = $this->trova($partecipazioneId);
        if (!$partecipazione) {
            return null;
        }

        $puntata = $_SESSION['puntate'][$partecipazioneId] ?? 0;
        if ($puntata <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT corretta FROM opzioni
             WHERE id = :id LIMIT 1"
        );

        $stmt->execute(['id' => $opzioneId]);
        $opzione = $stmt->fetch();
        if (!$opzione) {
            return null;
        }

        $corretta = (int) $opzione['corretta'];

        $stmt = $this->pdo->prepare(
            "SELECT * FROM domande
             WHERE id = :id LIMIT 1"
        );

        $stmt->execute(['id' => $domandaId]);
        $domanda = $stmt->fetch();

        $difficolta = $domanda ? (float) ($domanda['difficolta'] ?? 1.0) : 1.0;
        $modeMeta = (new QuestionModeResolver())->resolveFromRow(is_array($domanda) ? $domanda : []);

        $sistema = new Sistema();

        $durata = (int) $sistema->get('durata_domanda');
        $fattoreMax = (float) $sistema->get('fattore_velocita_max');
        $bonusPrimoAttivo = (int) $sistema->get('bonus_primo_attivo');
        $coeffBonusPrimo = (float) $sistema->get('coefficiente_bonus_primo');

        $stmt = $this->pdo->prepare(
            "SELECT s.inizio_domanda, s.id as sessione_id
             FROM partecipazioni p
             JOIN sessioni s ON s.id = p.sessione_id
             WHERE p.id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $partecipazioneId]);
        $sessionData = $stmt->fetch();

        if (!$sessionData || !$sessionData['inizio_domanda']) {
            return null;
        }

        $sessioneId = (int) $sessionData['sessione_id'];
        $inizioDomanda = (float) $sessionData['inizio_domanda'];
        $modeMeta = (new ImpostoreModeService())->applyRuntimeOverride($sessioneId, $domandaId, $modeMeta);

        $tempoServer = max(0, round(microtime(true) - $inizioDomanda, 3));
        $tempoRisposta = $tempoServer;

        if ($tempoClient !== null && is_finite($tempoClient)) {
            $tempoClient = round(max(0, $tempoClient), 3);
            $deltaTempo = abs($tempoClient - $tempoServer);
            $limiteAccettabile = max(1.5, ($durata * 0.2));

            if ($tempoClient <= ($durata + 1) && $deltaTempo <= $limiteAccettabile) {
                $tempoRisposta = $tempoClient;
            }
        }

        $tempoRimanente = max(0, $durata - $tempoRisposta);

        $percentuale = $durata > 0 ? ($tempoRimanente / $durata) : 0;
        $fattoreVelocita = round($percentuale * $fattoreMax, 2);

        $bonusPrimo = 0;
        $isPrimoARispondere = false;
        $isImpostore = false;
        $bonusImpostore = 0;

        if ($bonusPrimoAttivo) {
            $check = $this->pdo->prepare(
                "SELECT COUNT(*) as totale
                 FROM risposte r
                 JOIN partecipazioni p ON p.id = r.partecipazione_id
                 WHERE r.domanda_id = :domanda_id
                 AND p.sessione_id = :sessione_id"
            );

            $check->execute([
                'domanda_id' => $domandaId,
                'sessione_id' => $sessioneId,
            ]);

            $row = $check->fetch();

            if ((int) $row['totale'] === 0) {
                $isPrimoARispondere = true;
                $bonusPrimo = (int) round($puntata * $coeffBonusPrimo);
            }
        }

        if (strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? 'CLASSIC'))) === 'IMPOSTORE') {
            $impostoreService = new ImpostoreModeService();
            $assignment = $impostoreService->getAssignment($sessioneId, $domandaId);
            $isImpostore = (int) ($assignment['impostore_partecipazione_id'] ?? 0) === $partecipazioneId;
            $bonusImpostore = $impostoreService->calculateBonus(
                $modeMeta,
                $puntata,
                (bool) $corretta,
                $isImpostore
            );
        }

        $scoreEngine = new ScoreEngine();
        $score = $scoreEngine->calculate($modeMeta['tipo_domanda'], [
            'puntata' => $puntata,
            'corretta' => (bool) $corretta,
            'difficolta' => $difficolta,
            'fattore_velocita' => $fattoreVelocita,
            'bonus_primo' => $bonusPrimo,
            'bonus_impostore' => $bonusImpostore,
            'tempo_risposta' => $tempoRisposta,
            'question_meta' => $modeMeta,
        ]);

        $punti = $score->punti;
        $vincitaDifficolta = $score->vincitaDifficolta;
        $vincitaVelocita = $score->vincitaVelocita;
        $bonusPrimoCalcolato = $score->bonusPrimo;
        $bonusImpostoreCalcolato = $score->bonusImpostore;

        $update = $this->pdo->prepare(
            "UPDATE partecipazioni
             SET capitale_attuale = capitale_attuale + :punti
             WHERE id = :id"
        );

        $update->execute([
            'punti' => $punti,
            'id' => $partecipazioneId,
        ]);

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
            'data' => time(),
        ]);

        unset($_SESSION['puntate'][$partecipazioneId]);

        $aggiornata = $this->trova($partecipazioneId);
        $capitaleFinale = (int) ($aggiornata['capitale_attuale'] ?? 0);

        return [
            'corretta' => (bool) $corretta,
            'puntata' => (int) $puntata,
            'punti' => $punti,
            'vincita_difficolta' => $vincitaDifficolta,
            'vincita_velocita' => $vincitaVelocita,
            'bonus_primo' => $bonusPrimoCalcolato,
            'bonus_impostore' => $bonusImpostoreCalcolato,
            'fattore_velocita' => $fattoreVelocita,
            'tempo_risposta' => $tempoRisposta,
            'tempo_risposta_display' => $this->formatTempoRispostaDisplay($tempoRisposta),
            'difficolta_domanda' => $difficolta,
            'primo_a_rispondere' => $isPrimoARispondere,
            'is_impostore' => $isImpostore,
            'vincita_domanda' => $punti,
            'capitale' => $capitaleFinale,
            'tipo_domanda' => $modeMeta['tipo_domanda'],
            'modalita_party' => $modeMeta['modalita_party'],
            'fase_domanda' => $modeMeta['fase_domanda'],
        ];
    }

    public function ripristinaCapitaleEliminatiFineFase(int $sessioneId): void
    {
        $ultimoConPuntiStmt = $this->pdo->prepare(
            "SELECT capitale_attuale
             FROM partecipazioni
             WHERE sessione_id = :sessione_id
               AND capitale_attuale > 0
             ORDER BY capitale_attuale ASC, id DESC
             LIMIT 1"
        );

        $ultimoConPuntiStmt->execute([
            'sessione_id' => $sessioneId,
        ]);

        $ultimoConPunti = $ultimoConPuntiStmt->fetch();

        if (!$ultimoConPunti) {
            return;
        }

        $capitaleRipristinoBase = (int) ($ultimoConPunti['capitale_attuale'] ?? 0);
        $capitaleRipristino = (int) floor($capitaleRipristinoBase * 0.25);

        if ($capitaleRipristino <= 0) {
            return;
        }

        $update = $this->pdo->prepare(
            "UPDATE partecipazioni
             SET capitale_attuale = :capitale
             WHERE sessione_id = :sessione_id
               AND capitale_attuale <= 0"
        );

        $update->execute([
            'capitale' => $capitaleRipristino,
            'sessione_id' => $sessioneId,
        ]);
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
