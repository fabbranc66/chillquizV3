<?php

namespace App\Services\Sessione\Traits;

use App\Models\Sistema;
use App\Services\Question\ImpostoreModeService;
use App\Services\Question\MemeModeService;
use App\Services\Question\QuestionModeResolver;

trait ClassificaTrait
{
    private static bool $risposteOptionIdEnsured = false;

    private function formatTempoRispostaDisplay(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return number_format($value, 2, ',', '');
    }

    private function resolveCurrentModeMeta(int $domandaId): array
    {
        $domandaCorrenteRow = $this->loadDomandaCorrenteRow($domandaId);
        $modeMeta = (new QuestionModeResolver())->resolveFromRow($domandaCorrenteRow);
        $modeMeta = (new ImpostoreModeService())->applyRuntimeOverride($this->sessioneId, $domandaId, $modeMeta);
        return (new MemeModeService())->applyRuntimeOverride($this->sessioneId, $domandaId, $modeMeta);
    }

    private function loadClassificaRows(int $domandaId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                p.id AS partecipazione_id,
                p.utente_id,
                u.nome,
                p.capitale_attuale,
                COALESCE(pl.importo, r_corrente.puntata, 0) AS ultima_puntata,
                r_corrente.id AS risposta_id,
                r_corrente.opzione_id AS risposta_opzione_id,
                r_corrente.corretta AS esito_corretta,
                r_corrente.tempo_risposta,
                r_corrente.punti AS vincita_domanda_raw,
                d_corrente.difficolta AS difficolta_domanda,
                primo.partecipazione_id AS primo_partecipazione_id,
                o_risposta.testo AS risposta_data_testo,
                o_corretta.testo AS risposta_corretta_testo
             FROM partecipazioni p
             JOIN utenti u ON u.id = p.utente_id
             LEFT JOIN puntate_live pl
                ON pl.sessione_id = p.sessione_id
               AND pl.partecipazione_id = p.id
             LEFT JOIN (
                SELECT r1.id, r1.partecipazione_id, r1.opzione_id, r1.puntata, r1.corretta, r1.tempo_risposta, r1.punti
                FROM risposte r1
                INNER JOIN (
                    SELECT partecipazione_id, MAX(id) AS max_id
                    FROM risposte
                    WHERE domanda_id = :domanda_id_risposte
                    GROUP BY partecipazione_id
                ) r2 ON r2.max_id = r1.id
             ) r_corrente ON r_corrente.partecipazione_id = p.id
             LEFT JOIN domande d_corrente ON d_corrente.id = :domanda_id_domanda
             LEFT JOIN opzioni o_risposta ON o_risposta.id = r_corrente.opzione_id
             LEFT JOIN opzioni o_corretta
                ON o_corretta.domanda_id = d_corrente.id
               AND o_corretta.corretta = 1
             LEFT JOIN (
                SELECT r0.partecipazione_id
                FROM risposte r0
                WHERE r0.domanda_id = :domanda_id_primo
                  AND r0.corretta = 1
                ORDER BY r0.tempo_risposta ASC, r0.id ASC
                LIMIT 1
             ) primo ON 1 = 1
             WHERE p.sessione_id = :sessione_id
             ORDER BY
                CASE WHEN r_corrente.tempo_risposta IS NULL THEN 1 ELSE 0 END ASC,
                r_corrente.tempo_risposta ASC,
                p.capitale_attuale DESC"
        );

        $stmt->execute([
            'domanda_id_risposte' => $domandaId,
            'domanda_id_domanda' => $domandaId,
            'domanda_id_primo' => $domandaId,
            'sessione_id' => $this->sessioneId,
        ]);

