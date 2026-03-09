<?php

namespace App\Services\Question;

final class QuestionRuntimeModeService
{
    private function normalizeExclusiveImageEffectState(int $sessioneId, int $domandaId): void
    {
        if ($sessioneId <= 0 || $domandaId <= 0) {
            return;
        }

        $imagePartyService = new ImagePartyModeService();
        $fadeService = new FadeModeService();

        $imagePartyEnabled = $imagePartyService->isEnabledForQuestion($sessioneId, $domandaId);
        $fadeEnabled = $fadeService->isEnabledForQuestion($sessioneId, $domandaId);

        if (!$imagePartyEnabled || !$fadeEnabled) {
            return;
        }

        $fadeState = $fadeService->getRuntimeState($sessioneId) ?? [];
        $imagePartyState = $imagePartyService->getRuntimeState($sessioneId) ?? [];
        $fadeUpdatedAt = (float) ($fadeState['updated_at'] ?? 0);
        $imagePartyUpdatedAt = (float) ($imagePartyState['updated_at'] ?? 0);

        if ($fadeUpdatedAt >= $imagePartyUpdatedAt) {
            $imagePartyService->clearRuntimeState($sessioneId);
            return;
        }

        $fadeService->clearRuntimeState($sessioneId);
    }

    public function resolveFromRow(int $sessioneId, int $domandaId, array $row): array
    {
        $this->normalizeExclusiveImageEffectState($sessioneId, $domandaId);
        $modeMeta = (new QuestionModeResolver())->resolveFromRow($row);
        $modeMeta = (new ImpostoreModeService())->applyRuntimeOverride($sessioneId, $domandaId, $modeMeta);
        $modeMeta = (new MemeModeService())->applyRuntimeOverride($sessioneId, $domandaId, $modeMeta);
        $modeMeta = (new ImagePartyModeService())->applyRuntimeOverride($sessioneId, $domandaId, $modeMeta);
        return (new FadeModeService())->applyRuntimeOverride($sessioneId, $domandaId, $modeMeta);
    }

    public function buildRuntimeState(int $sessioneId, int $domandaId, array $modeMeta): array
    {
        $tipoDomanda = strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? QuestionMode::CLASSIC)));
        $isImpostoreMode = $tipoDomanda === QuestionMode::IMPOSTORE;
        $isMemeMode = $tipoDomanda === QuestionMode::MEME;
        $isImagePartyMode = $tipoDomanda === QuestionMode::IMAGE_PARTY;
        $isFadeMode = $tipoDomanda === QuestionMode::FADE;

        $memeState = $isMemeMode
            ? ((new MemeModeService())->getRuntimeState($sessioneId) ?? [])
            : [];
        $imagePartyState = $isImagePartyMode
            ? ((new ImagePartyModeService())->getRuntimeState($sessioneId) ?? [])
            : [];
        $fadeState = $isFadeMode
            ? ((new FadeModeService())->getRuntimeState($sessioneId) ?? [])
            : [];
        $assignment = $isImpostoreMode
            ? ((new ImpostoreModeService())->getAssignment($sessioneId, $domandaId) ?? [])
            : [];

        return [
            'tipo_domanda' => $tipoDomanda,
            'is_impostore_mode' => $isImpostoreMode,
            'is_meme_mode' => $isMemeMode,
            'is_image_party_mode' => $isImagePartyMode,
            'is_fade_mode' => $isFadeMode,
            'meme_text' => trim((string) ($memeState['meme_text'] ?? '')),
            'meme_display_wrong_option_id' => (int) ($memeState['display_wrong_option_id'] ?? 0),
            'image_party_state' => $imagePartyState,
            'fade_state' => $fadeState,
            'impostore_assignment' => $assignment,
            'impostore_partecipazione_id' => (int) ($assignment['impostore_partecipazione_id'] ?? 0),
        ];
    }
}
