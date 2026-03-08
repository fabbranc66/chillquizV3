<?php

namespace App\Controllers;

use App\Models\Partecipazione;
use App\Models\Risposta;
use App\Models\Sessione;
use App\Models\Sistema;
use App\Models\JoinRichiesta;
use App\Models\Utente;
use App\Models\ScreenMedia;
use App\Models\AppSettings;
use App\Services\Admin\SessionImageSearchService;
use App\Services\SessioneService;
use Throwable;

class ApiController
{
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
            }

            $this->json([
                'success' => true,
                'sessione' => $sessione
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

            if (!$preview) {
                $preview = [
                    'token' => $incomingToken,
                    'sessione_id' => $sessioneId,
                    'domanda_id' => (int) ($domanda['id'] ?? 0),
                    'audio_path' => $audioPath,
                    'preview_sec' => (int) ($domanda['media_audio_preview_sec'] ?? 0),
                    'created_at' => round(microtime(true), 3),
                ];
            }

            $previewSec = (int) ($preview['preview_sec'] ?? (int) ($domanda['media_audio_preview_sec'] ?? 0));

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

            $startAt = round(microtime(true) + max(0, $previewSec), 3);
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

        // ?? BLOCCO: nome gi� usato nella stessa sessione (richiede approvazione admin)
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
                'error' => 'Nome gi� utilizzato: richiesta inviata alla regia'
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
                    'error' => 'Non � il momento di puntare'
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
                $this->json([
                    'success' => false,
                    'error' => 'Non � il momento di rispondere'
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
                    'error' => 'Errore registrazione risposta'
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

            switch ($action) {

                case 'nuova-sessione':
                    $nomeSessione = trim((string) ($_POST['nome'] ?? $_POST['sessione_nome'] ?? ''));

                    $numeroDomande = (int) ($_POST['numero_domande'] ?? 0);
                    $poolTipo = trim((string) ($_POST['pool_tipo'] ?? ''));
                    $argomentoRaw = $_POST['argomento_id'] ?? null;
                    $selezioneTipo = trim((string) ($_POST['selezione_tipo'] ?? ''));

                    $configInput = [
                        'numero_domande' => $numeroDomande > 0 ? $numeroDomande : null,
                        'pool_tipo' => $poolTipo,
                        'argomento_id' => $argomentoRaw,
                        'selezione_tipo' => $selezioneTipo,
                    ];

                    $sessioneModel = new Sessione();
                    $nuovaId = $sessioneModel->crea(1, $nomeSessione, $configInput);
                    $sessioneCreata = $sessioneModel->trova($nuovaId) ?: [];

                    $this->json([
                        'success' => true,
                        'action' => $action,
                        'sessione_id' => $nuovaId,
                        'nome_sessione' => $nomeSessione !== '' ? $nomeSessione : null,
                        'numero_domande' => (int) ($sessioneCreata['numero_domande'] ?? 0),
                        'pool_tipo' => (string) ($sessioneCreata['pool_tipo'] ?? ''),
                        'argomento_id' => $sessioneCreata['argomento_id'] ?? null,
                        'selezione_tipo' => (string) ($sessioneCreata['selezione_tipo'] ?? ''),
                    ]);
                    return;
                case 'avvia-puntata':
                    $this->clearAudioPreviewCommand($sessioneId);
                    (new SessioneService($sessioneId))->avviaPuntata();
                    break;

                case 'avvia-domanda':
                    $this->clearAudioPreviewCommand($sessioneId);
                    (new SessioneService($sessioneId))->avviaDomanda();
                    break;

                case 'risultati':
                    $this->clearAudioPreviewCommand($sessioneId);
                    (new SessioneService($sessioneId))->chiudiDomanda();
                    break;

                case 'prossima':
                    $this->clearAudioPreviewCommand($sessioneId);
                    (new SessioneService($sessioneId))->prossimaFase();
                    break;

                case 'riavvia':
                    $this->clearAudioPreviewCommand($sessioneId);
                    (new SessioneService($sessioneId))->resetTotale();
                    break;

                case 'audio-preview':
                    $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
                    if ($targetSessioneId <= 0) {
                        $this->json([
                            'success' => false,
                            'error' => 'Sessione non valida'
                        ]);
                        return;
                    }

                    $service = new SessioneService($targetSessioneId);
                    $domanda = $service->domandaCorrente();

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

                    $previewSec = (int) ($domanda['media_audio_preview_sec'] ?? 0);
                    $tipoDomanda = strtoupper(trim((string) ($domanda['tipo_domanda'] ?? 'CLASSIC')));

                    if ($tipoDomanda === 'SARABANDA' && $previewSec <= 0) {
                        $this->json([
                            'success' => false,
                            'error' => 'Per SARABANDA imposta Intro secondi maggiore di 0',
                        ]);
                        return;
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
                        return;
                    }

                    $this->json([
                        'success' => true,
                        'action' => $action,
                        'sessione_id' => $targetSessioneId,
                        'preview' => $payload,
                    ]);
                    return;

                case 'join-richieste':
                    $joinRichiesta = new JoinRichiesta();
                    $this->json([
                        'success' => true,
                        'action' => $action,
                        'richieste' => $joinRichiesta->listaPending($sessioneId)
                    ]);
                    return;

                case 'sessioni-lista':
                    $sessioneModel = new Sessione();
                    $corrente = $sessioneModel->corrente();

                    $this->json([
                        'success' => true,
                        'action' => $action,
                        'sessione_corrente_id' => (int) ($corrente['id'] ?? 0),
                        'sessioni' => $sessioneModel->disponibili(200)
                    ]);
                    return;

                case 'argomenti-lista':
                    $pdo = \App\Core\Database::getInstance();
                    $stmt = $pdo->query("SELECT id, nome FROM argomenti ORDER BY nome ASC");
                    $this->json([
                        'success' => true,
                        'action' => $action,
                        'argomenti' => $stmt ? ($stmt->fetchAll() ?: []) : []
                    ]);
                    return;

                case 'set-corrente':
                    $targetSessioneId = (int) ($_POST['sessione_id'] ?? 0);
                    if ($targetSessioneId <= 0) {
                        $this->json([
                            'success' => false,
                            'error' => 'Sessione non valida'
                        ]);
                        return;
                    }

                    $ok = (new Sessione())->impostaCorrente($targetSessioneId);
                    $this->json([
                        'success' => $ok,
                        'action' => $action,
                        'sessione_id' => $targetSessioneId,
                        'error' => $ok ? null : 'Impossibile impostare sessione corrente'
                    ]);
                    return;

                case 'sessione-update':
                    $targetSessioneId = (int) ($_POST['sessione_id'] ?? 0);
                    if ($targetSessioneId <= 0) {
                        $this->json([
                            'success' => false,
                            'error' => 'Sessione non valida'
                        ]);
                        return;
                    }

                    $nomeSessione = trim((string) ($_POST['nome_sessione'] ?? $_POST['nome'] ?? ''));
                    $numeroDomande = (int) ($_POST['numero_domande'] ?? 0);
                    $poolTipo = trim((string) ($_POST['pool_tipo'] ?? 'tutti'));
                    $argomentoRaw = $_POST['argomento_id'] ?? null;
                    $selezioneTipo = trim((string) ($_POST['selezione_tipo'] ?? 'random'));

                    $sessioneModel = new Sessione();
                    $ok = $sessioneModel->aggiornaSnapshot($targetSessioneId, [
                        'nome_sessione' => $nomeSessione,
                        'numero_domande' => $numeroDomande,
                        'pool_tipo' => $poolTipo,
                        'argomento_id' => $argomentoRaw,
                        'selezione_tipo' => $selezioneTipo,
                    ]);

                    $aggiornata = $sessioneModel->trova($targetSessioneId) ?: null;

                    $this->json([
                        'success' => $ok,
                        'action' => $action,
                        'sessione_id' => $targetSessioneId,
                        'sessione' => $aggiornata,
                        'error' => $ok ? null : 'Impossibile aggiornare sessione'
                    ]);
                    return;

                case 'domande-sessione':
                    $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
                    if ($targetSessioneId <= 0) {
                        $this->json([
                            'success' => false,
                            'error' => 'Sessione non valida'
                        ]);
                        return;
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
                    return;

                case 'sessione-image-search':
                    $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
                    if ($targetSessioneId <= 0) {
                        $corrente = (new Sessione())->corrente();
                        $targetSessioneId = (int) ($corrente['id'] ?? 0);
                    }

                    if ($targetSessioneId <= 0) {
                        $this->json([
                            'success' => false,
                            'error' => 'Sessione corrente non disponibile'
                        ]);
                        return;
                    }

                    $report = (new SessionImageSearchService())->analyzeSession($targetSessioneId);
                    $this->json([
                        'success' => true,
                        'action' => $action,
                        'sessione_id' => $targetSessioneId,
                        'report' => $report,
                    ]);
                    return;

                case 'domanda-dettaglio':
                    $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
                    $domandaId = (int) ($_POST['domanda_id'] ?? 0);

                    if ($targetSessioneId <= 0) {
                        $this->json([
                            'success' => false,
                            'error' => 'Sessione non valida'
                        ]);
                        return;
                    }

                    if ($domandaId <= 0) {
                        $this->json([
                            'success' => false,
                            'error' => 'Domanda non valida'
                        ]);
                        return;
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
                        return;
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
                        return;
                    }

                    $this->json([
                        'success' => true,
                        'action' => $action,
                        'sessione_id' => $targetSessioneId,
                        'domanda_id' => $domandaId,
                        'domanda' => $domanda,
                    ]);
                    return;

                case 'domanda-update':
                    $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
                    if ($targetSessioneId <= 0) {
                        $this->json([
                            'success' => false,
                            'error' => 'Sessione non valida'
                        ]);
                        return;
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
                        return;
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
                        return;
                    }

                    $tipoRaw = strtoupper(trim((string) ($_POST['tipo_domanda'] ?? 'CLASSIC')));
                    $allowedTipi = [
                        'CLASSIC',
                        'MEDIA',
                        'SARABANDA',
                        'IMPOSTORE',
                        'MEME',
                        'MAJORITY',
                        'RANDOM_BONUS',
                        'BOMB',
                        'CHAOS',
                        'AUDIO_PARTY',
                        'IMAGE_PARTY',
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
                            return;
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
                    return;

                case 'settings-get':
                    $this->json([
                        'success' => true,
                        'settings' => (new AppSettings())->all()
                    ]);
                    return;

                case 'settings-save':
                    $settingsModel = new AppSettings();

                    $rawConfig = (string) ($_POST['configurazioni_json'] ?? '{}');
                    $decoded = json_decode($rawConfig, true);
                    $configurazioni = is_array($decoded) ? $decoded : [];

                    $showModuleTags = (int) ($_POST['show_module_tags'] ?? (($configurazioni['show_module_tags'] ?? '1'))) === 1;
                    $configurazioni['show_module_tags'] = $showModuleTags ? '1' : '0';

                    $settingsModel->saveConfigurazioni($configurazioni);

                    $this->json([
                        'success' => true,
                        'settings' => $settingsModel->all()
                    ]);
                    return;

                case 'media-list':
                    $this->json([
                        'success' => true,
                        'media' => (new ScreenMedia())->lista('screen')
                    ]);
                    return;

                case 'media-upload':
                    if (!isset($_FILES['immagine']) || !is_array($_FILES['immagine'])) {
                        $this->json([
                            'success' => false,
                            'error' => 'File media mancante'
                        ]);
                        return;
                    }

                    $file = $_FILES['immagine'];

                    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        $this->json([
                            'success' => false,
                            'error' => 'Upload non valido'
                        ]);
                        return;
                    }

                    $maxSizeBytes = 12 * 1024 * 1024;
                    $size = (int) ($file['size'] ?? 0);
                    if ($size <= 0 || $size > $maxSizeBytes) {
                        $this->json([
                            'success' => false,
                            'error' => 'Dimensione file non valida (max 12MB)'
                        ]);
                        return;
                    }

                    $tmpName = $file['tmp_name'] ?? '';
                    $detectedMime = @mime_content_type($tmpName) ?: '';
                    $isImage = strpos((string) $detectedMime, 'image/') === 0;
                    $isAudio = strpos((string) $detectedMime, 'audio/') === 0;

                    if (!$isImage && !$isAudio) {
                        $this->json([
                            'success' => false,
                            'error' => 'Formato non supportato: carica immagine o audio'
                        ]);
                        return;
                    }

                    $originalName = (string) ($file['name'] ?? '');
                    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    $allowedImageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $allowedAudioExt = ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'webm'];
                    $allowedExt = $isImage ? $allowedImageExt : $allowedAudioExt;
                    if (!in_array($ext, $allowedExt, true)) {
                        $this->json([
                            'success' => false,
                            'error' => 'Estensione non consentita'
                        ]);
                        return;
                    }

                    $subDir = $isImage ? 'image' : 'audio';
                    $uploadDir = BASE_PATH . '/public/upload/' . $subDir;
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                        $this->json([
                            'success' => false,
                            'error' => 'Impossibile creare cartella upload'
                        ]);
                        return;
                    }

                    $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', pathinfo($originalName, PATHINFO_FILENAME));
                    $safeBase = trim((string) $safeBase, '-');
                    if ($safeBase === '') {
                        $safeBase = 'media';
                    }

                    $fileName = $safeBase . '-' . time() . '-' . random_int(1000, 9999) . '.' . $ext;
                    $destPath = $uploadDir . '/' . $fileName;

                    if (!move_uploaded_file($tmpName, $destPath)) {
                        $this->json([
                            'success' => false,
                            'error' => 'Errore salvataggio file'
                        ]);
                        return;
                    }

                    $titolo = trim((string) ($_POST['titolo'] ?? ''));
                    if ($titolo === '') {
                        $titolo = pathinfo($originalName, PATHINFO_FILENAME) ?: ($isImage ? 'Immagine' : 'Audio');
                    }

                    $filePath = '/upload/' . $subDir . '/' . $fileName;
                    $tipoFile = $isImage ? 'image' : 'audio';
                    $id = (new ScreenMedia())->crea($titolo, $filePath, 'screen', $tipoFile);

                    $this->json([
                        'success' => true,
                        'media_id' => $id,
                        'file_path' => $filePath,
                        'tipo_file' => $tipoFile
                    ]);
                    return;

                case 'media-attiva':
                    $mediaId = (int) ($_POST['media_id'] ?? 0);
                    $attiva = (int) ($_POST['attiva'] ?? 1) === 1;
                    if ($mediaId <= 0) {
                        $this->json([
                            'success' => false,
                            'error' => 'Media non valido'
                        ]);
                        return;
                    }

                    $ok = (new ScreenMedia())->impostaAttiva($mediaId, $attiva, 'screen');
                    $this->json([
                        'success' => $ok,
                        'media_id' => $mediaId,
                        'attiva' => $attiva,
                        'error' => $ok ? null : 'Media non trovato'
                    ]);
                    return;

                case 'media-disattiva':
                    $ok = (new ScreenMedia())->disattivaTutte('screen');
                    $this->json([
                        'success' => $ok
                    ]);
                    return;

                case 'media-elimina':
                    $mediaId = (int) ($_POST['media_id'] ?? 0);
                    if ($mediaId <= 0) {
                        $this->json([
                            'success' => false,
                            'error' => 'Media non valido'
                        ]);
                        return;
                    }

                    $mediaModel = new ScreenMedia();
                    $media = $mediaModel->trova($mediaId, 'screen');

                    if (!$media) {
                        $this->json([
                            'success' => false,
                            'error' => 'Media non trovato'
                        ]);
                        return;
                    }

                    $ok = $mediaModel->elimina($mediaId, 'screen');

                    if ($ok) {
                        $file = BASE_PATH . '/public' . ($media['file_path'] ?? '');
                        if (is_file($file)) {
                            @unlink($file);
                        }
                    }

                    $this->json([
                        'success' => $ok
                    ]);
                    return;

                case 'domanda-media-list':
                    $mediaModel = new ScreenMedia();
                    $listDomanda = $mediaModel->lista('domanda');

                    $merged = [];
                    $seen = [];

                    foreach ($listDomanda as $row) {
                        $filePath = (string) ($row['file_path'] ?? '');
                        $tipoFile = (string) ($row['tipo_file'] ?? '');
                        $isDomandaAudio = $tipoFile === 'audio' && strpos($filePath, '/upload/domanda/audio/') === 0;
                        $isDomandaImage = $tipoFile === 'image' && strpos($filePath, '/upload/domanda/image/') === 0;
                        if (!$isDomandaAudio && !$isDomandaImage) {
                            continue;
                        }
                        $key = $tipoFile . '|' . $filePath;
                        if ($filePath === '' || isset($seen[$key])) {
                            continue;
                        }
                        $seen[$key] = true;
                        $merged[] = $row;
                    }

                    // Aggiunge anche file presenti su disco (copiati manualmente)
                    // non ancora registrati in tabella screen_media.
                    $scanSources = [
                        ['dir' => BASE_PATH . '/public/upload/domanda/audio', 'urlPrefix' => '/upload/domanda/audio/', 'tipo' => 'audio'],
                        ['dir' => BASE_PATH . '/public/upload/domanda/image', 'urlPrefix' => '/upload/domanda/image/', 'tipo' => 'image'],
                    ];

                    foreach ($scanSources as $src) {
                        $dir = (string) ($src['dir'] ?? '');
                        $urlPrefix = (string) ($src['urlPrefix'] ?? '');
                        $tipo = (string) ($src['tipo'] ?? '');

                        if ($dir === '' || !is_dir($dir)) {
                            continue;
                        }

                        $files = @scandir($dir);
                        if (!is_array($files)) {
                            continue;
                        }

                        foreach ($files as $file) {
                            if ($file === '.' || $file === '..') {
                                continue;
                            }

                            $fullPath = $dir . DIRECTORY_SEPARATOR . $file;
                            if (!is_file($fullPath)) {
                                continue;
                            }

                            $filePath = $urlPrefix . $file;
                            $key = $tipo . '|' . $filePath;
                            if (isset($seen[$key])) {
                                continue;
                            }

                            $seen[$key] = true;
                            $merged[] = [
                                'id' => 0,
                                'titolo' => pathinfo($file, PATHINFO_FILENAME),
                                'file_path' => $filePath,
                                'contesto' => 'domanda',
                                'tipo_file' => $tipo,
                                'attiva' => 0,
                                'creato_il' => null,
                            ];
                        }
                    }

                    $this->json([
                        'success' => true,
                        'media' => $merged
                    ]);
                    return;

                case 'domanda-media-upload':
                    if (!isset($_FILES['media_file']) || !is_array($_FILES['media_file'])) {
                        $this->json([
                            'success' => false,
                            'error' => 'File media mancante'
                        ]);
                        return;
                    }

                    $file = $_FILES['media_file'];

                    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        $this->json([
                            'success' => false,
                            'error' => 'Upload non valido'
                        ]);
                        return;
                    }

                    $maxSizeBytes = 16 * 1024 * 1024;
                    $size = (int) ($file['size'] ?? 0);
                    if ($size <= 0 || $size > $maxSizeBytes) {
                        $this->json([
                            'success' => false,
                            'error' => 'Dimensione file non valida (max 16MB)'
                        ]);
                        return;
                    }

                    $tmpName = (string) ($file['tmp_name'] ?? '');
                    $detectedMime = (string) (@mime_content_type($tmpName) ?: '');
                    $isImage = strpos($detectedMime, 'image/') === 0;
                    $isAudio = strpos($detectedMime, 'audio/') === 0;

                    if (!$isImage && !$isAudio) {
                        $this->json([
                            'success' => false,
                            'error' => 'Formato non supportato: carica immagine o audio'
                        ]);
                        return;
                    }

                    $originalName = (string) ($file['name'] ?? '');
                    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                    $allowedImageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $allowedAudioExt = ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'webm'];
                    $allowedExt = $isImage ? $allowedImageExt : $allowedAudioExt;

                    if (!in_array($ext, $allowedExt, true)) {
                        $this->json([
                            'success' => false,
                            'error' => 'Estensione non consentita per questo tipo file'
                        ]);
                        return;
                    }

                    $subDir = $isImage ? 'image' : 'audio';
                    $uploadDir = BASE_PATH . '/public/upload/domanda/' . $subDir;
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                        $this->json([
                            'success' => false,
                            'error' => 'Impossibile creare cartella upload'
                        ]);
                        return;
                    }

                    $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', pathinfo($originalName, PATHINFO_FILENAME));
                    $safeBase = trim((string) $safeBase, '-');
                    if ($safeBase === '') {
                        $safeBase = 'media-domanda';
                    }

                    $fileName = $safeBase . '-' . time() . '-' . random_int(1000, 9999) . '.' . $ext;
                    $destPath = $uploadDir . '/' . $fileName;

                    if (!move_uploaded_file($tmpName, $destPath)) {
                        $this->json([
                            'success' => false,
                            'error' => 'Errore salvataggio file'
                        ]);
                        return;
                    }

                    $titolo = trim((string) ($_POST['titolo'] ?? ''));
                    if ($titolo === '') {
                        $titolo = pathinfo($originalName, PATHINFO_FILENAME) ?: ($isImage ? 'Immagine domanda' : 'Audio domanda');
                    }

                    $filePath = '/upload/domanda/' . $subDir . '/' . $fileName;
                    $tipoFile = $isImage ? 'image' : 'audio';
                    $id = (new ScreenMedia())->crea($titolo, $filePath, 'domanda', $tipoFile);

                    $this->json([
                        'success' => true,
                        'media_id' => $id,
                        'contesto' => 'domanda',
                        'tipo_file' => $tipoFile,
                        'file_path' => $filePath
                    ]);
                    return;

                case 'domanda-media-elimina':
                    $mediaId = (int) ($_POST['media_id'] ?? 0);
                    if ($mediaId <= 0) {
                        $this->json([
                            'success' => false,
                            'error' => 'Media non valido'
                        ]);
                        return;
                    }

                    $mediaModel = new ScreenMedia();
                    $media = $mediaModel->trova($mediaId, 'domanda');

                    if (!$media) {
                        $this->json([
                            'success' => false,
                            'error' => 'Media non trovato'
                        ]);
                        return;
                    }

                    $ok = $mediaModel->elimina($mediaId, 'domanda');

                    if ($ok) {
                        $file = BASE_PATH . '/public' . ($media['file_path'] ?? '');
                        if (is_file($file)) {
                            @unlink($file);
                        }
                    }

                    $this->json([
                        'success' => $ok
                    ]);
                    return;

                case 'approva-join':
                case 'rifiuta-join':
                    $richiestaId = (int) ($_POST['request_id'] ?? 0);
                    if ($richiestaId <= 0) {
                        $this->json([
                            'success' => false,
                            'error' => 'Richiesta non valida'
                        ]);
                        return;
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
                    return;

                default:
                    $this->json([
                        'success' => false,
                        'error' => 'Azione non valida'
                    ]);
                    return;
            }

            $this->json([
                'success' => true,
                'action' => $action
            ]);

        } catch (\Throwable $e) {

            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}

