<?php

namespace App\Models;

use App\Core\Database;
use App\Services\Question\ImpostoreModeService;
use App\Services\Question\MemeModeService;
use App\Services\Question\QuestionRuntimeModeService;
use App\Services\Question\Score\ScoreEngine;
use PDO;

class Partecipazione
{
    private PDO $pdo;
    private static bool $risposteOptionIdEnsured = false;
    private ?string $lastError = null;

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
        $this->ensureRisposteOptionIdColumn();
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    private function loadOptionResultData(int $opzioneId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT o.corretta,
                    o.testo AS risposta_data_testo,
                    c.testo AS risposta_corretta_testo
             FROM opzioni o
             LEFT JOIN opzioni c
               ON c.domanda_id = o.domanda_id
              AND c.corretta = 1
             WHERE o.id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $opzioneId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return [
            'corretta' => (int) ($row['corretta'] ?? 0),
            'risposta_data_testo' => (string) ($row['risposta_data_testo'] ?? ''),
            'risposta_corretta_testo' => (string) ($row['risposta_corretta_testo'] ?? ''),
        ];
    }

    private function loadDomandaRow(int $domandaId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM domande
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $domandaId]);
        return $stmt->fetch() ?: null;
    }

    private function loadAnswerSessionContext(int $partecipazioneId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.inizio_domanda,
                    s.id as sessione_id,
                    s.stato,
                    sd.domanda_id AS domanda_attuale_id
             FROM partecipazioni p
             JOIN sessioni s ON s.id = p.sessione_id
             LEFT JOIN sessione_domande sd
               ON sd.sessione_id = s.id
              AND sd.posizione = s.domanda_corrente
             WHERE p.id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $partecipazioneId]);
        return $stmt->fetch() ?: null;
    }

    private function loadCurrentLiveBet(int $sessioneId, int $partecipazioneId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT importo
             FROM puntate_live
             WHERE sessione_id = :sessione_id
               AND partecipazione_id = :partecipazione_id
             LIMIT 1"
        );

        $stmt->execute([
            'sessione_id' => $sessioneId,
            'partecipazione_id' => $partecipazioneId,
        ]);

        return (int) (($stmt->fetch()['importo'] ?? 0));
    }

    private function resolveQuestionModeMeta(int $sessioneId, int $domandaId, ?array $domanda): array
    {
        return (new QuestionRuntimeModeService())->resolveFromRow($sessioneId, $domandaId, is_array($domanda) ? $domanda : []);
    }

    private function validateAnswerSessionContext(?array $sessionData, int $domandaId): ?string
    {
        if (!$sessionData || !$sessionData['inizio_domanda']) {
            return 'Domanda non attiva';
        }

        if ((string) ($sessionData['stato'] ?? '') !== 'domanda') {
            return 'Domanda non piu valida';
        }

        if ((int) ($sessionData['domanda_attuale_id'] ?? 0) !== $domandaId) {
            return 'Domanda non piu valida';
        }

        return null;
    }

    private function decorateAnswerTextsForRuntime(
        int $sessioneId,
        int $opzioneId,
        array $modeMeta,
        string $rispostaDataTesto
    ): string {
        $tipoDomanda = strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? 'CLASSIC')));
        if ($tipoDomanda !== 'MEME') {
            return $rispostaDataTesto;
        }

        $memeState = (new MemeModeService())->getRuntimeState($sessioneId);
        $memeText = trim((string) ($memeState['meme_text'] ?? ''));
        $displayWrongOptionId = (int) ($memeState['display_wrong_option_id'] ?? 0);

        if ($memeText !== '' && $displayWrongOptionId > 0 && $displayWrongOptionId === $opzioneId) {
            return $memeText;
        }

        return $rispostaDataTesto;
    }

    private function calculateAnswerScore(
        array $modeMeta,
        int $puntata,
        int $corretta,
        float $difficolta,
        float $fattoreVelocita,
        int $bonusPrimo,
        int $bonusImpostore,
        float $tempoRisposta
    ): object {
        return (new ScoreEngine())->calculate($modeMeta['tipo_domanda'], [
            'puntata' => $puntata,
            'corretta' => (bool) $corretta,
            'difficolta' => $difficolta,
            'fattore_velocita' => $fattoreVelocita,
            'bonus_primo' => $bonusPrimo,
            'bonus_impostore' => $bonusImpostore,
            'tempo_risposta' => $tempoRisposta,
            'question_meta' => $modeMeta,
        ]);
    }

    private function resolveTempoRisposta(float $inizioDomanda, int $durata, ?float $tempoClient): array
    {
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

        return [
            $tempoRisposta,
            max(0, $durata - $tempoRisposta),
        ];
    }

    private function resolveBonusPrimo(
        int $bonusPrimoAttivo,
        int $corretta,
        int $domandaId,
        int $sessioneId,
        int $puntata,
        float $coeffBonusPrimo
    ): array {
        if (!$bonusPrimoAttivo || !$corretta) {
            return [0, false];
        }

        $check = $this->pdo->prepare(
            "SELECT COUNT(*) as totale
             FROM risposte r
             JOIN partecipazioni p ON p.id = r.partecipazione_id
             WHERE r.domanda_id = :domanda_id
             AND p.sessione_id = :sessione_id
             AND r.corretta = 1"
        );

        $check->execute([
            'domanda_id' => $domandaId,
            'sessione_id' => $sessioneId,
        ]);

        $row = $check->fetch();
        if ((int) ($row['totale'] ?? 0) !== 0) {
            return [0, false];
        }

        return [(int) round($puntata * $coeffBonusPrimo), true];
    }

    private function resolveImpostoreBonus(
        array $modeMeta,
        int $sessioneId,
        int $domandaId,
        int $partecipazioneId,
        int $puntata,
        int $corretta
    ): array {
        if (strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? 'CLASSIC'))) !== 'IMPOSTORE') {
            return [false, 0];
        }

        $impostoreService = new ImpostoreModeService();
        $assignment = $impostoreService->getAssignment($sessioneId, $domandaId);
        $isImpostore = (int) ($assignment['impostore_partecipazione_id'] ?? 0) === $partecipazioneId;

        return [
            $isImpostore,
            $impostoreService->calculateBonus($modeMeta, $puntata, (bool) $corretta, $isImpostore),
        ];
    }

    private function persistAnswerOutcome(
        int $partecipazioneId,
        int $sessioneId,
        int $domandaId,
        int $opzioneId,
        int $puntata,
        int $corretta,
        int $punti,
        float $tempoRisposta
    ): int {
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
             (partecipazione_id, domanda_id, opzione_id, puntata, corretta, punti, tempo_risposta, data_risposta)
             VALUES
             (:partecipazione_id, :domanda_id, :opzione_id, :puntata, :corretta, :punti, :tempo, :data)"
        );

        $insert->execute([
            'partecipazione_id' => $partecipazioneId,
            'domanda_id' => $domandaId,
            'opzione_id' => $opzioneId,
            'puntata' => $puntata,
            'corretta' => $corretta,
            'punti' => $punti,
            'tempo' => $tempoRisposta,
            'data' => time(),
        ]);

        $deleteLive = $this->pdo->prepare(
            "DELETE FROM puntate_live
             WHERE sessione_id = :sessione_id
               AND partecipazione_id = :partecipazione_id"
        );

        $deleteLive->execute([
            'sessione_id' => $sessioneId,
            'partecipazione_id' => $partecipazioneId,
        ]);

        unset($_SESSION['puntate'][$partecipazioneId]);

        $aggiornata = $this->trova($partecipazioneId);
        return (int) ($aggiornata['capitale_attuale'] ?? 0);
    }

    private function buildResultPayload(
        int $corretta,
        int $puntata,
        int $punti,
        int $vincitaDifficolta,
        int $vincitaVelocita,
        int $bonusPrimo,
        int $bonusImpostore,
        float $fattoreVelocita,
        float $tempoRisposta,
        float $difficolta,
        bool $isPrimoARispondere,
        bool $isImpostore,
        int $capitale,
        array $modeMeta,
        string $rispostaDataTesto,
        string $rispostaCorrettaTesto
    ): array {
        return [
            'corretta' => (bool) $corretta,
            'puntata' => $puntata,
            'punti' => $punti,
            'vincita_difficolta' => $vincitaDifficolta,
            'vincita_velocita' => $vincitaVelocita,
            'bonus_primo' => $bonusPrimo,
            'bonus_impostore' => $bonusImpostore,
            'fattore_velocita' => $fattoreVelocita,
            'tempo_risposta' => $tempoRisposta,
            'tempo_risposta_display' => $this->formatTempoRispostaDisplay($tempoRisposta),
            'difficolta_domanda' => $difficolta,
            'primo_a_rispondere' => $isPrimoARispondere,
            'is_impostore' => $isImpostore,
            'vincita_domanda' => $punti,
            'capitale' => $capitale,
            'tipo_domanda' => $modeMeta['tipo_domanda'],
            'modalita_party' => $modeMeta['modalita_party'],
            'fase_domanda' => $modeMeta['fase_domanda'],
            'risposta_data_testo' => $rispostaDataTesto,
            'risposta_corretta_testo' => $rispostaCorrettaTesto,
        ];
    }

    private function loadAnswerExecutionContext(
        int $partecipazioneId,
        int $domandaId,
        int $opzioneId
    ): ?array {
        $partecipazione = $this->trova($partecipazioneId);
        if (!$partecipazione) {
            $this->lastError = 'Partecipazione non trovata';
            return null;
        }

        $opzione = $this->loadOptionResultData($opzioneId);
        if (!$opzione) {
            $this->lastError = 'Opzione non valida';
            return null;
        }

        $domanda = $this->loadDomandaRow($domandaId);
        $sessionData = $this->loadAnswerSessionContext($partecipazioneId);
        $validationError = $this->validateAnswerSessionContext($sessionData, $domandaId);
        if ($validationError !== null) {
            $this->lastError = $validationError;
            return null;
        }

        $sessioneId = (int) ($sessionData['sessione_id'] ?? 0);
        $existingResult = $this->loadExistingResult($partecipazioneId, $domandaId);
        if ($existingResult !== null) {
            return [
                'existing_result' => $existingResult,
            ];
        }

        $puntata = $this->loadCurrentLiveBet($sessioneId, $partecipazioneId);
        if ($puntata <= 0) {
            $this->lastError = 'Puntata non trovata per la domanda corrente';
            return null;
        }

        return [
            'partecipazione' => $partecipazione,
            'opzione' => $opzione,
            'domanda' => $domanda,
            'session_data' => $sessionData,
            'sessione_id' => $sessioneId,
            'puntata' => $puntata,
        ];
    }

    private function loadAnswerSystemSettings(): array
    {
        $sistema = new Sistema();

        return [
            'durata' => (int) $sistema->get('durata_domanda'),
            'fattore_max' => (float) $sistema->get('fattore_velocita_max'),
            'bonus_primo_attivo' => (int) $sistema->get('bonus_primo_attivo'),
            'coeff_bonus_primo' => (float) $sistema->get('coefficiente_bonus_primo'),
        ];
    }

    private function resolveAnswerRuntimeContext(
        int $sessioneId,
        int $domandaId,
        ?array $domanda,
        int $opzioneId,
        string $rispostaDataTesto
    ): array {
        $modeMeta = $this->resolveQuestionModeMeta($sessioneId, $domandaId, $domanda);
        $decoratedText = $this->decorateAnswerTextsForRuntime(
            $sessioneId,
            $opzioneId,
            $modeMeta,
            $rispostaDataTesto
        );

        return [
            'mode_meta' => $modeMeta,
            'risposta_data_testo' => $decoratedText,
        ];
    }

    private function resolveAnswerBonuses(
        array $settings,
        int $corretta,
        int $domandaId,
        int $sessioneId,
        int $partecipazioneId,
        int $puntata,
        array $modeMeta
    ): array {
        [$bonusPrimo, $isPrimoARispondere] = $this->resolveBonusPrimo(
            (int) ($settings['bonus_primo_attivo'] ?? 0),
            $corretta,
            $domandaId,
            $sessioneId,
            $puntata,
            (float) ($settings['coeff_bonus_primo'] ?? 0)
        );

        [$isImpostore, $bonusImpostore] = $this->resolveImpostoreBonus(
            $modeMeta,
            $sessioneId,
            $domandaId,
            $partecipazioneId,
            $puntata,
            $corretta
        );

        return [
            'bonus_primo' => $bonusPrimo,
            'is_primo' => $isPrimoARispondere,
            'is_impostore' => $isImpostore,
            'bonus_impostore' => $bonusImpostore,
        ];
    }

    private function calculateAnswerTiming(array $sessionData, array $settings, ?float $tempoClient): array
    {
        $inizioDomanda = (float) ($sessionData['inizio_domanda'] ?? 0);
        $durata = (int) ($settings['durata'] ?? 0);
        [$tempoRisposta, $tempoRimanente] = $this->resolveTempoRisposta($inizioDomanda, $durata, $tempoClient);
        $fattoreMax = (float) ($settings['fattore_max'] ?? 0);
        $percentuale = $durata > 0 ? ($tempoRimanente / $durata) : 0;

        return [
            'tempo_risposta' => $tempoRisposta,
            'tempo_rimanente' => $tempoRimanente,
            'fattore_velocita' => round($percentuale * $fattoreMax, 2),
        ];
    }

    private function loadMissingAnswerRows(int $sessioneId, int $domandaId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT pl.partecipazione_id, pl.importo
             FROM puntate_live pl
             LEFT JOIN risposte r
               ON r.partecipazione_id = pl.partecipazione_id
              AND r.domanda_id = :domanda_id
             WHERE pl.sessione_id = :sessione_id
               AND pl.importo > 0
               AND r.id IS NULL"
        );

        $stmt->execute([
            'sessione_id' => $sessioneId,
            'domanda_id' => $domandaId,
        ]);

        return $stmt->fetchAll() ?: [];
    }

    private function resolveMissingAnswerTempo(): float
    {
        $durataStmt = $this->pdo->query(
            "SELECT valore
             FROM configurazioni_sistema
             WHERE chiave = 'durata_domanda'
             LIMIT 1"
        );

        $durataRow = $durataStmt ? $durataStmt->fetch() : null;
        return max(0, (float) ($durataRow['valore'] ?? 0));
    }

    private function persistMissingAnswers(
        int $sessioneId,
        int $domandaId,
        array $rows,
        float $tempoRispostaAssenza
    ): int {
        $processed = 0;
        $started = false;

        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $started = true;
            }

            $updateCapitale = $this->pdo->prepare(
                "UPDATE partecipazioni
                 SET capitale_attuale = capitale_attuale - :puntata
                 WHERE id = :partecipazione_id"
            );

            $insertRisposta = $this->pdo->prepare(
                "INSERT INTO risposte
                 (partecipazione_id, domanda_id, opzione_id, puntata, corretta, punti, tempo_risposta, data_risposta)
                 VALUES
                 (:partecipazione_id, :domanda_id, NULL, :puntata, 0, :punti, :tempo_risposta, :data)"
            );

            $deleteLive = $this->pdo->prepare(
                "DELETE FROM puntate_live
                 WHERE sessione_id = :sessione_id
                   AND partecipazione_id = :partecipazione_id"
            );

            foreach ($rows as $row) {
                $partecipazioneId = (int) ($row['partecipazione_id'] ?? 0);
                $puntata = (int) ($row['importo'] ?? 0);
                if ($partecipazioneId <= 0 || $puntata <= 0) {
                    continue;
                }

                $updateCapitale->execute([
                    'puntata' => $puntata,
                    'partecipazione_id' => $partecipazioneId,
                ]);

                $insertRisposta->execute([
                    'partecipazione_id' => $partecipazioneId,
                    'domanda_id' => $domandaId,
                    'puntata' => $puntata,
                    'punti' => -$puntata,
                    'tempo_risposta' => $tempoRispostaAssenza,
                    'data' => time(),
                ]);

                $deleteLive->execute([
                    'sessione_id' => $sessioneId,
                    'partecipazione_id' => $partecipazioneId,
                ]);

                unset($_SESSION['puntate'][$partecipazioneId]);
                $processed++;
            }

            if ($started) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $processed;
    }

    private function loadExistingResult(int $partecipazioneId, int $domandaId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.opzione_id,
                    r.corretta,
                    r.puntata,
                    r.punti,
                    r.tempo_risposta,
                    p.capitale_attuale AS capitale,
                    d.difficolta AS difficolta_domanda,
                    o_sel.testo AS risposta_data_testo,
                    o_ok.testo AS risposta_corretta_testo
             FROM risposte r
             JOIN partecipazioni p ON p.id = r.partecipazione_id
             LEFT JOIN domande d ON d.id = r.domanda_id
             LEFT JOIN opzioni o_sel ON o_sel.id = r.opzione_id
             LEFT JOIN opzioni o_ok
               ON o_ok.domanda_id = r.domanda_id
              AND o_ok.corretta = 1
             WHERE r.partecipazione_id = :partecipazione_id
               AND r.domanda_id = :domanda_id
             ORDER BY r.id DESC
             LIMIT 1"
        );

        $stmt->execute([
            'partecipazione_id' => $partecipazioneId,
            'domanda_id' => $domandaId,
        ]);

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $tempoRisposta = $row['tempo_risposta'] === null ? null : (float) $row['tempo_risposta'];

        return [
            'corretta' => (bool) ((int) ($row['corretta'] ?? 0)),
            'puntata' => (int) ($row['puntata'] ?? 0),
            'punti' => (int) ($row['punti'] ?? 0),
            'vincita_difficolta' => 0,
            'vincita_velocita' => 0,
            'bonus_primo' => 0,
            'bonus_impostore' => 0,
            'fattore_velocita' => 0,
            'tempo_risposta' => $tempoRisposta,
            'tempo_risposta_display' => $this->formatTempoRispostaDisplay($tempoRisposta),
            'difficolta_domanda' => $row['difficolta_domanda'] === null ? 0 : (float) $row['difficolta_domanda'],
            'primo_a_rispondere' => false,
            'is_impostore' => false,
            'vincita_domanda' => (int) ($row['punti'] ?? 0),
            'capitale' => (int) ($row['capitale'] ?? 0),
            'tipo_domanda' => null,
            'modalita_party' => null,
            'fase_domanda' => null,
            'risposta_data_testo' => (string) ($row['risposta_data_testo'] ?? ''),
            'risposta_corretta_testo' => (string) ($row['risposta_corretta_testo'] ?? ''),
        ];
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
        $this->lastError = null;

        $executionContext = $this->loadAnswerExecutionContext($partecipazioneId, $domandaId, $opzioneId);
        if ($executionContext === null) {
            return null;
        }
        if (isset($executionContext['existing_result'])) {
            return $executionContext['existing_result'];
        }

        $opzione = $executionContext['opzione'];
        $domanda = $executionContext['domanda'];
        $sessionData = $executionContext['session_data'];
        $sessioneId = (int) $executionContext['sessione_id'];
        $puntata = (int) $executionContext['puntata'];
        $corretta = (int) ($opzione['corretta'] ?? 0);
        $rispostaDataTesto = (string) ($opzione['risposta_data_testo'] ?? '');
        $rispostaCorrettaTesto = (string) ($opzione['risposta_corretta_testo'] ?? '');
        $difficolta = $domanda ? (float) ($domanda['difficolta'] ?? 1.0) : 1.0;

        $settings = $this->loadAnswerSystemSettings();
        $runtimeContext = $this->resolveAnswerRuntimeContext(
            $sessioneId,
            $domandaId,
            $domanda,
            $opzioneId,
            $rispostaDataTesto
        );
        $modeMeta = $runtimeContext['mode_meta'];
        $rispostaDataTesto = $runtimeContext['risposta_data_testo'];

        $timing = $this->calculateAnswerTiming($sessionData, $settings, $tempoClient);
        $tempoRisposta = (float) $timing['tempo_risposta'];
        $fattoreVelocita = (float) $timing['fattore_velocita'];

        $bonuses = $this->resolveAnswerBonuses(
            $settings,
            $corretta,
            $domandaId,
            $sessioneId,
            $partecipazioneId,
            $puntata,
            $modeMeta
        );
        $bonusPrimo = (int) $bonuses['bonus_primo'];
        $isPrimoARispondere = (bool) $bonuses['is_primo'];
        $isImpostore = (bool) $bonuses['is_impostore'];
        $bonusImpostore = (int) $bonuses['bonus_impostore'];

        $score = $this->calculateAnswerScore(
            $modeMeta,
            $puntata,
            $corretta,
            $difficolta,
            $fattoreVelocita,
            $bonusPrimo,
            $bonusImpostore,
            $tempoRisposta
        );

        $punti = $score->punti;
        $vincitaDifficolta = $score->vincitaDifficolta;
        $vincitaVelocita = $score->vincitaVelocita;
        $bonusPrimoCalcolato = $score->bonusPrimo;
        $bonusImpostoreCalcolato = $score->bonusImpostore;

        $capitaleFinale = $this->persistAnswerOutcome(
            $partecipazioneId,
            $sessioneId,
            $domandaId,
            $opzioneId,
            $puntata,
            $corretta,
            $punti,
            $tempoRisposta
        );

        return $this->buildResultPayload(
            $corretta,
            $puntata,
            $punti,
            $vincitaDifficolta,
            $vincitaVelocita,
            $bonusPrimoCalcolato,
            $bonusImpostoreCalcolato,
            $fattoreVelocita,
            $tempoRisposta,
            $difficolta,
            $isPrimoARispondere,
            $isImpostore,
            $capitaleFinale,
            $modeMeta,
            $rispostaDataTesto,
            $rispostaCorrettaTesto
        );
    }

    public function registraAssenzeRisposta(int $sessioneId, int $domandaId): int
    {
        if ($sessioneId <= 0 || $domandaId <= 0) {
            return 0;
        }

        $this->ensurePuntateLiveTable();

        $rows = $this->loadMissingAnswerRows($sessioneId, $domandaId);
        if (!$rows) {
            return 0;
        }

        return $this->persistMissingAnswers(
            $sessioneId,
            $domandaId,
            $rows,
            $this->resolveMissingAnswerTempo()
        );
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

    private function ensurePuntateLiveTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS puntate_live (
                sessione_id INT NOT NULL,
                partecipazione_id INT NOT NULL,
                importo INT NOT NULL,
                aggiornato_il INT NOT NULL,
                PRIMARY KEY (sessione_id, partecipazione_id),
                KEY idx_sessione (sessione_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function ensureRisposteOptionIdColumn(): void
    {
        if (self::$risposteOptionIdEnsured) {
            return;
        }

        $stmt = $this->pdo->query("
            SELECT COUNT(*) AS totale
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'risposte'
              AND COLUMN_NAME = 'opzione_id'
        ");

        $exists = (int) (($stmt ? $stmt->fetch()['totale'] : 0) ?? 0) > 0;

        if (!$exists) {
            $this->pdo->exec("ALTER TABLE risposte ADD COLUMN opzione_id INT NULL AFTER domanda_id");
        }

        self::$risposteOptionIdEnsured = true;
    }
}
