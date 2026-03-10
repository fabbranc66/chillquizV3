<?php

namespace App\Controllers\Traits;

use App\Core\Database;
use App\Models\JoinRichiesta;
use App\Models\Sessione;
use App\Services\Question\FadeModeService;
use App\Services\Question\ImagePartyModeService;
use App\Services\Question\ImpostoreModeService;
use App\Services\Question\MemeModeService;
use App\Services\Question\SarabandaAudioModeService;
use App\Services\SessioneService;

trait HandlesAdminRuntimeActions
{
    private const SARABANDA_FAST_FORWARD_SOURCE_SEC = 20;

    private function handleAdminRuntimeAction(string $action, int $sessioneId): bool
    {
        if ($this->handleAdminAudioRuntimeAction($action, $sessioneId)) {
            return true;
        }

        switch ($action) {
            case 'join-richieste':
                $joinRichiesta = new JoinRichiesta();
                $this->json([
                    'success' => true,
                    'action' => $action,
                    'richieste' => $joinRichiesta->listaPending($sessioneId)
                ]);
                return true;

            case 'debug-sessione':
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

                $service = new SessioneService($targetSessioneId);
                $currentQuestion = $this->loadCurrentQuestionForSession($targetSessioneId);
                $questionId = (int) ($currentQuestion['id'] ?? 0);
                $impostoreService = new ImpostoreModeService();
                $memeService = new MemeModeService();
                $imagePartyService = new ImagePartyModeService();
                $fadeService = new FadeModeService();
                $sarabandaAudioService = new SarabandaAudioModeService();
                $pdo = Database::getInstance();

                $puntateLiveStmt = $pdo->prepare(
                    "SELECT pl.partecipazione_id, u.nome, pl.importo, pl.aggiornato_il
                     FROM puntate_live pl
                     JOIN partecipazioni p ON p.id = pl.partecipazione_id
                     JOIN utenti u ON u.id = p.utente_id
                     WHERE pl.sessione_id = :sessione_id
                     ORDER BY pl.aggiornato_il DESC, pl.partecipazione_id ASC"
                );
                $puntateLiveStmt->execute(['sessione_id' => $targetSessioneId]);

                $risposteStmt = $pdo->prepare(
                    "SELECT r.id,
                            r.partecipazione_id,
                            u.nome,
                            r.domanda_id,
                            r.opzione_id,
                            r.corretta,
                            r.puntata,
                            r.punti,
                            r.tempo_risposta,
                            o_sel.testo AS risposta_data_testo,
                            o_ok.testo AS risposta_corretta_testo
                     FROM risposte r
                     JOIN partecipazioni p ON p.id = r.partecipazione_id
                     JOIN utenti u ON u.id = p.utente_id
                     LEFT JOIN opzioni o_sel ON o_sel.id = r.opzione_id
                     LEFT JOIN opzioni o_ok
                       ON o_ok.domanda_id = r.domanda_id
                      AND o_ok.corretta = 1
                     WHERE p.sessione_id = :sessione_id
                     ORDER BY r.id DESC
                     LIMIT 12"
                );
                $risposteStmt->execute(['sessione_id' => $targetSessioneId]);

                $this->json([
                    'success' => true,
                    'action' => $action,
                    'sessione_id' => $targetSessioneId,
                    'debug' => [
                        'sessione' => $sessionRow,
                        'domanda_corrente' => $currentQuestion,
                        'runtime' => [
                            'impostore_enabled' => $questionId > 0 ? $impostoreService->isEnabledForQuestion($targetSessioneId, $questionId) : false,
                            'impostore_assignment' => $questionId > 0 ? $impostoreService->getAssignment($targetSessioneId, $questionId) : null,
                            'meme_enabled' => $questionId > 0 ? $memeService->isEnabledForQuestion($targetSessioneId, $questionId) : false,
                            'meme_state' => $memeService->getRuntimeState($targetSessioneId),
                            'image_party_enabled' => $questionId > 0 ? $imagePartyService->isEnabledForQuestion($targetSessioneId, $questionId) : false,
                            'image_party_state' => $imagePartyService->getRuntimeState($targetSessioneId),
                            'fade_enabled' => $questionId > 0 ? $fadeService->isEnabledForQuestion($targetSessioneId, $questionId) : false,
                            'fade_state' => $fadeService->getRuntimeState($targetSessioneId),
                            'sarabanda_reverse_enabled' => $questionId > 0 ? $sarabandaAudioService->isReverseEnabledForQuestion($targetSessioneId, $questionId) : false,
                            'sarabanda_fast_forward_enabled' => $questionId > 0 ? $sarabandaAudioService->isFastForwardEnabledForQuestion($targetSessioneId, $questionId) : false,
                            'sarabanda_fast_forward_rate' => $questionId > 0 ? $sarabandaAudioService->getFastForwardRateForQuestion($targetSessioneId, $questionId) : SarabandaAudioModeService::DEFAULT_FAST_FORWARD_RATE,
                            'sarabanda_audio_enabled' => $questionId > 0 ? $sarabandaAudioService->isAudioEnabledForQuestion($targetSessioneId, $questionId) : false,
                            'sarabanda_audio_state' => $sarabandaAudioService->getRuntimeState($targetSessioneId),
                        ],
                        'puntate_live' => $puntateLiveStmt->fetchAll() ?: [],
                        'classifica' => $service->classifica(),
                        'ultime_risposte' => $risposteStmt->fetchAll() ?: [],
                    ],
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

            case 'image-party-toggle':
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
                        'error' => 'PIXELATE modificabile solo prima dello stato domanda'
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
                        'error' => 'PIXELATE non disponibile su domande SARABANDA'
                    ]);
                    return true;
                }

                if (trim((string) ($currentQuestion['media_image_path'] ?? '')) === '') {
                    $this->json([
                        'success' => false,
                        'error' => 'PIXELATE richiede un\'immagine sulla domanda corrente'
                    ]);
                    return true;
                }

                $enabled = (int) ($_POST['enabled'] ?? 0) === 1;
                $imagePartyService = new ImagePartyModeService();
                $fadeService = new FadeModeService();
                $fadeService->clearRuntimeState($targetSessioneId);
                $writeOk = $imagePartyService->setEnabledForQuestion($targetSessioneId, (int) ($currentQuestion['id'] ?? 0), $enabled);
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
                    'domanda_id' => (int) ($currentQuestion['id'] ?? 0),
                    'enabled' => $imagePartyService->isEnabledForQuestion($targetSessioneId, (int) ($currentQuestion['id'] ?? 0)),
                ]);
                return true;

            case 'fade-toggle':
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
                        'error' => 'FADE modificabile solo prima dello stato domanda'
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
                        'error' => 'FADE non disponibile su domande SARABANDA'
                    ]);
                    return true;
                }

                if (trim((string) ($currentQuestion['media_image_path'] ?? '')) === '') {
                    $this->json([
                        'success' => false,
                        'error' => 'FADE richiede un\'immagine sulla domanda corrente'
                    ]);
                    return true;
                }

                $enabled = (int) ($_POST['enabled'] ?? 0) === 1;
                $fadeService = new FadeModeService();
                $imagePartyService = new ImagePartyModeService();
                $imagePartyService->clearRuntimeState($targetSessioneId);
                $writeOk = $fadeService->setEnabledForQuestion($targetSessioneId, (int) ($currentQuestion['id'] ?? 0), $enabled);
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
                    'domanda_id' => (int) ($currentQuestion['id'] ?? 0),
                    'enabled' => $fadeService->isEnabledForQuestion($targetSessioneId, (int) ($currentQuestion['id'] ?? 0)),
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
