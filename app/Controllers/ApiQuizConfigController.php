<?php

namespace App\Controllers;

use App\Modules\QuizConfigV2\QuizConfigService;
use RuntimeException;
use Throwable;

class ApiQuizConfigController
{
    private QuizConfigService $service;

    public function __construct()
    {
        $this->ensureServiceClassLoaded();
        $this->service = new QuizConfigService();
    }

    private function ensureServiceClassLoaded(): void
    {
        if (class_exists(QuizConfigService::class)) {
            return;
        }

        $paths = [
            BASE_PATH . '/app/Modules/QuizConfigV2/QuizConfigService.php',
            BASE_PATH . '/app/Views/modules/QuizConfigV2/QuizConfigService.php',
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                require_once $path;
            }

            if (class_exists(QuizConfigService::class)) {
                return;
            }
        }

        throw new RuntimeException('Class "' . QuizConfigService::class . '" non trovata');
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


    private function getIncomingAdminToken(): ?string
    {
        $headerToken = $this->getRequestHeader('X-ADMIN-TOKEN');
        if (is_string($headerToken) && trim($headerToken) !== '') {
            return trim($headerToken);
        }

        $queryToken = isset($_GET['admin_token']) ? trim((string) $_GET['admin_token']) : '';
        if ($queryToken !== '') {
            return $queryToken;
        }

        $postToken = isset($_POST['admin_token']) ? trim((string) $_POST['admin_token']) : '';
        if ($postToken !== '') {
            return $postToken;
        }

        return null;
    }

    private function isAdminAuthorized(): bool
    {
        $incoming = $this->getIncomingAdminToken();

        if ($incoming === null || $incoming === '') {
            return false;
        }

        return hash_equals($this->getAdminToken(), $incoming);
    }

    private function getInput(): array
    {
        $input = $_POST;

        if (!empty($input)) {
            return $input;
        }

        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        if (strpos($contentType, 'application/json') === false) {
            return [];
        }

        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function parseManualQuestions($source): array
    {
        if (is_array($source)) {
            $values = array_map('intval', $source);
            return array_values(array_unique(array_filter($values, static fn ($v) => $v > 0)));
        }

        $raw = trim((string) $source);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/', $raw) ?: [];
        $values = array_map('intval', $parts);

        return array_values(array_unique(array_filter($values, static fn ($v) => $v > 0)));
    }

    public function handle(string $action = 'index'): void
    {
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
                case 'schema-init':
                    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                        $this->json(['success' => false, 'error' => 'Metodo non consentito']);
                        return;
                    }

                    $this->service->initializeSchema();
                    $this->json(['success' => true]);
                    return;

                case 'list':
                    $this->service->initializeSchema();

                    $this->json([
                        'success' => true,
                        'configurazioni' => $this->service->listConfigurations()
                    ]);
                    return;

                case 'get':
                    $this->service->initializeSchema();
                    $id = (int) ($_GET['id'] ?? 0);

                    if ($id <= 0) {
                        $this->json(['success' => false, 'error' => 'id non valido']);
                        return;
                    }

                    $config = $this->service->getConfiguration($id);
                    if ($config === null) {
                        $this->json(['success' => false, 'error' => 'configurazione non trovata']);
                        return;
                    }

                    $this->json(['success' => true, 'configurazione' => $config]);
                    return;

                case 'argomenti':
                    $this->service->initializeSchema();
                    $this->json([
                        'success' => true,
                        'argomenti' => $this->service->listArgomenti()
                    ]);
                    return;

                case 'domande':
                    $this->service->initializeSchema();

                    $argomentoId = isset($_GET['argomento_id']) && (int) $_GET['argomento_id'] > 0
                        ? (int) $_GET['argomento_id']
                        : null;

                    $query = trim((string) ($_GET['q'] ?? ''));

                    $this->json([
                        'success' => true,
                        'domande' => $this->service->listDomandeDisponibili($argomentoId, $query)
                    ]);
                    return;

                case 'save':
                    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                        $this->json(['success' => false, 'error' => 'Metodo non consentito']);
                        return;
                    }

                    $this->service->initializeSchema();
                    $input = $this->getInput();

                    $payload = [
                        'id' => isset($input['id']) ? (int) $input['id'] : null,
                        'nome_quiz' => trim((string) ($input['nome_quiz'] ?? '')),
                        'titolo' => trim((string) ($input['titolo'] ?? '')),
                        'modalita' => trim((string) ($input['modalita'] ?? 'mista')),
                        'numero_domande' => (int) ($input['numero_domande'] ?? 10),
                        'argomento_id' => ($input['argomento_id'] ?? '') === '' ? 0 : (int) $input['argomento_id'],
                        'selezione_tipo' => trim((string) ($input['selezione_tipo'] ?? 'auto')),
                        'attiva' => (int) ($input['attiva'] ?? 1) === 1,
                    ];

                    $domandeManuali = $this->parseManualQuestions($input['domande_manuali'] ?? '');
                    $savedId = $this->service->saveConfiguration($payload, $domandeManuali);

                    $this->json([
                        'success' => true,
                        'id' => $savedId
                    ]);
                    return;

                case 'generate-domande':
                    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                        $this->json(['success' => false, 'error' => 'Metodo non consentito']);
                        return;
                    }

                    $this->service->initializeSchema();
                    $input = $this->getInput();

                    $payload = [
                        'id' => isset($input['id']) ? (int) $input['id'] : null,
                        'nome_quiz' => trim((string) ($input['nome_quiz'] ?? 'preview')),
                        'titolo' => trim((string) ($input['titolo'] ?? 'Preview')),
                        'modalita' => trim((string) ($input['modalita'] ?? 'mista')),
                        'numero_domande' => (int) ($input['numero_domande'] ?? 10),
                        'argomento_id' => ($input['argomento_id'] ?? '') === '' ? 0 : (int) $input['argomento_id'],
                        'selezione_tipo' => trim((string) ($input['selezione_tipo'] ?? 'auto')),
                        'attiva' => (int) ($input['attiva'] ?? 1) === 1,
                    ];

                    $domandeManuali = $this->parseManualQuestions($input['domande_manuali'] ?? '');
                    $domande = $this->service->generateQuestions($payload, $domandeManuali);

                    $this->json([
                        'success' => true,
                        'domande' => $domande,
                    ]);
                    return;

                case 'save-domande':
                    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                        $this->json(['success' => false, 'error' => 'Metodo non consentito']);
                        return;
                    }

                    $this->service->initializeSchema();
                    $input = $this->getInput();
                    $id = (int) ($input['id'] ?? 0);
                    if ($id <= 0) {
                        $this->json(['success' => false, 'error' => 'id configurazione non valido']);
                        return;
                    }

                    $ids = $this->parseManualQuestions($input['domande'] ?? ($input['domande_manuali'] ?? ''));
                    $this->service->saveGeneratedQuestions($id, $ids);

                    $this->json([
                        'success' => true,
                        'id' => $id,
                        'salvate' => count($ids),
                    ]);
                    return;

                default:
                    $this->json(['success' => false, 'error' => 'Azione non valida']);
                    return;
            }
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
