<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('IMAGE_DIR', BASE_PATH . '/public/upload/domanda/image');
define('FFMPEG_BIN', 'C:\\Users\\fabbr\\AppData\\Local\\Microsoft\\WinGet\\Packages\\Gyan.FFmpeg_Microsoft.Winget.Source_8wekyb3d8bbwe\\ffmpeg-8.0.1-full_build\\bin\\ffmpeg.exe');

require BASE_PATH . '/app/Core/Database.php';

use App\Core\Database;

/**
 * @return array{mime: string, width: int, height: int}|null
 */
function imageInfo(string $path): ?array
{
    $size = @getimagesize($path);
    if ($size === false) {
        return null;
    }

    return [
        'mime' => (string) ($size['mime'] ?? ''),
        'width' => (int) ($size[0] ?? 0),
        'height' => (int) ($size[1] ?? 0),
    ];
}

function downloadFile(string $url, string $destination): void
{
    $fp = fopen($destination, 'wb');
    if ($fp === false) {
        throw new RuntimeException('Impossibile aprire file di destinazione: ' . $destination);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_FAILONERROR => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Referer: https://www.pexels.com/',
        ],
    ]);

    $result = curl_exec($ch);
    $error = $result === false ? curl_error($ch) : null;
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    fclose($fp);

    if ($result === false || $status < 200 || $status >= 300) {
        @unlink($destination);
        throw new RuntimeException(sprintf('Download fallito (%s, HTTP %d) per %s', (string) $error, $status, $url));
    }
}

function resizeTo300(string $source, string $destination): void
{
    $command = sprintf(
        '"%s" -y -i "%s" -vf "scale=\'if(gt(iw,ih),300,-2)\':\'if(gt(ih,iw),300,-2)\'" -q:v 4 "%s" 2>&1',
        FFMPEG_BIN,
        $source,
        $destination
    );

    exec($command, $output, $exitCode);
    if ($exitCode !== 0 || !is_file($destination)) {
        throw new RuntimeException("ffmpeg ha fallito: \n" . implode("\n", $output));
    }
}

$items = [
    132 => [
        'path' => '/upload/domanda/image/sci_132_dna_lab.jpg',
        'url' => 'https://images.pexels.com/photos/7722538/pexels-photo-7722538.jpeg?cs=srgb&dl=pexels-tara-winstead-7722538.jpg&fm=jpg',
        'source_page' => 'https://www.pexels.com/photo/close-up-shot-of-test-tubes-on-a-blue-surface-7723191/',
    ],
    1649 => [
        'path' => '/upload/domanda/image/mat_1649_coin_toss.jpg',
        'url' => 'https://images.pexels.com/photos/4029695/pexels-photo-4029695.jpeg?cs=srgb&dl=pexels-iisaacpollock-4029695.jpg&fm=jpg',
        'source_page' => 'https://www.pexels.com/photo/a-person-tossing-a-coin-4029695/',
    ],
    950 => [
        'path' => '/upload/domanda/image/lit_950_writing_desk.jpg',
        'url' => 'https://images.pexels.com/photos/30082138/pexels-photo-30082138.jpeg?cs=srgb&dl=pexels-seymasungr-1499342462-30082138.jpg&fm=jpg',
        'source_page' => 'https://www.pexels.com/photo/quill-pen-in-an-inkwell-on-a-wooden-desk-30082138/',
    ],
    877 => [
        'path' => '/upload/domanda/image/art_877_paint_palette.jpg',
        'url' => 'https://images.pexels.com/photos/6925031/pexels-photo-6925031.jpeg?cs=srgb&dl=pexels-pavel-danilyuk-6925031.jpg&fm=jpg',
        'source_page' => 'https://www.pexels.com/photo/selective-focus-photography-of-paint-brush-on-paint-palette-1213431/',
    ],
    543 => [
        'path' => '/upload/domanda/image/tec_543_server_room.jpg',
        'url' => 'https://images.pexels.com/photos/5480781/pexels-photo-5480781.jpeg?cs=srgb&dl=pexels-brett-sayles-5480781.jpg&fm=jpg',
        'source_page' => 'https://www.pexels.com/photo/server-room-5480781/',
    ],
    385 => [
        'path' => '/upload/domanda/image/hst_385_castle_wall.jpg',
        'url' => 'https://images.pexels.com/photos/25388607/pexels-photo-25388607.jpeg?cs=srgb&dl=pexels-elreydidi-25388607.jpg&fm=jpg',
        'source_page' => 'https://www.pexels.com/photo/stone-wall-along-a-walkway-25388607/',
    ],
    502 => [
        'path' => '/upload/domanda/image/tec_502_motherboard.jpg',
        'url' => 'https://images.pexels.com/photos/14887608/pexels-photo-14887608.jpeg?cs=srgb&dl=pexels-nicolas-foster-65973708-14887608.jpg&fm=jpg',
        'source_page' => 'https://www.pexels.com/photo/motherboard-in-close-up-shot-14887608/',
    ],
    1415 => [
        'path' => '/upload/domanda/image/myt_1415_moon_trees.jpg',
        'url' => 'https://images.pexels.com/photos/12856241/pexels-photo-12856241.jpeg?cs=srgb&dl=pexels-bybushra-149061976-12856241.jpg&fm=jpg',
        'source_page' => 'https://www.pexels.com/photo/serene-night-sky-with-moon-and-pine-trees-33247310/',
    ],
];

$pdo = Database::getInstance();
$updateStmt = $pdo->prepare(
    'UPDATE domande d
     INNER JOIN sessione_domande sd ON sd.domanda_id = d.id
     SET d.media_image_path = :path
     WHERE d.id = :id
       AND sd.sessione_id = 2'
);

$report = [];

foreach ($items as $questionId => $config) {
    $tmpSource = tempnam(sys_get_temp_dir(), 'pexels_src_');
    $tmpScaledBase = tempnam(sys_get_temp_dir(), 'pexels_scaled_');
    if ($tmpSource === false || $tmpScaledBase === false) {
        throw new RuntimeException('Impossibile creare file temporanei.');
    }
    $tmpScaled = $tmpScaledBase . '.jpg';
    @unlink($tmpScaledBase);

    try {
        downloadFile($config['url'], $tmpSource);
        resizeTo300($tmpSource, $tmpScaled);

        $info = imageInfo($tmpScaled);
        if ($info === null || $info['mime'] !== 'image/jpeg') {
            throw new RuntimeException('Il file generato non è un JPEG valido.');
        }
        if ($info['width'] > 300 || $info['height'] > 300) {
            throw new RuntimeException('Il resize non ha rispettato il limite di 300px.');
        }

        $targetPath = BASE_PATH . '/public' . $config['path'];
        if (!rename($tmpScaled, $targetPath)) {
            throw new RuntimeException('Impossibile spostare il file finale in ' . $targetPath);
        }

        $updateStmt->execute([
            'id' => $questionId,
            'path' => $config['path'],
        ]);

        $report[] = [
            'id' => $questionId,
            'path' => $config['path'],
            'width' => $info['width'],
            'height' => $info['height'],
            'source_page' => $config['source_page'],
        ];
    } finally {
        @unlink($tmpSource);
        @unlink($tmpScaled);
    }
}

echo json_encode([
    'session_id' => 2,
    'updated' => count($report),
    'items' => $report,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
