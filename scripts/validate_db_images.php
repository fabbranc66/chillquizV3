<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/Database.php';

use App\Core\Database;

$pdo = Database::getInstance();
$rows = $pdo->query(
    "SELECT id, codice_domanda, media_image_path
     FROM domande
     WHERE media_image_path IS NOT NULL
       AND TRIM(media_image_path) <> ''
     ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$stats = [
    'total' => 0,
    'ok' => 0,
    'missing' => 0,
    'html_saved' => 0,
    'bad_mime' => 0,
];
$bad = [];

foreach ($rows as $row) {
    $stats['total']++;
    $fullPath = BASE_PATH . '/public' . (string) $row['media_image_path'];

    if (!is_file($fullPath)) {
        $stats['missing']++;
        $bad[] = [
            'id' => (int) $row['id'],
            'codice' => (string) $row['codice_domanda'],
            'issue' => 'missing_file',
            'path' => (string) $row['media_image_path'],
        ];
        continue;
    }

    $head = file_get_contents($fullPath, false, null, 0, 512);
    $trim = ltrim((string) $head);
    if (stripos($trim, '<!DOCTYPE html') === 0 || stripos($trim, '<html') === 0) {
        $stats['html_saved']++;
        $bad[] = [
            'id' => (int) $row['id'],
            'codice' => (string) $row['codice_domanda'],
            'issue' => 'html_saved',
            'path' => (string) $row['media_image_path'],
        ];
        continue;
    }

    $mime = finfo_file($finfo, $fullPath) ?: 'unknown';
    $allowed = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'image/vnd.microsoft.icon',
    ];

    if (!in_array($mime, $allowed, true)) {
        $stats['bad_mime']++;
        $bad[] = [
            'id' => (int) $row['id'],
            'codice' => (string) $row['codice_domanda'],
            'issue' => 'mime:' . $mime,
            'path' => (string) $row['media_image_path'],
        ];
        continue;
    }

    $stats['ok']++;
}

finfo_close($finfo);

echo json_encode([
    'stats' => $stats,
    'sample_bad' => array_slice($bad, 0, 100),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
