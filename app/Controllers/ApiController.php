<?php

namespace App\Controllers;

use App\Controllers\Traits\HandlesAdminMediaActions;
use App\Controllers\Traits\HandlesAdminQuestionActions;
use App\Controllers\Traits\HandlesAdminRuntimeActions;
use App\Controllers\Traits\HandlesAdminSessionActions;
use App\Models\Partecipazione;
use App\Models\Risposta;
use App\Models\Sessione;
use App\Models\Sistema;
use App\Models\JoinRichiesta;
use App\Models\Utente;
use App\Models\ScreenMedia;
use App\Models\AppSettings;
use App\Services\Question\ImpostoreModeService;
use App\Services\Question\FadeModeService;
use App\Services\Question\ImagePartyModeService;
use App\Services\Question\MemeModeService;
use App\Services\Question\SarabandaAudioModeService;
use App\Services\SessioneService;
use Throwable;

class ApiController
{
    private const SARABANDA_FAST_FORWARD_SOURCE_SEC = 20;

    use HandlesAdminSessionActions;
    use HandlesAdminRuntimeActions;
    use HandlesAdminQuestionActions;
    use HandlesAdminMediaActions;

    private function audioPreviewCommandFile(int $sessioneId): string
    {
        $dir = STORAGE_PATH . '/runtime/audio_preview';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir . '/session_' . $sessioneId . '.json';
    }

