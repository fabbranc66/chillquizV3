<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/Database.php';

use App\Core\Database;

$pdo = Database::getInstance();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$missingCls = (int) $pdo->query(
    "SELECT COUNT(*) FROM domande WHERE (media_image_path IS NULL OR TRIM(media_image_path) = '') AND codice_domanda LIKE 'CLS-%'"
)->fetchColumn();

$missingAll = (int) $pdo->query(
    "SELECT COUNT(*) FROM domande WHERE media_image_path IS NULL OR TRIM(media_image_path) = ''"
)->fetchColumn();

$wrongOlympics = (int) $pdo->query(
    "SELECT COUNT(*) FROM domande WHERE codice_domanda LIKE 'CLS-%' AND media_image_path LIKE '%xxiv_giochi_olimpici_invernali%'"
)->fetchColumn();

echo 'MISSING_CLS=' . $missingCls . PHP_EOL;
echo 'MISSING_ALL=' . $missingAll . PHP_EOL;
echo 'WRONG_OLYMPICS=' . $wrongOlympics . PHP_EOL;

if ($wrongOlympics > 0) {
    $rows = $pdo->query(
        "SELECT id, codice_domanda, media_image_path
         FROM domande
         WHERE codice_domanda LIKE 'CLS-%'
           AND media_image_path LIKE '%xxiv_giochi_olimpici_invernali%'
         ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
