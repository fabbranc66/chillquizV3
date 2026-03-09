<?php

namespace App\Services\Question;

final class QuestionRuntimeModeService
{
    public function resolveFromRow(int $sessioneId, int $domandaId, array $row): array
    {
        $modeMeta = (new QuestionModeResolver())->resolveFromRow($row);
        $modeMeta = (new ImpostoreModeService())->applyRuntimeOverride($sessioneId, $domandaId, $modeMeta);
        return (new MemeModeService())->applyRuntimeOverride($sessioneId, $domandaId, $modeMeta);
    }

    public function buildRuntimeState(int $sessioneId, int $domandaId, array $modeMeta): array
    {
        $tipoDomanda = strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? QuestionMode::CLASSIC)));
        $isImpostoreMode = $tipoDomanda === QuestionMode::IMPOSTORE;
        $isMemeMode = $tipoDomanda === QuestionMode::MEME;

        $memeState = $isMemeMode
            ? ((new MemeModeService())->getRuntimeState($sessioneId) ?? [])
            : [];
        $assignment = $isImpostoreMode
            ? ((new ImpostoreModeService())->getAssignment($sessioneId, $domandaId) ?? [])
            : [];

        return [
            'tipo_domanda' => $tipoDomanda,
            'is_impostore_mode' => $isImpostoreMode,
            'is_meme_mode' => $isMemeMode,
            'meme_text' => trim((string) ($memeState['meme_text'] ?? '')),
            'meme_display_wrong_option_id' => (int) ($memeState['display_wrong_option_id'] ?? 0),
            'impostore_assignment' => $assignment,
            'impostore_partecipazione_id' => (int) ($assignment['impostore_partecipazione_id'] ?? 0),
        ];
    }
}
