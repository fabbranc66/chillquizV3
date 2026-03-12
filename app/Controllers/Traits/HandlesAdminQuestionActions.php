<?php

namespace App\Controllers\Traits;

trait HandlesAdminQuestionActions
{
    private function loadSessioneProgressForQuestionReplace(\PDO $pdo, int $sessioneId): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT stato, domanda_corrente
             FROM sessioni
             WHERE id = :sessione_id
             LIMIT 1"
        );
        $stmt->execute(['sessione_id' => $sessioneId]);
        $row = $stmt->fetch() ?: null;

        if (!$row) {
            return null;
        }

        return [
            'stato' => (string) ($row['stato'] ?? ''),
            'domanda_corrente' => (int) ($row['domanda_corrente'] ?? 0),
        ];
    }

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

            case 'domande-candidati':
                $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
                $posizioneSource = (int) ($_POST['posizione_source'] ?? 0);
                $domandaSourceId = (int) ($_POST['domanda_source_id'] ?? 0);
                $querySearch = trim((string) ($_POST['q'] ?? ''));
                $argomentoFilter = (int) ($_POST['argomento_id'] ?? 0);
                $tipoFilterRaw = strtoupper(trim((string) ($_POST['tipo_domanda'] ?? '')));
                $limit = (int) ($_POST['limit'] ?? 20);
                if ($limit <= 0) {
                    $limit = 20;
                }
                $limit = max(5, min(100, $limit));

                if ($targetSessioneId <= 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Sessione non valida'
                    ]);
                    return true;
                }

                if ($posizioneSource <= 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Posizione domanda non valida'
                    ]);
                    return true;
                }

                $pdo = \App\Core\Database::getInstance();

                $sessionMeta = $this->loadSessioneProgressForQuestionReplace($pdo, $targetSessioneId);
                if (!$sessionMeta) {
                    $this->json([
                        'success' => false,
                        'error' => 'Sessione non trovata'
                    ]);
                    return true;
                }

                $domandaCorrente = (int) ($sessionMeta['domanda_corrente'] ?? 0);
                if ($domandaCorrente > 0 && $posizioneSource < $domandaCorrente) {
                    $this->json([
                        'success' => false,
                        'error' => 'Sostituzione bloccata: domanda gia passata.',
                        'code' => 'QUESTION_PASSED',
                        'sessione' => $sessionMeta,
                    ]);
                    return true;
                }

                $checkSource = $pdo->prepare(
                    "SELECT sd.domanda_id,
                            d.testo,
                            d.tipo_domanda,
                            d.argomento_id,
                            COALESCE(a.nome, '') AS argomento_nome
                     FROM sessione_domande sd
                     JOIN domande d ON d.id = sd.domanda_id
                     LEFT JOIN argomenti a ON a.id = d.argomento_id
                     WHERE sd.sessione_id = :sessione_id
                       AND sd.posizione = :posizione
                     LIMIT 1"
                );
                $checkSource->execute([
                    'sessione_id' => $targetSessioneId,
                    'posizione' => $posizioneSource,
                ]);
                $sourceRow = $checkSource->fetch() ?: null;

                if (!$sourceRow) {
                    $this->json([
                        'success' => false,
                        'error' => 'Domanda sorgente non trovata in sessione'
                    ]);
                    return true;
                }

                if ($domandaSourceId <= 0) {
                    $domandaSourceId = (int) ($sourceRow['domanda_id'] ?? 0);
                }

                $allowedTypes = [
                    'CLASSIC', 'MEDIA', 'SARABANDA', 'IMPOSTORE', 'MEME', 'MAJORITY',
                    'RANDOM_BONUS', 'BOMB', 'CHAOS', 'AUDIO_PARTY',
                ];
                $tipoFilter = in_array($tipoFilterRaw, $allowedTypes, true) ? $tipoFilterRaw : '';

                $sql = "SELECT
                            d.id,
                            d.codice_domanda,
                            d.testo,
                            UPPER(COALESCE(d.tipo_domanda, 'CLASSIC')) AS tipo_domanda,
                            d.argomento_id,
                            COALESCE(a.nome, '') AS argomento_nome
                        FROM domande d
                        LEFT JOIN argomenti a ON a.id = d.argomento_id
                        LEFT JOIN sessione_domande sd_conflict
                               ON sd_conflict.sessione_id = :sessione_id
                              AND sd_conflict.domanda_id = d.id
                              AND sd_conflict.posizione <> :posizione_source
                        WHERE d.attiva = 1
                          AND sd_conflict.domanda_id IS NULL
                          AND d.id <> :domanda_source_id";
                $params = [
                    'sessione_id' => $targetSessioneId,
                    'posizione_source' => $posizioneSource,
                    'domanda_source_id' => $domandaSourceId,
                ];

                if ($querySearch !== '') {
                    $sql .= " AND (
                        d.testo LIKE :q
                        OR COALESCE(d.codice_domanda, '') LIKE :q
                    )";
                    $params['q'] = '%' . $querySearch . '%';
                }

                if ($argomentoFilter > 0) {
                    $sql .= " AND d.argomento_id = :argomento_id";
                    $params['argomento_id'] = $argomentoFilter;
                }

                if ($tipoFilter !== '') {
                    $sql .= " AND UPPER(COALESCE(d.tipo_domanda, 'CLASSIC')) = :tipo_domanda";
                    $params['tipo_domanda'] = $tipoFilter;
                }

                $sql .= " ORDER BY d.id DESC LIMIT " . $limit;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $candidati = $stmt->fetchAll() ?: [];

                $this->json([
                    'success' => true,
                    'action' => $action,
                    'sessione_id' => $targetSessioneId,
                    'posizione_source' => $posizioneSource,
                    'source' => [
                        'domanda_id' => (int) ($sourceRow['domanda_id'] ?? 0),
                        'testo' => (string) ($sourceRow['testo'] ?? ''),
                        'tipo_domanda' => (string) ($sourceRow['tipo_domanda'] ?? 'CLASSIC'),
                        'argomento_id' => isset($sourceRow['argomento_id']) ? (int) $sourceRow['argomento_id'] : null,
                        'argomento_nome' => (string) ($sourceRow['argomento_nome'] ?? ''),
                    ],
                    'sessione' => $sessionMeta,
                    'candidati' => $candidati,
                ]);
                return true;

            case 'domanda-sostituisci':
                $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
                $posizioneSource = (int) ($_POST['posizione_source'] ?? 0);
                $domandaNuovaId = (int) ($_POST['domanda_nuova_id'] ?? 0);
                $confirmCurrent = (int) ($_POST['confirm_corrente'] ?? 0) === 1;

                if ($targetSessioneId <= 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Sessione non valida'
                    ]);
                    return true;
                }

                if ($posizioneSource <= 0 || $domandaNuovaId <= 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Parametri sostituzione non validi'
                    ]);
                    return true;
                }

                $pdo = \App\Core\Database::getInstance();

                $sessionMeta = $this->loadSessioneProgressForQuestionReplace($pdo, $targetSessioneId);
                if (!$sessionMeta) {
                    $this->json([
                        'success' => false,
                        'error' => 'Sessione non trovata'
                    ]);
                    return true;
                }

                $domandaCorrente = (int) ($sessionMeta['domanda_corrente'] ?? 0);
                $isCurrentPosition = $domandaCorrente > 0 && $posizioneSource === $domandaCorrente;
                if ($domandaCorrente > 0 && $posizioneSource < $domandaCorrente) {
                    $this->json([
                        'success' => false,
                        'error' => 'Sostituzione bloccata: domanda gia passata.',
                        'code' => 'QUESTION_PASSED',
                        'sessione' => $sessionMeta,
                    ]);
                    return true;
                }

                if ($isCurrentPosition && !$confirmCurrent) {
                    $this->json([
                        'success' => false,
                        'requires_confirmation' => true,
                        'error' => 'Stai sostituendo la domanda corrente: conferma richiesta.',
                        'code' => 'CONFIRM_CURRENT_REQUIRED',
                        'sessione' => $sessionMeta,
                    ]);
                    return true;
                }

                $sourceStmt = $pdo->prepare(
                    "SELECT sd.domanda_id,
                            d.testo
                     FROM sessione_domande sd
                     JOIN domande d ON d.id = sd.domanda_id
                     WHERE sd.sessione_id = :sessione_id
                       AND sd.posizione = :posizione
                     LIMIT 1"
                );
                $sourceStmt->execute([
                    'sessione_id' => $targetSessioneId,
                    'posizione' => $posizioneSource,
                ]);
                $sourceRow = $sourceStmt->fetch() ?: null;

                if (!$sourceRow) {
                    $this->json([
                        'success' => false,
                        'error' => 'Domanda sorgente non trovata'
                    ]);
                    return true;
                }

                $domandaVecchiaId = (int) ($sourceRow['domanda_id'] ?? 0);
                if ($domandaVecchiaId === $domandaNuovaId) {
                    $this->json([
                        'success' => false,
                        'error' => 'La domanda selezionata e gia in quella posizione'
                    ]);
                    return true;
                }

                $newDomandaStmt = $pdo->prepare(
                    "SELECT id, testo, attiva
                     FROM domande
                     WHERE id = :id
                     LIMIT 1"
                );
                $newDomandaStmt->execute(['id' => $domandaNuovaId]);
                $newDomanda = $newDomandaStmt->fetch() ?: null;

                if (!$newDomanda || (int) ($newDomanda['attiva'] ?? 0) !== 1) {
                    $this->json([
                        'success' => false,
                        'error' => 'Domanda sostitutiva non valida o non attiva'
                    ]);
                    return true;
                }

                $dupStmt = $pdo->prepare(
                    "SELECT posizione
                     FROM sessione_domande
                     WHERE sessione_id = :sessione_id
                       AND domanda_id = :domanda_id
                     LIMIT 1"
                );
                $dupStmt->execute([
                    'sessione_id' => $targetSessioneId,
                    'domanda_id' => $domandaNuovaId,
                ]);
                $dupRow = $dupStmt->fetch() ?: null;
                if ($dupRow && (int) ($dupRow['posizione'] ?? 0) !== $posizioneSource) {
                    $this->json([
                        'success' => false,
                        'error' => 'Sostituzione bloccata: domanda gia presente in sessione.',
                        'code' => 'QUESTION_DUPLICATE',
                        'dup_posizione' => (int) ($dupRow['posizione'] ?? 0),
                    ]);
                    return true;
                }

                $update = $pdo->prepare(
                    "UPDATE sessione_domande
                     SET domanda_id = :domanda_nuova_id
                     WHERE sessione_id = :sessione_id
                       AND posizione = :posizione"
                );
                $ok = $update->execute([
                    'domanda_nuova_id' => $domandaNuovaId,
                    'sessione_id' => $targetSessioneId,
                    'posizione' => $posizioneSource,
                ]);

                if (!$ok) {
                    $this->json([
                        'success' => false,
                        'error' => 'Errore aggiornamento sostituzione domanda'
                    ]);
                    return true;
                }

                $this->json([
                    'success' => true,
                    'action' => $action,
                    'sessione_id' => $targetSessioneId,
                    'posizione_source' => $posizioneSource,
                    'is_current_position' => $isCurrentPosition,
                    'sessione' => $sessionMeta,
                    'replaced' => [
                        'domanda_vecchia_id' => $domandaVecchiaId,
                        'domanda_vecchia_testo' => (string) ($sourceRow['testo'] ?? ''),
                        'domanda_nuova_id' => $domandaNuovaId,
                        'domanda_nuova_testo' => (string) ($newDomanda['testo'] ?? ''),
                    ],
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
