<?php

namespace App\Controllers\Traits;

use App\Models\Sessione;
use App\Services\Question\SarabandaAudioModeService;
use App\Services\SessioneService;

trait HandlesAdminAudioRuntimeActions
{
    private function handleAdminAudioRuntimeAction(string $action, int $sessioneId): bool
    {
        switch ($action) {
            case 'audio-preview':
                $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
                if ($targetSessioneId <= 0) {
                    $this->json(['success' => false, 'error' => 'Sessione non valida']);
                    return true;
                }

                $service = new SessioneService($targetSessioneId);
                $domanda = $service->domandaCorrente();

                if (!$domanda || !is_array($domanda)) {
                    $this->json(['success' => false, 'error' => 'Domanda corrente non disponibile']);
                    return true;
                }

                $audioPath = trim((string) ($domanda['media_audio_path'] ?? ''));
                if ($audioPath === '') {
                    $this->json(['success' => false, 'error' => 'La domanda corrente non ha audio']);
                    return true;
                }

                $previewSec = (int) ($domanda['media_audio_preview_sec'] ?? 0);
                $tipoDomanda = strtoupper(trim((string) ($domanda['tipo_domanda'] ?? 'CLASSIC')));

                if ($tipoDomanda === 'SARABANDA' && $previewSec <= 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Per SARABANDA imposta Intro secondi maggiore di 0',
                    ]);
                    return true;
                }

                $audioModeService = new SarabandaAudioModeService();
                $questionId = (int) ($domanda['id'] ?? 0);
                $audioEnabled = $audioModeService->isAudioEnabledForQuestion($targetSessioneId, $questionId);
                $reverseEnabled = $audioModeService->isReverseEnabledForQuestion($targetSessioneId, $questionId);
                $fastForwardEnabled = $audioModeService->isFastForwardEnabledForQuestion($targetSessioneId, $questionId);
                $fastForwardRate = $audioModeService->getFastForwardRateForQuestion($targetSessioneId, $questionId);
                $effectivePreviewSec = $reverseEnabled
                    ? max(10, $previewSec > 0 ? $previewSec : 0)
                    : ($fastForwardEnabled
                        ? max(self::SARABANDA_FAST_FORWARD_SOURCE_SEC, $previewSec > 0 ? $previewSec : 0)
                        : ($previewSec > 0 ? $previewSec : 0));
                $playbackDurationSec = $fastForwardEnabled
                    ? round($effectivePreviewSec / max(1, $fastForwardRate), 3)
                    : $effectivePreviewSec;

                $payload = [
                    'token' => $targetSessioneId . '-' . time() . '-' . random_int(1000, 9999),
                    'sessione_id' => $targetSessioneId,
                    'domanda_id' => $questionId,
                    'audio_path' => $audioPath,
                    'preview_sec' => $effectivePreviewSec,
                    'playback_duration_sec' => $playbackDurationSec,
                    'audio_enabled' => $audioEnabled,
                    'reverse_audio' => $reverseEnabled,
                    'fast_forward_audio' => $fastForwardEnabled,
                    'fast_forward_rate' => $fastForwardRate,
                    'created_at' => time(),
                ];

                $ok = $this->writeAudioPreviewCommand($targetSessioneId, $payload);
                if (!$ok) {
                    $this->json(['success' => false, 'error' => 'Impossibile inviare comando anteprima audio']);
                    return true;
                }

                $this->json([
                    'success' => true,
                    'action' => $action,
                    'sessione_id' => $targetSessioneId,
                    'preview' => $payload,
                ]);
                return true;

            case 'sarabanda-audio-toggle':
                return $this->toggleSarabandaAudioBase($sessioneId, $action);

            case 'sarabanda-reverse-toggle':
                return $this->toggleSarabandaReverse($sessioneId, $action);

            case 'sarabanda-fast-toggle':
                return $this->toggleSarabandaFast($sessioneId, $action);

