<?php

namespace App\Controllers\Traits;

trait HandlesAdminQuestionActions
{
    private function handleAdminQuestionAction(string $action, int $sessioneId): bool
    {
        switch ($action) {
            case 'domande-sessione':
                $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
                if ($targetSessioneId <= 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Sessione non valida'
                    ]);
                    return true;
                }

                $pdo = \App\Core\Database::getInstance();
                $this->ensureDomandeIdentitySchema($pdo);
                $stmt = $pdo->prepare(
                    "SELECT
                        sd.posizione,
                        d.id AS domanda_id,
                        d.codice_domanda,
                        d.testo,
                        d.tipo_domanda,
                        d.modalita_party,
                        d.fase_domanda,
                        d.media_image_path,
                        d.media_audio_path,
                        d.media_caption
                     FROM sessione_domande sd
                     JOIN domande d ON d.id = sd.domanda_id
                     WHERE sd.sessione_id = :sessione_id
                     ORDER BY sd.posizione ASC"
                );

                $stmt->execute(['sessione_id' => $targetSessioneId]);

                $this->json([
                    'success' => true,
                    'action' => $action,
                    'sessione_id' => $targetSessioneId,
                    'domande' => $stmt->fetchAll() ?: []
                ]);
                return true;

            case 'domanda-dettaglio':
                $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
                $domandaId = (int) ($_POST['domanda_id'] ?? 0);

