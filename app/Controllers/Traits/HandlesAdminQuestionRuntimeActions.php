<?php

namespace App\Controllers\Traits;

use App\Models\Sessione;
use App\Services\Question\FadeModeService;
use App\Services\Question\ImagePartyModeService;
use App\Services\Question\ImpostoreModeService;
use App\Services\Question\MemeModeService;
use App\Services\Question\QuestionModeResolver;

trait HandlesAdminQuestionRuntimeActions
{
    private function handleAdminQuestionRuntimeAction(string $action, int $sessioneId): bool
    {
        switch ($action) {
            case 'impostore-toggle':
                return $this->toggleImpostoreRuntime($sessioneId, $action);

            case 'meme-toggle':
                return $this->toggleMemeRuntime($sessioneId, $action);

            case 'image-party-toggle':
                return $this->toggleImagePartyRuntime($sessioneId, $action);

            case 'fade-toggle':
                return $this->toggleFadeRuntime($sessioneId, $action);
        }

        return false;
    }

    private function validateQuestionRuntimeToggle(
        int $sessioneId,
        string $lockedError,
        string $sarabandaError,
        bool $requiresImage = false,
        string $imageError = ''
    ): array {
        if ($sessioneId <= 0) {
            return [false, 'Sessione non valida', null, null];
        }

        $sessionRow = (new Sessione())->trova($sessioneId);
        if (!$sessionRow) {
            return [false, 'Sessione non trovata', null, null];
        }

        if (in_array((string) ($sessionRow['stato'] ?? ''), ['preview', 'domanda', 'conclusa'], true)) {
            return [false, $lockedError, $sessionRow, null];
        }

        $currentQuestion = $this->loadCurrentQuestionForSession($sessioneId);
        if (!$currentQuestion) {
            return [false, 'Domanda corrente non disponibile', $sessionRow, null];
        }

        $modeMeta = (new QuestionModeResolver())->resolveFromRow($currentQuestion);
        $currentType = strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? 'CLASSIC')));
        if ($currentType === 'SARABANDA') {
            return [false, $sarabandaError, $sessionRow, $currentQuestion];
        }

        if ($requiresImage && trim((string) ($currentQuestion['media_image_path'] ?? '')) === '') {
            return [false, $imageError, $sessionRow, $currentQuestion];
        }

        return [true, null, $sessionRow, $currentQuestion];
    }

    private function toggleImpostoreRuntime(int $sessioneId, string $action): bool
    {
        $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
        [$ok, $error, , $currentQuestion] = $this->validateQuestionRuntimeToggle(
            $targetSessioneId,
            'IMPOSTORE modificabile solo prima dello stato domanda',
            'IMPOSTORE non disponibile su domande SARABANDA'
        );

        if (!$ok) {
            $this->json(['success' => false, 'error' => $error]);
            return true;
        }

        $enabled = (int) ($_POST['enabled'] ?? 0) === 1;
        $service = new ImpostoreModeService();
        $questionId = (int) ($currentQuestion['id'] ?? 0);
        $service->setEnabledForQuestion($targetSessioneId, $questionId, $enabled);

        $this->json([
            'success' => true,
            'action' => $action,
            'sessione_id' => $targetSessioneId,
            'domanda_id' => $questionId,
            'enabled' => $service->isEnabledForQuestion($targetSessioneId, $questionId),
        ]);
        return true;
    }

    private function toggleMemeRuntime(int $sessioneId, string $action): bool
    {
        $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
        [$ok, $error, , $currentQuestion] = $this->validateQuestionRuntimeToggle(
            $targetSessioneId,
            'MEME modificabile solo prima dello stato domanda',
            'MEME non disponibile su domande SARABANDA'
        );

        if (!$ok) {
            $this->json(['success' => false, 'error' => $error]);
            return true;
        }

        $enabled = (int) ($_POST['enabled'] ?? 0) === 1;
        $memeText = trim((string) ($_POST['meme_text'] ?? ''));
        if ($enabled && $memeText === '') {
            $this->json([
                'success' => false,
                'error' => 'Inserisci il testo meme prima di attivare la modalita'
            ]);
            return true;
        }

        $service = new MemeModeService();
        $questionId = (int) ($currentQuestion['id'] ?? 0);
        $service->setEnabledForQuestion($targetSessioneId, $questionId, $enabled, $memeText);
        $state = $service->getRuntimeState($targetSessioneId);

        $this->json([
            'success' => true,
            'action' => $action,
            'sessione_id' => $targetSessioneId,
            'domanda_id' => $questionId,
            'enabled' => $service->isEnabledForQuestion($targetSessioneId, $questionId),
            'meme_text' => trim((string) ($state['meme_text'] ?? '')),
        ]);
        return true;
    }

    private function toggleImagePartyRuntime(int $sessioneId, string $action): bool
    {
        $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
        [$ok, $error, , $currentQuestion] = $this->validateQuestionRuntimeToggle(
            $targetSessioneId,
            'PIXELATE modificabile solo prima dello stato domanda',
            'PIXELATE non disponibile su domande SARABANDA',
            true,
            'PIXELATE richiede un\'immagine sulla domanda corrente'
        );

        if (!$ok) {
            $this->json(['success' => false, 'error' => $error]);
            return true;
        }

        $enabled = (int) ($_POST['enabled'] ?? 0) === 1;
        $questionId = (int) ($currentQuestion['id'] ?? 0);
        $imagePartyService = new ImagePartyModeService();
        $fadeService = new FadeModeService();
        $fadeService->clearRuntimeState($targetSessioneId);
        $writeOk = $imagePartyService->setEnabledForQuestion($targetSessioneId, $questionId, $enabled);
        if (!$writeOk) {
            $this->json([
                'success' => false,
                'error' => $imagePartyService->getLastError() ?: 'Impossibile aggiornare lo stato PIXELATE',
            ]);
            return true;
        }

        $this->json([
            'success' => true,
            'action' => $action,
            'sessione_id' => $targetSessioneId,
            'domanda_id' => $questionId,
            'enabled' => $imagePartyService->isEnabledForQuestion($targetSessioneId, $questionId),
        ]);
        return true;
    }

    private function toggleFadeRuntime(int $sessioneId, string $action): bool
    {
        $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
        [$ok, $error, , $currentQuestion] = $this->validateQuestionRuntimeToggle(
            $targetSessioneId,
            'FADE modificabile solo prima dello stato domanda',
            'FADE non disponibile su domande SARABANDA',
            true,
            'FADE richiede un\'immagine sulla domanda corrente'
        );

        if (!$ok) {
            $this->json(['success' => false, 'error' => $error]);
            return true;
        }

        $enabled = (int) ($_POST['enabled'] ?? 0) === 1;
        $questionId = (int) ($currentQuestion['id'] ?? 0);
        $fadeService = new FadeModeService();
        $imagePartyService = new ImagePartyModeService();
        $imagePartyService->clearRuntimeState($targetSessioneId);
        $writeOk = $fadeService->setEnabledForQuestion($targetSessioneId, $questionId, $enabled);
        if (!$writeOk) {
            $this->json([
                'success' => false,
                'error' => $fadeService->getLastError() ?: 'Impossibile aggiornare lo stato FADE',
            ]);
            return true;
        }

        $this->json([
            'success' => true,
            'action' => $action,
            'sessione_id' => $targetSessioneId,
            'domanda_id' => $questionId,
            'enabled' => $fadeService->isEnabledForQuestion($targetSessioneId, $questionId),
        ]);
        return true;
    }
}