            case 'sarabanda-fast-rate-set':
                return $this->setSarabandaFastRate($sessioneId, $action);
        }

        return false;
    }

    private function validateSarabandaAudioRuntime(int $sessioneId, string $lockedError, string $eligibleError): array
    {
        if ($sessioneId <= 0) {
            return [false, 'Sessione non valida', null, null];
        }

        $sessionRow = (new Sessione())->trova($sessioneId);
        if (!$sessionRow) {
            return [false, 'Sessione non trovata', null, null];
        }

        if (in_array((string) ($sessionRow['stato'] ?? ''), ['domanda', 'conclusa'], true)) {
            return [false, $lockedError, $sessionRow, null];
        }

        $currentQuestion = $this->loadCurrentQuestionForSession($sessioneId);
        if (!$currentQuestion) {
            return [false, 'Domanda corrente non disponibile', $sessionRow, null];
        }

        $currentType = strtoupper(trim((string) ($currentQuestion['tipo_domanda'] ?? 'CLASSIC')));
        $hasAudio = trim((string) ($currentQuestion['media_audio_path'] ?? '')) !== '';
        if ($currentType !== 'SARABANDA' || !$hasAudio) {
            return [false, $eligibleError, $sessionRow, $currentQuestion];
        }

        return [true, null, $sessionRow, $currentQuestion];
    }

    private function toggleSarabandaAudioBase(int $sessioneId, string $action): bool
    {
        [$ok, $error, , $currentQuestion] = $this->validateSarabandaAudioRuntime(
            (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0),
            'SARABANDA modificabile solo prima dello stato domanda',
            'SARABANDA disponibile solo per domande SARABANDA con audio'
        );

        if (!$ok) {
            $this->json(['success' => false, 'error' => $error]);
            return true;
        }

        $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
        $enabled = (int) ($_POST['enabled'] ?? 0) === 1;
        $service = new SarabandaAudioModeService();
        $questionId = (int) ($currentQuestion['id'] ?? 0);
        $service->setAudioEnabledForQuestion($targetSessioneId, $questionId, $enabled);

        $this->json([
            'success' => true,
            'action' => $action,
            'sessione_id' => $targetSessioneId,
            'domanda_id' => $questionId,
            'enabled' => $service->isAudioEnabledForQuestion($targetSessioneId, $questionId),
        ]);
        return true;
    }

    private function toggleSarabandaReverse(int $sessioneId, string $action): bool
    {
        [$ok, $error, , $currentQuestion] = $this->validateSarabandaAudioRuntime(
            (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0),
            'REVERSE modificabile solo prima dello stato domanda',
            'REVERSE disponibile solo per SARABANDA con audio'
        );

        if (!$ok) {
            $this->json(['success' => false, 'error' => $error]);
            return true;
        }

        $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
        $enabled = (int) ($_POST['enabled'] ?? 0) === 1;
        $service = new SarabandaAudioModeService();
        $questionId = (int) ($currentQuestion['id'] ?? 0);
        $service->setReverseEnabledForQuestion($targetSessioneId, $questionId, $enabled);

        $this->json([
            'success' => true,
            'action' => $action,
            'sessione_id' => $targetSessioneId,
            'domanda_id' => $questionId,
            'enabled' => $service->isReverseEnabledForQuestion($targetSessioneId, $questionId),
        ]);
        return true;
    }

    private function toggleSarabandaFast(int $sessioneId, string $action): bool
    {
        [$ok, $error, , $currentQuestion] = $this->validateSarabandaAudioRuntime(
            (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0),
            'FAST modificabile solo prima dello stato domanda',
            'FAST disponibile solo per SARABANDA con audio'
        );

        if (!$ok) {
            $this->json(['success' => false, 'error' => $error]);
            return true;
        }

        $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
        $enabled = (int) ($_POST['enabled'] ?? 0) === 1;
        $rate = (float) ($_POST['rate'] ?? SarabandaAudioModeService::DEFAULT_FAST_FORWARD_RATE);
        $service = new SarabandaAudioModeService();
        $questionId = (int) ($currentQuestion['id'] ?? 0);
        $service->setFastForwardEnabledForQuestion($targetSessioneId, $questionId, $enabled, $rate);

        $this->json([
            'success' => true,
            'action' => $action,
            'sessione_id' => $targetSessioneId,
            'domanda_id' => $questionId,
            'enabled' => $service->isFastForwardEnabledForQuestion($targetSessioneId, $questionId),
            'rate' => $service->getFastForwardRateForQuestion($targetSessioneId, $questionId),
        ]);
        return true;
    }

    private function setSarabandaFastRate(int $sessioneId, string $action): bool
    {
        [$ok, $error, , $currentQuestion] = $this->validateSarabandaAudioRuntime(
            (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0),
            'Velocita FAST modificabile solo prima dello stato domanda',
            'FAST disponibile solo per SARABANDA con audio'
        );

        if (!$ok) {
            $this->json(['success' => false, 'error' => $error]);
            return true;
        }

        $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
        $rate = (float) ($_POST['rate'] ?? SarabandaAudioModeService::DEFAULT_FAST_FORWARD_RATE);
        $service = new SarabandaAudioModeService();
        $questionId = (int) ($currentQuestion['id'] ?? 0);
        $service->setFastForwardRateForQuestion($targetSessioneId, $questionId, $rate);

        $this->json([
            'success' => true,
            'action' => $action,
            'sessione_id' => $targetSessioneId,
            'domanda_id' => $questionId,
            'rate' => $service->getFastForwardRateForQuestion($targetSessioneId, $questionId),
        ]);
        return true;
    }
}