                if ($targetSessioneId <= 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Sessione non valida'
                    ]);
                    return true;
                }

                if ($domandaId <= 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Domanda non valida'
                    ]);
                    return true;
                }

                $pdo = \App\Core\Database::getInstance();
                $this->ensureDomandeIdentitySchema($pdo);

                $check = $pdo->prepare(
                    "SELECT d.id
                     FROM domande d
                     JOIN sessione_domande sd ON sd.domanda_id = d.id
                     WHERE sd.sessione_id = :sessione_id
                       AND d.id = :domanda_id
                     LIMIT 1"
                );
                $check->execute([
                    'sessione_id' => $targetSessioneId,
                    'domanda_id' => $domandaId,
                ]);

                if (!$check->fetch()) {
                    $this->json([
                        'success' => false,
                        'error' => 'Domanda non appartenente alla sessione'
                    ]);
                    return true;
                }

                $stmt = $pdo->prepare(
                    "SELECT id, codice_domanda, fingerprint_unico, testo, tipo_domanda, modalita_party, fase_domanda, media_image_path, media_audio_path, media_audio_preview_sec, media_caption, config_json
                     FROM domande
                     WHERE id = :id
                     LIMIT 1"
                );
                $stmt->execute(['id' => $domandaId]);
                $domanda = $stmt->fetch() ?: null;

                if (!$domanda) {
                    $this->json([
                        'success' => false,
                        'error' => 'Domanda non trovata'
                    ]);
                    return true;
                }

                $this->json([
                    'success' => true,
                    'action' => $action,
                    'sessione_id' => $targetSessioneId,
                    'domanda_id' => $domandaId,
                    'domanda' => $domanda,
                ]);
                return true;

            case 'domanda-update':
                $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
                if ($targetSessioneId <= 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Sessione non valida'
                    ]);
                    return true;
                }

                $pdo = \App\Core\Database::getInstance();
                $this->ensureDomandeIdentitySchema($pdo);

                $domandaId = (int) ($_POST['domanda_id'] ?? 0);
                if ($domandaId <= 0) {
                    $stmt = $pdo->prepare(
                        "SELECT sd.domanda_id
                         FROM sessioni s
                         JOIN sessione_domande sd
                           ON sd.sessione_id = s.id
                          AND sd.posizione = s.domanda_corrente
                         WHERE s.id = :sessione_id
                         LIMIT 1"
                    );
                    $stmt->execute(['sessione_id' => $targetSessioneId]);
                    $rowDomanda = $stmt->fetch();
                    $domandaId = (int) ($rowDomanda['domanda_id'] ?? 0);
                }

                if ($domandaId <= 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Domanda corrente non trovata'
                    ]);
                    return true;
                }

                $check = $pdo->prepare(
                    "SELECT d.id
                     FROM domande d
                     JOIN sessione_domande sd ON sd.domanda_id = d.id
                     WHERE sd.sessione_id = :sessione_id
                       AND d.id = :domanda_id
                     LIMIT 1"
                );
                $check->execute([
                    'sessione_id' => $targetSessioneId,
                    'domanda_id' => $domandaId,
                ]);

                if (!$check->fetch()) {
                    $this->json([
                        'success' => false,
                        'error' => 'Domanda non appartenente alla sessione'
                    ]);
                    return true;
                }

                $tipoRaw = strtoupper(trim((string) ($_POST['tipo_domanda'] ?? 'CLASSIC')));
                $allowedTipi = [
                    'CLASSIC', 'MEDIA', 'SARABANDA', 'IMPOSTORE', 'MEME', 'MAJORITY',
                    'RANDOM_BONUS', 'BOMB', 'CHAOS', 'AUDIO_PARTY',
                ];
                $tipoDomanda = in_array($tipoRaw, $allowedTipi, true) ? $tipoRaw : 'CLASSIC';

                $modalitaPartyRaw = trim((string) ($_POST['modalita_party'] ?? ''));
                $modalitaParty = $modalitaPartyRaw !== '' ? $modalitaPartyRaw : null;

                $faseRaw = strtolower(trim((string) ($_POST['fase_domanda'] ?? 'domanda')));
                $faseDomanda = $faseRaw === 'intro' ? 'intro' : 'domanda';

                $mediaImagePathRaw = trim((string) ($_POST['media_image_path'] ?? ''));
                $mediaImagePath = $mediaImagePathRaw !== '' ? $mediaImagePathRaw : null;

                $mediaAudioPathRaw = trim((string) ($_POST['media_audio_path'] ?? ''));
                $mediaAudioPath = $mediaAudioPathRaw !== '' ? $mediaAudioPathRaw : null;

                $previewRaw = (int) ($_POST['media_audio_preview_sec'] ?? 0);
                $mediaAudioPreviewSec = $previewRaw > 0 ? $previewRaw : null;

                $mediaCaptionRaw = trim((string) ($_POST['media_caption'] ?? ''));
                $mediaCaption = $mediaCaptionRaw !== '' ? $mediaCaptionRaw : null;

                $configJsonRaw = trim((string) ($_POST['config_json'] ?? ''));
                $configJson = null;
                if ($configJsonRaw !== '') {
                    $decoded = json_decode($configJsonRaw, true);
                    if (!is_array($decoded)) {
                        $this->json([
                            'success' => false,
                            'error' => 'config_json non valido'
                        ]);
                        return true;
                    }
                    $configJson = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                }

                $update = $pdo->prepare(
                    "UPDATE domande
                     SET tipo_domanda = :tipo_domanda,
                         modalita_party = :modalita_party,
                         fase_domanda = :fase_domanda,
                         media_image_path = :media_image_path,
                         media_audio_path = :media_audio_path,
                         media_audio_preview_sec = :media_audio_preview_sec,
                         media_caption = :media_caption,
                         config_json = :config_json
                     WHERE id = :domanda_id"
                );

                $update->execute([
                    'tipo_domanda' => $tipoDomanda,
                    'modalita_party' => $modalitaParty,
                    'fase_domanda' => $faseDomanda,
                    'media_image_path' => $mediaImagePath,
                    'media_audio_path' => $mediaAudioPath,
                    'media_audio_preview_sec' => $mediaAudioPreviewSec,
                    'media_caption' => $mediaCaption,
                    'config_json' => $configJson,
                    'domanda_id' => $domandaId,
                ]);

                $identity = $this->syncDomandaIdentityFields($pdo, $domandaId, false);

                $stmt = $pdo->prepare(
                    "SELECT id, codice_domanda, fingerprint_unico, testo, tipo_domanda, modalita_party, fase_domanda, media_image_path, media_audio_path, media_audio_preview_sec, media_caption, config_json
                     FROM domande
                     WHERE id = :id
                     LIMIT 1"
                );
                $stmt->execute(['id' => $domandaId]);
                $domandaAggiornata = $stmt->fetch() ?: null;

                $this->json([
                    'success' => true,
                    'action' => $action,
                    'sessione_id' => $targetSessioneId,
                    'domanda_id' => $domandaId,
                    'identity' => $identity,
                    'domanda' => $domandaAggiornata,
                ]);
                return true;
        }

        return false;
    }
}
