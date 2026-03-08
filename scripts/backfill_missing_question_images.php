<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/Database.php';

use App\Core\Database;

function slugify(string $value): string
{
    $value = trim($value);
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;
    return trim($value, '_');
}

function fetchJson(string $url): ?array
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 20,
            'ignore_errors' => true,
            'header' => "User-Agent: ChillQuizBackfill/1.0\r\nAccept: application/json\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function fetchBinary(string $url): ?string
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'ignore_errors' => true,
            'header' => "User-Agent: ChillQuizBackfill/1.0\r\nAccept: image/*,*/*;q=0.8\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    return is_string($raw) && $raw !== '' ? $raw : null;
}

function imageExtensionFromUrl(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH);
    $ext = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return $ext === 'jpeg' ? 'jpg' : $ext;
    }

    return 'jpg';
}

function isValidImageBinary(string $binary): bool
{
    if ($binary === '') {
        return false;
    }

    $trimmed = ltrim($binary);
    if (str_starts_with($trimmed, '<!DOCTYPE html') || str_starts_with($trimmed, '<html')) {
        return false;
    }

    if (str_starts_with($binary, "\xFF\xD8\xFF")) {
        return true;
    }

    if (str_starts_with($binary, "\x89PNG\r\n\x1A\n")) {
        return true;
    }

    if (substr($binary, 0, 4) === 'RIFF' && substr($binary, 8, 4) === 'WEBP') {
        return true;
    }

    return false;
}

$imageMap = [
    37 => [
        'page' => 'Charlemagne',
        'prefix' => 'hst',
    ],
    46 => [
        'page' => 'Normandy_landings',
        'prefix' => 'hst',
    ],
];

$pdo = Database::getInstance();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$uploadDir = BASE_PATH . '/public/upload/domanda/image';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
    throw new RuntimeException('Impossibile creare la cartella upload immagini.');
}

$selectStmt = $pdo->prepare(
    "SELECT id, codice_domanda, testo, media_image_path
     FROM domande
     WHERE id = :id"
);

$updateStmt = $pdo->prepare(
    "UPDATE domande
     SET media_image_path = :media_image_path
     WHERE id = :id"
);

$report = [];

foreach ($imageMap as $questionId => $config) {
    $selectStmt->execute(['id' => $questionId]);
    $row = $selectStmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $report[] = [
            'id' => $questionId,
            'status' => 'missing-question',
        ];
        continue;
    }

    $currentPath = trim((string) ($row['media_image_path'] ?? ''));
    if ($currentPath !== '') {
        $report[] = [
            'id' => $questionId,
            'status' => 'already-set',
            'path' => $currentPath,
        ];
        continue;
    }

    $page = (string) $config['page'];
    $summaryUrl = 'https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode($page);
    $summary = fetchJson($summaryUrl);

    $imageSource = '';
    if (is_array($summary)) {
        $imageSource = (string) ($summary['originalimage']['source'] ?? $summary['thumbnail']['source'] ?? '');
    }

    if ($imageSource === '') {
        $report[] = [
            'id' => $questionId,
            'status' => 'missing-image-source',
            'page' => $page,
        ];
        continue;
    }

    $binary = fetchBinary($imageSource);
    if (!is_string($binary) || !isValidImageBinary($binary)) {
        $report[] = [
            'id' => $questionId,
            'status' => 'invalid-download',
            'page' => $page,
            'image_source' => $imageSource,
        ];
        continue;
    }

    $ext = imageExtensionFromUrl($imageSource);
    $filename = sprintf(
        '%s_%d_%s.%s',
        (string) $config['prefix'],
        $questionId,
        slugify($page),
        $ext
    );

    $fullPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    $publicPath = '/upload/domanda/image/' . $filename;

    try {
        file_put_contents($fullPath, $binary, LOCK_EX);
        $updateStmt->execute([
            'id' => $questionId,
            'media_image_path' => $publicPath,
        ]);

        $report[] = [
            'id' => $questionId,
            'status' => 'updated',
            'path' => $publicPath,
            'page' => $page,
        ];
    } catch (Throwable $e) {
        $report[] = [
            'id' => $questionId,
            'status' => 'write-failed',
            'error' => $e->getMessage(),
        ];
    }
}

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
