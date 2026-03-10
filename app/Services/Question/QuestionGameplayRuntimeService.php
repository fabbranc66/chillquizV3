<?php

namespace App\Services\Question;

use App\Models\Sistema;

final class QuestionGameplayRuntimeService
{
    public function clearRuntimeModes(int $sessioneId): void
    {
        (new ImpostoreModeService())->clearRuntimeState($sessioneId);
        (new MemeModeService())->clearRuntimeState($sessioneId);
        (new ImagePartyModeService())->clearRuntimeState($sessioneId);
        (new FadeModeService())->clearRuntimeState($sessioneId);
        (new SarabandaAudioModeService())->clearRuntimeState($sessioneId);
    }

    public function resolveModeMeta(int $sessioneId, array $domandaRow): array
    {
        $domandaId = (int) ($domandaRow['id'] ?? 0);
        return (new QuestionRuntimeModeService())->resolveFromRow($sessioneId, $domandaId, $domandaRow);
    }

    public function prepareRuntimeQuestionModes(int $sessioneId, array $domandaRow, array $modeMeta): void
    {
        $domandaId = (int) ($domandaRow['id'] ?? 0);
        $tipoDomanda = strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? QuestionMode::CLASSIC)));

        if ($tipoDomanda === QuestionMode::IMPOSTORE) {
            (new ImpostoreModeService())->assignForQuestion($sessioneId, $domandaId);
        }

        if ($tipoDomanda === QuestionMode::MEME) {
            (new MemeModeService())->prepareForQuestion($sessioneId, $domandaId);
        }
    }

    public function resolveQuestionStartTimestamp(array $domandaRow, array $modeMeta): ?float
    {
        $tipoDomanda = strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? QuestionMode::CLASSIC)));
        $hasAudio = trim((string) ($domandaRow['media_audio_path'] ?? '')) !== '';

        if ($tipoDomanda === QuestionMode::SARABANDA && $hasAudio) {
            return null;
        }

        return round(microtime(true), 3);
    }

    public function resolveQuestionInitialState(array $domandaRow, array $modeMeta): string
    {
        $tipoDomanda = strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? QuestionMode::CLASSIC)));
        $hasAudio = trim((string) ($domandaRow['media_audio_path'] ?? '')) !== '';

        if ($tipoDomanda === QuestionMode::SARABANDA && $hasAudio) {
            return 'preview';
        }

        return 'domanda';
    }

    public function buildClassificaScoreContext(
        int $sessioneId,
        int $domandaId,
        array $domandaRow,
        Sistema $sistema
    ): array {
        $modeMeta = $this->resolveModeMeta($sessioneId, $domandaRow);
        $runtimeModeService = new QuestionRuntimeModeService();

        return [
            'durata_domanda' => (int) ($sistema->get('durata_domanda') ?? 0),
            'fattore_velocita_max' => (float) ($sistema->get('fattore_velocita_max') ?? 1),
            'bonus_primo_attivo' => (int) ($sistema->get('bonus_primo_attivo') ?? 0) === 1,
            'coefficiente_bonus_primo' => (float) ($sistema->get('coefficiente_bonus_primo') ?? 0),
            'mode_meta' => $modeMeta,
            'runtime_state' => $runtimeModeService->buildRuntimeState($sessioneId, $domandaId, $modeMeta),
            'impostore_service' => new ImpostoreModeService(),
        ];
    }

    public function calculateClassificaScoreParts(array $row, array $context): array
    {
        $puntata = (int) ($row['ultima_puntata'] ?? 0);
        $difficolta = (float) ($row['difficolta_domanda'] ?? 1.0);
        $tempoRisposta = $row['tempo_risposta'] === null ? null : (float) $row['tempo_risposta'];
        $durataDomanda = (int) ($context['durata_domanda'] ?? 0);
        $fattoreVelocitaMax = (float) ($context['fattore_velocita_max'] ?? 1);
        $runtimeState = is_array($context['runtime_state'] ?? null) ? $context['runtime_state'] : [];
        $modeMeta = is_array($context['mode_meta'] ?? null) ? $context['mode_meta'] : [];
        $impostoreService = $context['impostore_service'] ?? new ImpostoreModeService();

        $fattoreVelocita = 0.0;
        if ($tempoRisposta !== null && $durataDomanda > 0) {
            $tempoRimanente = max(0, $durataDomanda - $tempoRisposta);
            $fattoreVelocita = round(($tempoRimanente / $durataDomanda) * $fattoreVelocitaMax, 2);
        }

        $vincitaDifficolta = 0;
        $vincitaVelocita = 0;
        $bonusPrimo = 0;
        $bonusImpostore = 0;
        $vincitaDomanda = null;

        if (($row['esito'] ?? null) === 'corretta') {
            $vincitaDifficolta = (int) round($puntata * $difficolta);
            $vincitaVelocita = (int) round($puntata * $fattoreVelocita);

            if (!empty($context['bonus_primo_attivo']) && !empty($row['primo_a_rispondere'])) {
                $bonusPrimo = (int) round($puntata * (float) ($context['coefficiente_bonus_primo'] ?? 0));
            }

            if (
                !empty($runtimeState['is_impostore_mode'])
                && (int) ($runtimeState['impostore_partecipazione_id'] ?? 0) > 0
                && (int) ($row['partecipazione_id'] ?? 0) === (int) ($runtimeState['impostore_partecipazione_id'] ?? 0)
            ) {
                $bonusImpostore = $impostoreService->calculateBonus($modeMeta, $puntata, true, true);
            }

            $vincitaDomanda = $vincitaDifficolta + $vincitaVelocita + $bonusPrimo + $bonusImpostore;
        } elseif (($row['esito'] ?? null) === 'errata') {
            $vincitaDomanda = -$puntata;
        }

        return [
            'fattore_velocita' => $fattoreVelocita,
            'vincita_difficolta' => $vincitaDifficolta,
            'vincita_velocita' => $vincitaVelocita,
            'bonus_primo' => $bonusPrimo,
            'bonus_impostore' => $bonusImpostore,
            'vincita_domanda' => $vincitaDomanda,
        ];
    }
}
