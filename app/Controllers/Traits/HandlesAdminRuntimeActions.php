<?php

namespace App\Controllers\Traits;

use App\Models\JoinRichiesta;
use App\Models\Sessione;
use App\Services\Question\ImpostoreModeService;
use App\Services\Question\MemeModeService;
use App\Services\SessioneService;

trait HandlesAdminRuntimeActions
{
    private function handleAdminRuntimeAction(string $action, int $sessioneId): bool
    {
        switch ($action) {
            case 'audio-preview':
                $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
                if ($targetSessioneId <= 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Sessione non valida'
                    ]);
                    return true;
                }

                $service = new SessioneService($targetSessioneId);
                $domanda = $service->domandaCorrente();

                if (!$domanda || !is_array($domanda)) {
                    $this->json([
                        'success' => false,
                        'error' => 'Domanda corrente non disponibile'
                    ]);
                    return true;
                }

                $audioPath = trim((string) ($domanda['media_audio_path'] ?? ''));
                if ($audioPath === '') {
                    $this->json([
                        'success' => false,
                        'error' => 'La domanda corrente non ha audio'
                    ]);
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

                $payload = [
                    'token' => $targetSessioneId . '-' . time() . '-' . random_int(1000, 9999),
                    'sessione_id' => $targetSessioneId,
                    'domanda_id' => (int) ($domanda['id'] ?? 0),
                    'audio_path' => $audioPath,
                    'preview_sec' => $previewSec > 0 ? $previewSec : 0,
                    'created_at' => time(),
                ];

                $ok = $this->writeAudioPreviewCommand($targetSessioneId, $payload);
                if (!$ok) {
                    $this->json([
                        'success' => false,
                        'error' => 'Impossibile inviare comando anteprima audio'
                    ]);
                    return true;
                }

                $this->json([
                    'success' => true,
                    'action' => $action,
                    'sessione_id' => $targetSessioneId,
                    'preview' => $payload,
                ]);
                return true;

            case 'join-richieste':
                $joinRichiesta = new JoinRichiesta();
                $this->json([
                    'success' => true,
                    'action' => $action,
                    'richieste' => $joinRichiesta->listaPending($sessioneId)
                ]);
                return true;

            case 'impostore-toggle':
                $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
                if ($targetSessioneId <= 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Sessione non valida'
                    ]);
                    return true;
                }

                $sessionRow = (new Sessione())->trova($targetSessioneId);
                if (!$sessionRow) {
                    $this->json([
                        'success' => false,
                        'error' => 'Sessione non trovata'
                    ]);
                    return true;
                }

                if (in_array((string) ($sessionRow['stato'] ?? ''), ['domanda', 'conclusa'], true)) {
                    $this->json([
                        'success' => false,
                        'error' => 'IMPOSTORE modificabile solo prima dello stato domanda'
                    ]);
                    return true;
                }

                $currentQuestion = $this->loadCurrentQuestionForSession($targetSessioneId);
                if (!$currentQuestion) {
                    $this->json([
                        'success' => false,
                        'error' => 'Domanda corrente non disponibile'
                    ]);
                    return true;
                }

                $modeMeta = (new \App\Services\Question\QuestionModeResolver())->resolveFromRow($currentQuestion);
                $currentType = strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? 'CLASSIC')));
                if ($currentType === 'SARABANDA') {
                    $this->json([
                        'success' => false,
                        'error' => 'IMPOSTORE non disponibile su domande SARABANDA'
                    ]);
                    return true;
                }

                $enabled = (int) ($_POST['enabled'] ?? 0) === 1;
                $impostoreService = new ImpostoreModeService();
                $impostoreService->setEnabledForQuestion($targetSessioneId, (int) ($currentQuestion['id'] ?? 0), $enabled);

                $this->json([
                    'success' => true,
                    'action' => $action,
                    'sessione_id' => $targetSessioneId,
                    'domanda_id' => (int) ($currentQuestion['id'] ?? 0),
                    'enabled' => $impostoreService->isEnabledForQuestion($targetSessioneId, (int) ($currentQuestion['id'] ?? 0)),
                ]);
                return true;

            case 'meme-toggle':
                $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
                if ($targetSessioneId <= 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Sessione non valida'
                    ]);
                    return true;
                }

                $sessionRow = (new Sessione())->trova($targetSessioneId);
                if (!$sessionRow) {
                    $this->json([
                        'success' => false,
                        'error' => 'Sessione non trovata'
                    ]);
                    return true;
                }

                if (in_array((string) ($sessionRow['stato'] ?? ''), ['domanda', 'conclusa'], true)) {
                    $this->json([
                        'success' => false,
                        'error' => 'MEME modificabile solo prima dello stato domanda'
                    ]);
                    return true;
                }

                $currentQuestion = $this->loadCurrentQuestionForSession($targetSessioneId);
                if (!$currentQuestion) {
                    $this->json([
                        'success' => false,
                        'error' => 'Domanda corrente non disponibile'
                    ]);
                    return true;
                }

                $modeMeta = (new \App\Services\Question\QuestionModeResolver())->resolveFromRow($currentQuestion);
                $currentType = strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? 'CLASSIC')));
                if ($currentType === 'SARABANDA') {
                    $this->json([
                        'success' => false,
                        'error' => 'MEME non disponibile su domande SARABANDA'
                    ]);
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

                $memeService = new MemeModeService();
                $memeService->setEnabledForQuestion($targetSessioneId, (int) ($currentQuestion['id'] ?? 0), $enabled, $memeText);
                $state = $memeService->getRuntimeState($targetSessioneId);

                $this->json([
                    'success' => true,
                    'action' => $action,
                    'sessione_id' => $targetSessioneId,
                    'domanda_id' => (int) ($currentQuestion['id'] ?? 0),
                    'enabled' => $memeService->isEnabledForQuestion($targetSessioneId, (int) ($currentQuestion['id'] ?? 0)),
                    'meme_text' => trim((string) ($state['meme_text'] ?? '')),
                ]);
                return true;

            case 'approva-join':
            case 'rifiuta-join':
                $richiestaId = (int) ($_POST['request_id'] ?? 0);
                if ($richiestaId <= 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Richiesta non valida'
                    ]);
                    return true;
                }

                $joinRichiesta = new JoinRichiesta();
                $stato = $action === 'approva-join' ? 'approvata' : 'rifiutata';
                $ok = $joinRichiesta->gestisci($richiestaId, $sessioneId, $stato);

                $this->json([
                    'success' => $ok,
                    'action' => $action,
                    'request_id' => $richiestaId,
                    'stato' => $stato,
                    'error' => $ok ? null : 'Impossibile aggiornare richiesta'
                ]);
                return true;
        }

        return false;
    }
}