        return $stmt->fetchAll();
    }

    private function enrichClassificaRow(
        array $row,
        int $durataDomanda,
        float $fattoreVelocitaMax,
        bool $bonusPrimoAttivo,
        float $coefficienteBonusPrimo,
        bool $isImpostoreMode,
        bool $isMemeMode,
        string $memeText,
        int $memeDisplayWrongOptionId,
        int $impostorePartecipazioneId,
        ImpostoreModeService $impostoreService,
        array $modeMeta
    ): array {
        $row['ultima_puntata'] = (int) ($row['ultima_puntata'] ?? 0);
        $row['capitale_attuale'] = (int) ($row['capitale_attuale'] ?? 0);
        $row['esito'] = ($row['esito_corretta'] === null)
            ? null
            : ((int) $row['esito_corretta'] === 1 ? 'corretta' : 'errata');
        $row['tempo_risposta'] = $row['tempo_risposta'] === null ? null : round((float) $row['tempo_risposta'], 3);
        $row['difficolta_domanda'] = $row['difficolta_domanda'] === null ? null : (float) $row['difficolta_domanda'];
        $row['durata_domanda'] = $durataDomanda;
        $row['primo_a_rispondere'] = isset($row['primo_partecipazione_id'])
            ? ((int) $row['primo_partecipazione_id'] === (int) $row['partecipazione_id'])
            : false;
        $row['risposta_data_testo'] = (string) ($row['risposta_data_testo'] ?? '');
        $row['risposta_corretta_testo'] = (string) ($row['risposta_corretta_testo'] ?? '');
        $row['is_meme_choice'] = $isMemeMode
            && $memeText !== ''
            && $memeDisplayWrongOptionId > 0
            && (int) ($row['risposta_opzione_id'] ?? 0) === $memeDisplayWrongOptionId;
        if ($row['is_meme_choice']) {
            $row['risposta_data_testo'] = $memeText;
        }
        $row['meme_text'] = $isMemeMode ? $memeText : '';

        $puntata = (int) $row['ultima_puntata'];
        $difficolta = (float) ($row['difficolta_domanda'] ?? 1.0);
        $tempoRisposta = $row['tempo_risposta'] === null ? null : (float) $row['tempo_risposta'];
        $vincitaDomandaRaw = $row['vincita_domanda_raw'] === null ? null : (int) $row['vincita_domanda_raw'];

        $fattoreVelocita = 0.0;
        if ($tempoRisposta !== null && $durataDomanda > 0) {
            $tempoRimanente = max(0, $durataDomanda - $tempoRisposta);
            $fattoreVelocita = round(($tempoRimanente / $durataDomanda) * $fattoreVelocitaMax, 2);
        }

        $vincitaDifficolta = 0;
        $vincitaVelocita = 0;
        $bonusPrimo = 0;
        $bonusImpostore = 0;
        $vincitaDomandaCalcolata = null;

        if ($row['esito'] === 'corretta') {
            $vincitaDifficolta = (int) round($puntata * $difficolta);
            $vincitaVelocita = (int) round($puntata * $fattoreVelocita);

            if ($bonusPrimoAttivo && !empty($row['primo_a_rispondere'])) {
                $bonusPrimo = (int) round($puntata * $coefficienteBonusPrimo);
            }

            if ($isImpostoreMode && $impostorePartecipazioneId > 0 && (int) $row['partecipazione_id'] === $impostorePartecipazioneId) {
                $bonusImpostore = $impostoreService->calculateBonus($modeMeta, $puntata, true, true);
            }

            $vincitaDomandaCalcolata = $vincitaDifficolta + $vincitaVelocita + $bonusPrimo + $bonusImpostore;
        } elseif ($row['esito'] === 'errata') {
            $vincitaDomandaCalcolata = -$puntata;
        }

        if (
            $vincitaDomandaCalcolata !== null
            && $vincitaDomandaRaw !== null
            && $vincitaDomandaCalcolata !== $vincitaDomandaRaw
        ) {
            $delta = $vincitaDomandaCalcolata - $vincitaDomandaRaw;
            $this->allineaPunteggioPersistito(
                (int) ($row['risposta_id'] ?? 0),
                (int) ($row['partecipazione_id'] ?? 0),
                $vincitaDomandaCalcolata,
                $delta
            );
            $row['capitale_attuale'] += $delta;
        }

        $row['vincita_domanda'] = $vincitaDomandaCalcolata;
        $row['fattore_velocita'] = $fattoreVelocita;
        $row['vincita_difficolta'] = $vincitaDifficolta;
        $row['vincita_velocita'] = $vincitaVelocita;
        $row['bonus_primo'] = $bonusPrimo;
        $row['bonus_impostore'] = $bonusImpostore;
        $row['is_impostore'] = $isImpostoreMode && $impostorePartecipazioneId > 0
            ? ((int) $row['partecipazione_id'] === $impostorePartecipazioneId)
            : false;
        $row['tempo_risposta_display'] = $this->formatTempoRispostaDisplay($tempoRisposta);

        unset($row['esito_corretta'], $row['primo_partecipazione_id'], $row['vincita_domanda_raw'], $row['risposta_id']);

        return $row;
    }

    public function classifica(): array
    {
        $this->ensureRisposteOptionIdColumn();
        $this->ensurePuntateLiveTable();

        $sistema = new Sistema();

        $domandaId = $this->domandaCorrenteId();
        $durataDomanda = (int) ($sistema->get('durata_domanda') ?? 0);
        $fattoreVelocitaMax = (float) ($sistema->get('fattore_velocita_max') ?? 1);
        $bonusPrimoAttivo = (int) ($sistema->get('bonus_primo_attivo') ?? 0) === 1;
        $coefficienteBonusPrimo = (float) ($sistema->get('coefficiente_bonus_primo') ?? 0);
        $modeMeta = $this->resolveCurrentModeMeta($domandaId);
        $isImpostoreMode = strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? 'CLASSIC'))) === 'IMPOSTORE';
        $isMemeMode = strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? 'CLASSIC'))) === 'MEME';
        $memeText = '';
        $memeDisplayWrongOptionId = 0;
        if ($isMemeMode) {
            $memeState = (new MemeModeService())->getRuntimeState($this->sessioneId);
            $memeText = trim((string) ($memeState['meme_text'] ?? ''));
            $memeDisplayWrongOptionId = (int) ($memeState['display_wrong_option_id'] ?? 0);
        }
        $impostoreService = new ImpostoreModeService();
        $assignment = $isImpostoreMode ? $impostoreService->getAssignment($this->sessioneId, $domandaId) : null;
        $impostorePartecipazioneId = (int) ($assignment['impostore_partecipazione_id'] ?? 0);
        $rows = $this->loadClassificaRows($domandaId);

        foreach ($rows as $index => $row) {
            $rows[$index] = $this->enrichClassificaRow(
                $row,
                $durataDomanda,
                $fattoreVelocitaMax,
                $bonusPrimoAttivo,
                $coefficienteBonusPrimo,
                $isImpostoreMode,
                $isMemeMode,
                $memeText,
                $memeDisplayWrongOptionId,
                $impostorePartecipazioneId,
                $impostoreService,
                $modeMeta
            );
        }

        return $rows;
    }

    private function loadDomandaCorrenteRow(int $domandaId): array
    {
        if ($domandaId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare("SELECT * FROM domande WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $domandaId]);
        return $stmt->fetch() ?: [];
    }

    private function allineaPunteggioPersistito(
        int $rispostaId,
        int $partecipazioneId,
        int $puntiCorretti,
        int $delta
    ): void {
        if ($rispostaId <= 0 || $partecipazioneId <= 0 || $delta === 0) {
            return;
        }

        $started = false;

        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $started = true;
            }

            $updateRisposta = $this->pdo->prepare(
                "UPDATE risposte
                 SET punti = :punti
                 WHERE id = :id"
            );

            $updateRisposta->execute([
                'punti' => $puntiCorretti,
                'id' => $rispostaId,
            ]);

            $updatePartecipazione = $this->pdo->prepare(
                "UPDATE partecipazioni
                 SET capitale_attuale = capitale_attuale + :delta
                 WHERE id = :id"
            );

            $updatePartecipazione->execute([
                'delta' => $delta,
                'id' => $partecipazioneId,
            ]);

            if ($started) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
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
