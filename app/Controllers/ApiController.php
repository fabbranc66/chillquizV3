<?php

namespace App\Controllers;

use App\Models\Partecipazione;
use App\Models\Risposta;
use App\Models\Sessione;
use App\Models\JoinRichiesta;
use App\Models\Utente;
use App\Models\ScreenMedia;
use App\Models\AppSettings;
use App\Services\SessioneService;
use Throwable;

class ApiController
{
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


    public function stato($sessioneId): void
    {
        $sessioneId = (int) $sessioneId;

        try {
            $service = new SessioneService($sessioneId);
            $service->verificaTimer();

            $pdo = \App\Core\Database::getInstance();
            $stmt = $pdo->prepare("SELECT * FROM sessioni WHERE id = ?");
            $stmt->execute([$sessioneId]);

            $this->json([
                'success' => true,
                'sessione' => $stmt->fetch()
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

        // 🔒 BLOCCO: nome già usato nella stessa sessione (richiede approvazione admin)
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
                'error' => 'Nome già utilizzato: richiesta inviata alla regia'
            ]);
            return;
        }

        // ✅ Se non esiste, crea utente temporaneo e partecipa
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
                    'error' => 'Non è il momento di puntare'
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
                    'error' => 'Non è il momento di rispondere'
                ]);
                return;
            }

            $partecipazione = new Partecipazione();

            $risultato = $partecipazione->registraRisposta(
                $partecipazioneId,
                $domandaId,
                $opzioneId
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
            $media = (new ScreenMedia())->mediaAttivaRandom();

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
                    $nuovaId = (new Sessione())->crea(1);
                    $this->json([
                        'success' => true,
                        'action' => $action,
                        'sessione_id' => $nuovaId
                    ]);
                    return;

                case 'avvia-puntata':
                    (new SessioneService($sessioneId))->avviaPuntata();
                    break;

                case 'avvia-domanda':
                    (new SessioneService($sessioneId))->avviaDomanda();
                    break;

                case 'risultati':
                    (new SessioneService($sessioneId))->chiudiDomanda();
                    break;

                case 'prossima':
                    (new SessioneService($sessioneId))->prossimaFase();
                    break;

                case 'riavvia':
                    (new SessioneService($sessioneId))->resetTotale();
                    break;

                case 'join-richieste':
                    $joinRichiesta = new JoinRichiesta();
                    $this->json([
                        'success' => true,
                        'action' => $action,
                        'richieste' => $joinRichiesta->listaPending($sessioneId)
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
                        'media' => (new ScreenMedia())->lista()
                    ]);
                    return;

                case 'media-upload':
                    if (!isset($_FILES['immagine']) || !is_array($_FILES['immagine'])) {
                        $this->json([
                            'success' => false,
                            'error' => 'File immagine mancante'
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

                    $maxSizeBytes = 8 * 1024 * 1024;
                    $size = (int) ($file['size'] ?? 0);
                    if ($size <= 0 || $size > $maxSizeBytes) {
                        $this->json([
                            'success' => false,
                            'error' => 'Dimensione file non valida (max 8MB)'
                        ]);
                        return;
                    }

                    $tmpName = $file['tmp_name'] ?? '';
                    $detectedMime = @mime_content_type($tmpName) ?: '';
                    if (strpos($detectedMime, 'image/') !== 0) {
                        $this->json([
                            'success' => false,
                            'error' => "Formato non supportato: carica un'immagine"
                        ]);
                        return;
                    }

                    $originalName = (string) ($file['name'] ?? '');
                    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if (!in_array($ext, $allowedExt, true)) {
                        $this->json([
                            'success' => false,
                            'error' => 'Estensione non consentita'
                        ]);
                        return;
                    }

                    $uploadDir = BASE_PATH . '/public/upload/image';
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
                        $titolo = pathinfo($originalName, PATHINFO_FILENAME) ?: 'Immagine';
                    }

                    $filePath = '/upload/image/' . $fileName;
                    $id = (new ScreenMedia())->crea($titolo, $filePath);

                    $this->json([
                        'success' => true,
                        'media_id' => $id,
                        'file_path' => $filePath
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

                    $ok = (new ScreenMedia())->impostaAttiva($mediaId, $attiva);
                    $this->json([
                        'success' => $ok,
                        'media_id' => $mediaId,
                        'attiva' => $attiva,
                        'error' => $ok ? null : 'Media non trovato'
                    ]);
                    return;

                case 'media-disattiva':
                    $ok = (new ScreenMedia())->disattivaTutte();
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
                    $media = $mediaModel->trova($mediaId);

                    if (!$media) {
                        $this->json([
                            'success' => false,
                            'error' => 'Media non trovato'
                        ]);
                        return;
                    }

                    $ok = $mediaModel->elimina($mediaId);

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