    private function readAudioPreviewCommand(int $sessioneId): ?array
    {
        if ($sessioneId <= 0) {
            return null;
        }

        $file = $this->audioPreviewCommandFile($sessioneId);
        if (!is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function writeAudioPreviewCommand(int $sessioneId, array $payload): bool
    {
        if ($sessioneId <= 0) {
            return false;
        }

        $file = $this->audioPreviewCommandFile($sessioneId);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            return false;
        }

        return @file_put_contents($file, $json, LOCK_EX) !== false;
    }

    private function clearAudioPreviewCommand(int $sessioneId): void
    {
        if ($sessioneId <= 0) {
            return;
        }

        $file = $this->audioPreviewCommandFile($sessioneId);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function getRequestHeader(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        if (isset($_SERVER[$key])) {
            return trim((string) $_SERVER[$key]);
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $headerName => $headerValue) {
                if (strcasecmp((string) $headerName, $name) === 0) {
                    return trim((string) $headerValue);
                }
            }
        }

        return null;
    }

    private function getAdminToken(): string
    {
        $token = getenv('ADMIN_TOKEN');
        return is_string($token) && $token !== '' ? $token : 'SUPERSEGRETO123';
    }

    private function isAdminAuthorized(): bool
    {
        $incoming = $this->getRequestHeader('X-ADMIN-TOKEN');

        if ($incoming === null || $incoming === '') {
            return false;
        }

        return hash_equals($this->getAdminToken(), $incoming);
    }

    private function loadCurrentQuestionForSession(int $sessioneId): ?array
    {
        if ($sessioneId <= 0) {
            return null;
        }

        $pdo = \App\Core\Database::getInstance();
        $stmt = $pdo->prepare(
            "SELECT d.*
             FROM sessioni s
             JOIN sessione_domande sd
               ON sd.sessione_id = s.id
              AND sd.posizione = s.domanda_corrente
             JOIN domande d ON d.id = sd.domanda_id
             WHERE s.id = :sessione_id
             LIMIT 1"
        );
        $stmt->execute(['sessione_id' => $sessioneId]);
        $row = $stmt->fetch() ?: null;
        return is_array($row) ? $row : null;
    }

    private function domandeColumnExists(\PDO $pdo, string $columnName): bool
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS c
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'domande'
               AND COLUMN_NAME = :column_name"
        );
        $stmt->execute(['column_name' => $columnName]);
        return (int) ($stmt->fetch()['c'] ?? 0) > 0;
    }

    private function domandeIndexExists(\PDO $pdo, string $indexName): bool
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS c
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'domande'
               AND INDEX_NAME = :index_name"
        );
        $stmt->execute(['index_name' => $indexName]);
        return (int) ($stmt->fetch()['c'] ?? 0) > 0;
    }

    private function normalizeFingerprintValue(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');
        $normalized = str_replace(["\r", "\n", "\t"], ' ', $normalized);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($transliterated) && $transliterated !== '') {
            $normalized = $transliterated;
        }

        $normalized = preg_replace('/[^a-z0-9 ]+/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', trim($normalized)) ?? trim($normalized);

        return $normalized;
    }

    private function questionTypeCodePrefix(?string $tipoDomanda): string
    {
        $map = [
            'CLASSIC' => 'CLS',
            'MEDIA' => 'MED',
            'SARABANDA' => 'SRB',
            'IMPOSTORE' => 'IMP',
            'MEME' => 'MEM',
            'MAJORITY' => 'MJR',
            'RANDOM_BONUS' => 'RND',
            'BOMB' => 'BMB',
            'CHAOS' => 'CHS',
            'AUDIO_PARTY' => 'AUD',
            'IMAGE_PARTY' => 'IMG',
            'FADE' => 'FAD',
        ];

        $key = strtoupper(trim((string) ($tipoDomanda ?? 'CLASSIC')));
        return $map[$key] ?? 'QST';
    }

    private function buildCodiceDomanda(int $domandaId, ?int $argomentoId, ?string $tipoDomanda): string
    {
        $prefix = $this->questionTypeCodePrefix($tipoDomanda);
        $arg = max(0, (int) ($argomentoId ?? 0));
        return sprintf('%s-A%03d-%05d', $prefix, $arg, max(1, $domandaId));
    }

    private function buildDomandaFingerprint(array $domanda, array $opzioni): string
    {
        $chunks = [];
        $chunks[] = 't:' . $this->normalizeFingerprintValue((string) ($domanda['testo'] ?? ''));
        $chunks[] = 'a:' . (int) ($domanda['argomento_id'] ?? 0);
        $chunks[] = 'y:' . $this->normalizeFingerprintValue((string) ($domanda['tipo_domanda'] ?? 'CLASSIC'));

        $opzioniNorm = [];
        foreach ($opzioni as $opzione) {
            $isCorretta = (int) ($opzione['corretta'] ?? 0) === 1 ? '1' : '0';
            $testo = $this->normalizeFingerprintValue((string) ($opzione['testo'] ?? ''));
            $opzioniNorm[] = $isCorretta . ':' . $testo;
        }

        sort($opzioniNorm, SORT_STRING);
        foreach ($opzioniNorm as $opt) {
            $chunks[] = 'o:' . $opt;
        }

        return sha1(implode('|', $chunks));
    }

    private function syncDomandaIdentityFields(\PDO $pdo, int $domandaId, bool $strictDuplicate = false): array
    {
        $stmtDomanda = $pdo->prepare(
            "SELECT id, testo, argomento_id, tipo_domanda, codice_domanda
             FROM domande
             WHERE id = :id
             LIMIT 1"
        );
        $stmtDomanda->execute(['id' => $domandaId]);
        $domanda = $stmtDomanda->fetch() ?: null;

        if (!$domanda) {
            return [];
        }

        $stmtOpzioni = $pdo->prepare(
            "SELECT id, testo, corretta
             FROM opzioni
             WHERE domanda_id = :domanda_id"
        );
        $stmtOpzioni->execute(['domanda_id' => $domandaId]);
        $opzioni = $stmtOpzioni->fetchAll() ?: [];

        // Il codice viene sempre riallineato al tipo/argomento correnti.
        // Esempio: CLS-A006-00123 -> SRB-A006-00123 se la domanda diventa SARABANDA.
        $codiceDomanda = $this->buildCodiceDomanda(
            (int) ($domanda['id'] ?? 0),
            isset($domanda['argomento_id']) ? (int) $domanda['argomento_id'] : null,
            (string) ($domanda['tipo_domanda'] ?? 'CLASSIC')
        );

        $fingerprint = $this->buildDomandaFingerprint($domanda, $opzioni);

        $checkDup = $pdo->prepare(
            "SELECT id
             FROM domande
             WHERE fingerprint_unico = :fingerprint
               AND id <> :id
             LIMIT 1"
        );
        $checkDup->execute([
            'fingerprint' => $fingerprint,
            'id' => $domandaId,
        ]);
        $dupRow = $checkDup->fetch() ?: null;
        $duplicateId = (int) ($dupRow['id'] ?? 0);

        if ($duplicateId > 0 && $strictDuplicate) {
            throw new \RuntimeException("Duplicato rilevato: domanda #{$domandaId} coincide con domanda #{$duplicateId}");
        }

        $fingerprintToSave = $duplicateId > 0 ? null : $fingerprint;

        $update = $pdo->prepare(
            "UPDATE domande
             SET codice_domanda = :codice_domanda,
                 fingerprint_unico = :fingerprint_unico
             WHERE id = :id"
        );
        $update->execute([
            'codice_domanda' => $codiceDomanda,
            'fingerprint_unico' => $fingerprintToSave,
            'id' => $domandaId,
        ]);

        return [
            'codice_domanda' => $codiceDomanda,
            'fingerprint_unico' => $fingerprintToSave,
            'duplicate_id' => $duplicateId > 0 ? $duplicateId : null,
        ];
    }

    private function ensureDomandeIdentitySchema(\PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        if (!$this->domandeColumnExists($pdo, 'codice_domanda')) {
            $pdo->exec("ALTER TABLE domande ADD COLUMN codice_domanda VARCHAR(80) NULL AFTER testo");
        }

        if (!$this->domandeColumnExists($pdo, 'fingerprint_unico')) {
            $pdo->exec("ALTER TABLE domande ADD COLUMN fingerprint_unico CHAR(40) NULL AFTER codice_domanda");
        }

        $stmt = $pdo->query(
            "SELECT id
             FROM domande
             WHERE codice_domanda IS NULL
                OR codice_domanda = ''
                OR fingerprint_unico IS NULL
                OR fingerprint_unico = ''"
        );
        $rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
        foreach ($rows as $row) {
            $this->syncDomandaIdentityFields($pdo, (int) ($row['id'] ?? 0), false);
        }

        if (!$this->domandeIndexExists($pdo, 'idx_domande_codice_domanda')) {
            $pdo->exec("CREATE INDEX idx_domande_codice_domanda ON domande (codice_domanda)");
        }

        if (!$this->domandeIndexExists($pdo, 'ux_domande_fingerprint_unico')) {
            try {
                $pdo->exec("CREATE UNIQUE INDEX ux_domande_fingerprint_unico ON domande (fingerprint_unico)");
            } catch (\Throwable $e) {
                // Se esistono storici incoerenti l'indice potrebbe fallire: la logica applicativa resta attiva.
            }
        }

        $done = true;
    }


    public function stato($sessioneId): void
    {
        $sessioneId = (int) $sessioneId;

        try {
            $service = new SessioneService($sessioneId);
            $service->verificaTimer();

            $pdo = \App\Core\Database::getInstance();
            $stmt = $pdo->prepare("SELECT * FROM sessioni WHERE id = ?");
            $stmt->execute([$sessioneId]);

            $sessione = $stmt->fetch() ?: null;

            if ($sessione) {
                $timerStart = (float) ($sessione['inizio_domanda'] ?? 0);
                $timerMax = (int) ((new Sistema())->get('durata_domanda') ?? 0);
                $revealUntil = (float) ($sessione['mostra_corretta_fino'] ?? 0);
                $now = round(microtime(true), 3);

                $sessione['timer_start'] = $timerStart;
                $sessione['timer_max'] = $timerMax;
                $sessione['show_correct'] = $revealUntil > $now;
                $sessione['reveal_until'] = $revealUntil > 0 ? $revealUntil : null;

                $currentQuestion = $this->loadCurrentQuestionForSession((int) ($sessione['id'] ?? 0));
                $modeMeta = $currentQuestion
                    ? (new \App\Services\Question\QuestionModeResolver())->resolveFromRow($currentQuestion)
                    : [];
                $currentType = strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? 'CLASSIC')));
                $hasImage = is_array($currentQuestion) && trim((string) ($currentQuestion['media_image_path'] ?? '')) !== '';
                $eligible = is_array($currentQuestion) && $currentType !== 'SARABANDA';
                $impostoreService = new ImpostoreModeService();
                $enabled = $eligible
                    ? $impostoreService->isEnabledForQuestion((int) ($sessione['id'] ?? 0), (int) ($currentQuestion['id'] ?? 0))
                    : false;
                $locked = in_array((string) ($sessione['stato'] ?? ''), ['domanda', 'conclusa'], true);

                $memeService = new MemeModeService();
                $memeState = $memeService->getRuntimeState((int) ($sessione['id'] ?? 0));
                $memeEnabled = $eligible
                    ? $memeService->isEnabledForQuestion((int) ($sessione['id'] ?? 0), (int) ($currentQuestion['id'] ?? 0))
                    : false;
                $imagePartyService = new ImagePartyModeService();
                $imagePartyEligible = $eligible && $hasImage;
                $imagePartyEnabled = $imagePartyEligible
                    ? $imagePartyService->isEnabledForQuestion((int) ($sessione['id'] ?? 0), (int) ($currentQuestion['id'] ?? 0))
                    : false;
                $fadeService = new FadeModeService();
                $fadeEligible = $eligible && $hasImage;
                $fadeEnabled = $fadeEligible
                    ? $fadeService->isEnabledForQuestion((int) ($sessione['id'] ?? 0), (int) ($currentQuestion['id'] ?? 0))
                    : false;
                $sarabandaAudioService = new SarabandaAudioModeService();
                $sarabandaAudioEligible = is_array($currentQuestion)
                    && $currentType === 'SARABANDA'
                    && trim((string) ($currentQuestion['media_audio_path'] ?? '')) !== '';
                $sarabandaReverseEnabled = $sarabandaAudioEligible
                    ? $sarabandaAudioService->isReverseEnabledForQuestion((int) ($sessione['id'] ?? 0), (int) ($currentQuestion['id'] ?? 0))
                    : false;
                $sarabandaFastForwardEnabled = $sarabandaAudioEligible
                    ? $sarabandaAudioService->isFastForwardEnabledForQuestion((int) ($sessione['id'] ?? 0), (int) ($currentQuestion['id'] ?? 0))
                    : false;
                $sarabandaAudioEnabled = $sarabandaAudioEligible
                    ? $sarabandaAudioService->isAudioEnabledForQuestion((int) ($sessione['id'] ?? 0), (int) ($currentQuestion['id'] ?? 0))
                    : false;
                $sarabandaFastForwardRate = $sarabandaAudioEligible
                    ? $sarabandaAudioService->getFastForwardRateForQuestion((int) ($sessione['id'] ?? 0), (int) ($currentQuestion['id'] ?? 0))
                    : SarabandaAudioModeService::DEFAULT_FAST_FORWARD_RATE;

                $sessione['impostore_enabled'] = $enabled;
                $sessione['impostore_eligible'] = (bool) $eligible;
                $sessione['impostore_locked'] = $locked;
                $sessione['impostore_question_id'] = (int) ($currentQuestion['id'] ?? 0);
                $sessione['meme_enabled'] = $memeEnabled;
                $sessione['meme_eligible'] = (bool) $eligible;
                $sessione['meme_locked'] = $locked;
                $sessione['meme_question_id'] = (int) ($currentQuestion['id'] ?? 0);
                $sessione['meme_text'] = trim((string) ($memeState['meme_text'] ?? ''));
                $sessione['image_party_enabled'] = $imagePartyEnabled;
                $sessione['image_party_eligible'] = (bool) $imagePartyEligible;
                $sessione['image_party_locked'] = $locked;
                $sessione['image_party_question_id'] = (int) ($currentQuestion['id'] ?? 0);
                $sessione['fade_enabled'] = $fadeEnabled;
                $sessione['fade_eligible'] = (bool) $fadeEligible;
                $sessione['fade_locked'] = $locked;
                $sessione['fade_question_id'] = (int) ($currentQuestion['id'] ?? 0);
                $sessione['sarabanda_reverse_enabled'] = $sarabandaReverseEnabled;
                $sessione['sarabanda_fast_forward_enabled'] = $sarabandaFastForwardEnabled;
                $sessione['sarabanda_fast_forward_rate'] = $sarabandaFastForwardRate;
                $sessione['sarabanda_audio_enabled'] = $sarabandaAudioEnabled;
                $sessione['sarabanda_audio_eligible'] = (bool) $sarabandaAudioEligible;
                $sessione['sarabanda_audio_locked'] = $locked;
                $sessione['sarabanda_audio_question_id'] = (int) ($currentQuestion['id'] ?? 0);
            }

            $domandaPayload = null;
            if (is_array($sessione) && (string) ($sessione['stato'] ?? '') === 'domanda') {
                $domandaPayload = $service->domandaCorrente();
            }

            $this->json([
                'success' => true,
                'server_now' => round(microtime(true), 3),
                'sessione' => $sessione,
                'domanda' => $domandaPayload,
            ]);

        } catch (Throwable $e) {

            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function domanda($sessioneId): void
    {
        $sessioneId = (int) $sessioneId;

        try {

            $service = new SessioneService($sessioneId);

            $this->json([
                'success' => true,
                'domanda' => $service->domandaCorrente()
            ]);

        } catch (Throwable $e) {

            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function classifica($sessioneId): void
    {
        $sessioneId = (int) $sessioneId;

        try {

            $service = new SessioneService($sessioneId);

            $this->json([
                'success' => true,
                'classifica' => $service->classifica()
            ]);

        } catch (Throwable $e) {

            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function audioPreviewStato($sessioneId): void
    {
        $sessioneId = (int) $sessioneId;

        try {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');

            $preview = $this->readAudioPreviewCommand($sessioneId);
            $this->json([
                'success' => true,
                'sessione_id' => $sessioneId,
                'preview' => $preview,
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function audioPreviewStarted($sessioneId): void
    {
        $sessioneId = (int) $sessioneId;

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->json([
                    'success' => false,
                    'error' => 'Metodo non consentito'
                ]);
                return;
            }

            $incomingToken = trim((string) ($_POST['token'] ?? ''));
            $preview = $this->readAudioPreviewCommand($sessioneId);
            $storedToken = trim((string) ($preview['token'] ?? ''));

            if ($incomingToken !== '') {
                if (!$preview || $storedToken === '' || !hash_equals($storedToken, $incomingToken)) {
                    $this->json([
                        'success' => false,
                        'error' => 'Token preview non valido'
                    ]);
                    return;
                }

                if (isset($preview['acknowledged_at'], $preview['start_at'])) {
                    $this->json([
                        'success' => true,
                        'sessione_id' => $sessioneId,
                        'preview' => $preview,
                        'start_at' => (int) $preview['start_at'],
                    ]);
                    return;
                }
            }

            $service = new SessioneService($sessioneId);
            $domanda = $service->domandaCorrente();
            $tipoDomanda = strtoupper(trim((string) ($domanda['tipo_domanda'] ?? 'CLASSIC')));
            if (!$domanda || !is_array($domanda)) {
                $this->json([
                    'success' => false,
                    'error' => 'Domanda corrente non disponibile'
                ]);
                return;
            }

            $audioPath = trim((string) ($domanda['media_audio_path'] ?? ''));
            if ($audioPath === '') {
                $this->json([
                    'success' => false,
                    'error' => 'La domanda corrente non ha audio'
                ]);
                return;
            }

            $audioModeService = new SarabandaAudioModeService();
            $audioEnabled = $audioModeService->isAudioEnabledForQuestion($sessioneId, (int) ($domanda['id'] ?? 0));
            $reverseEnabled = $audioModeService->isReverseEnabledForQuestion($sessioneId, (int) ($domanda['id'] ?? 0));
            $fastForwardEnabled = $audioModeService->isFastForwardEnabledForQuestion($sessioneId, (int) ($domanda['id'] ?? 0));
            $fastForwardRate = $audioModeService->getFastForwardRateForQuestion($sessioneId, (int) ($domanda['id'] ?? 0));

            if (!$preview) {
                $preview = [
                    'token' => $incomingToken,
                    'sessione_id' => $sessioneId,
                    'domanda_id' => (int) ($domanda['id'] ?? 0),
                    'audio_path' => $audioPath,
                    'preview_sec' => $reverseEnabled
                        ? max(10, (int) ($domanda['media_audio_preview_sec'] ?? 0))
                        : ($fastForwardEnabled
                            ? max(self::SARABANDA_FAST_FORWARD_SOURCE_SEC, (int) ($domanda['media_audio_preview_sec'] ?? 0))
                            : (int) ($domanda['media_audio_preview_sec'] ?? 0)),
                    'playback_duration_sec' => $fastForwardEnabled
                        ? round(max(self::SARABANDA_FAST_FORWARD_SOURCE_SEC, (int) ($domanda['media_audio_preview_sec'] ?? 0)) / max(1, $fastForwardRate), 3)
                        : ($reverseEnabled
                            ? max(10, (int) ($domanda['media_audio_preview_sec'] ?? 0))
                            : (int) ($domanda['media_audio_preview_sec'] ?? 0)),
                    'audio_enabled' => $audioEnabled,
                    'reverse_audio' => $reverseEnabled,
                    'fast_forward_audio' => $fastForwardEnabled,
                    'fast_forward_rate' => $fastForwardRate,
                    'created_at' => round(microtime(true), 3),
                ];
            }

            $previewSec = (int) ($preview['preview_sec'] ?? (int) ($domanda['media_audio_preview_sec'] ?? 0));
            if ((int) ($preview['reverse_audio'] ?? 0) === 1) {
                $previewSec = max(10, $previewSec);
                $preview['preview_sec'] = $previewSec;
            } elseif ((int) ($preview['fast_forward_audio'] ?? 0) === 1) {
                $previewSec = max(self::SARABANDA_FAST_FORWARD_SOURCE_SEC, $previewSec);
                $preview['preview_sec'] = $previewSec;
            }
            $clientPlaybackDurationSec = (float) ($_POST['playback_duration_sec'] ?? 0);
            $playbackDurationSec = $clientPlaybackDurationSec > 0
                ? $clientPlaybackDurationSec
                : (float) ($preview['playback_duration_sec'] ?? 0);
            if ($playbackDurationSec <= 0) {
                $playbackDurationSec = (int) ($preview['fast_forward_audio'] ?? 0) === 1
                    ? round($previewSec / max(1, (float) ($preview['fast_forward_rate'] ?? $fastForwardRate)), 3)
                    : $previewSec;
                $preview['playback_duration_sec'] = $playbackDurationSec;
            } else {
                $preview['playback_duration_sec'] = $playbackDurationSec;
            }

            if ($tipoDomanda !== 'SARABANDA') {
                $preview['acknowledged_at'] = round(microtime(true), 3);
                $this->clearAudioPreviewCommand($sessioneId);
                $this->json([
                    'success' => true,
                    'sessione_id' => $sessioneId,
                    'preview' => $preview,
                    'start_at' => null,
                ]);
                return;
            }

            $pdo = \App\Core\Database::getInstance();
            $stmt = $pdo->prepare("SELECT stato FROM sessioni WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $sessioneId]);
            $sessioneRow = $stmt->fetch() ?: null;
            $statoCorrente = (string) ($sessioneRow['stato'] ?? '');

            if ($statoCorrente !== 'domanda') {
                $this->json([
                    'success' => false,
                    'error' => 'La sessione non e in stato domanda'
                ]);
                return;
            }

            $startAt = round(microtime(true) + max(0, $playbackDurationSec), 3);
            $up = $pdo->prepare(
                "UPDATE sessioni
                 SET inizio_domanda = :start_at
                 WHERE id = :id"
            );
            $up->execute([
                'start_at' => $startAt,
                'id' => $sessioneId,
            ]);

            $preview['acknowledged_at'] = round(microtime(true), 3);
            $preview['start_at'] = $startAt;
            $this->clearAudioPreviewCommand($sessioneId);

            $this->json([
                'success' => true,
                'sessione_id' => $sessioneId,
                'preview' => $preview,
                'start_at' => $startAt,
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /* ======================
       PLAYER ACTIONS
    ====================== */

public function join($sessioneId): void
{
    $sessioneId = (int) $sessioneId;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $this->json([
            'success' => false,
            'error' => 'Metodo non consentito'
        ]);
        return;
    }

    $nome = trim($_POST['nome'] ?? '');

    if ($nome === '') {
        $this->json([
            'success' => false,
            'error' => 'Nome non valido'
        ]);
        return;
    }

    try {

        // BLOCCO: nome gia' usato nella stessa sessione (richiede approvazione admin)
        $pdo = \App\Core\Database::getInstance();

        $check = $pdo->prepare("
            SELECT p.id
            FROM partecipazioni p
            JOIN utenti u ON u.id = p.utente_id
            WHERE p.sessione_id = :sessione_id
              AND LOWER(u.nome) = LOWER(:nome)
            LIMIT 1
        ");

        $check->execute([
            'sessione_id' => $sessioneId,
            'nome' => $nome
        ]);

        $esistente = $check->fetch();

        if ($esistente) {
            $joinRichiesta = new JoinRichiesta();
            $richiesta = $joinRichiesta->creaORiprendiPending(
                $sessioneId,
                $nome,
                (int) $esistente['id']
            );

            $this->json([
                'success' => false,
                'requires_approval' => true,
                'request_id' => (int) $richiesta['id'],
                'error' => 'Nome gia\' utilizzato: richiesta inviata alla regia'
            ]);
            return;
        }

        // ? Se non esiste, crea utente temporaneo e partecipa
        $utenteModel = new Utente();
        $utenteId = $utenteModel->creaTemporaneo($nome);

        $partecipazioneModel = new Partecipazione();
        $partecipazioneId = $partecipazioneModel->entra($sessioneId, $utenteId);

        $partecipazione = $partecipazioneModel->trova($partecipazioneId);

        $this->json([
            'success' => true,
            'utente_id' => $utenteId,
            'partecipazione_id' => $partecipazioneId,
            'capitale' => $partecipazione['capitale_attuale'] ?? 0
        ]);

    } catch (\Throwable $e) {

        $this->json([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

    public function joinStato($sessioneId): void
    {
        $sessioneId = (int) $sessioneId;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json([
                'success' => false,
                'error' => 'Metodo non consentito'
            ]);
            return;
        }

        $richiestaId = (int) ($_POST['request_id'] ?? 0);

        if ($richiestaId <= 0) {
            $this->json([
                'success' => false,
                'error' => 'Richiesta non valida'
            ]);
            return;
        }

        try {
            $joinRichiesta = new JoinRichiesta();
            $richiesta = $joinRichiesta->trovaPerId($richiestaId, $sessioneId);

            if (!$richiesta) {
                $this->json([
                    'success' => false,
                    'error' => 'Richiesta non trovata'
                ]);
                return;
            }

            $response = [
                'success' => true,
                'stato' => $richiesta['stato']
            ];

            if ($richiesta['stato'] === 'approvata' && !empty($richiesta['partecipazione_id'])) {
                $partecipazione = (new Partecipazione())->trova((int) $richiesta['partecipazione_id']);

                if ($partecipazione) {
                    $response['partecipazione_id'] = (int) $partecipazione['id'];
                    $response['capitale'] = (int) ($partecipazione['capitale_attuale'] ?? 0);
                }
            }

            $this->json($response);

        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    public function puntata($sessioneId): void
    {
        $sessioneId = (int) $sessioneId;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json([
                'success' => false,
                'error' => 'Metodo non consentito'
            ]);
            return;
        }

        $partecipazioneId = (int) ($_POST['partecipazione_id'] ?? 0);
        $importo = (int) ($_POST['puntata'] ?? 0);

        if ($partecipazioneId <= 0 || $importo <= 0) {
            $this->json([
                'success' => false,
                'error' => 'Dati non validi'
            ]);
            return;
        }

        try {

            $service = new SessioneService($sessioneId);

            if (!$service->puoPuntare()) {
                $this->json([
                    'success' => false,
                    'error' => 'Non e\' il momento di puntare'
                ]);
                return;
            }

            $partecipazione = new Partecipazione();
            $ok = $partecipazione->registraPuntata($partecipazioneId, $importo);

            if (!$ok) {
                $this->json([
                    'success' => false,
                    'error' => 'Puntata non valida'
                ]);
                return;
            }

            $service->salvaPuntataLive($partecipazioneId, $importo);

            $this->json([
                'success' => true,
                'puntata' => $importo
            ]);

        } catch (\Throwable $e) {

            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function risposta($sessioneId): void
    {
        $sessioneId = (int) $sessioneId;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json([
                'success' => false,
                'error' => 'Metodo non consentito'
            ]);
            return;
        }

        $partecipazioneId = (int) ($_POST['partecipazione_id'] ?? 0);
        $domandaId = (int) ($_POST['domanda_id'] ?? 0);
        $opzioneId = (int) ($_POST['opzione_id'] ?? 0);
        $tempoClient = isset($_POST['tempo_client']) ? (float) $_POST['tempo_client'] : null;

        if ($partecipazioneId <= 0 || $domandaId <= 0 || $opzioneId <= 0) {
            $this->json([
                'success' => false,
                'error' => 'Dati non validi'
            ]);
            return;
        }

        try {

            $service = new SessioneService($sessioneId);

            if (!$service->puoRispondere()) {
                $motivoBlocco = method_exists($service, 'motivoBloccoRisposta')
                    ? $service->motivoBloccoRisposta()
                    : null;
                $this->json([
                    'success' => false,
                    'error' => $motivoBlocco === 'tempo_scaduto'
                        ? 'Tempo utile scaduto'
                        : 'Non e\' il momento di rispondere'
                ]);
                return;
            }

            $partecipazione = new Partecipazione();

            $risultato = $partecipazione->registraRisposta(
                $partecipazioneId,
                $domandaId,
                $opzioneId,
                $tempoClient
            );

            if (!$risultato) {
                $this->json([
                    'success' => false,
                    'error' => $partecipazione->getLastError() ?: 'Errore registrazione risposta'
                ]);
                return;
            }

            $service->rimuoviPuntataLive($partecipazioneId);

            $this->json([
                'success' => true,
                'risultato' => $risultato
            ]);

        } catch (\Throwable $e) {

            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }


    public function mediaAttiva(): void
    {
        try {
            $media = (new ScreenMedia())->mediaAttivaRandom('screen');

            $this->json([
                'success' => true,
                'media' => $media
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }




    /* ======================
       ADMIN CONTROL
    ====================== */

    public function admin($action, $sessioneId): void
    {
        $sessioneId = (int) $sessioneId;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json([
                'success' => false,
                'error' => 'Metodo non consentito'
            ]);
            return;
        }

        if (!$this->isAdminAuthorized()) {
            http_response_code(403);
            $this->json([
                'success' => false,
                'error' => 'Token admin non valido'
            ]);
            return;
        }

        try {
            if ($this->handleAdminPhaseAction((string) $action, $sessioneId)) {
                return;
            }

            if ($this->handleAdminSessionAction((string) $action, $sessioneId)) {
                return;
            }

            if ($this->handleAdminRuntimeAction((string) $action, $sessioneId)) {
                return;
            }

            if ($this->handleAdminQuestionAction((string) $action, $sessioneId)) {
                return;
            }

            if ($this->handleAdminSettingsAction((string) $action)) {
                return;
            }

            if ($this->handleAdminMediaAction((string) $action)) {
                return;
            }

            if ($this->handleAdminUploadAction((string) $action)) {
                return;
            }

            $this->json([
                'success' => false,
                'error' => 'Azione non valida'
            ]);
            return;
        } catch (\Throwable $e) {

            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}



