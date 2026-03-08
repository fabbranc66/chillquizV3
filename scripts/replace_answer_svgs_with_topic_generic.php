<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/Database.php';

use App\Core\Database;

/**
 * @return array<int, string>
 */
function renderLines(array $lines): array
{
    $rendered = [];
    $startY = 74;
    $lineHeight = count($lines) > 2 ? 28 : 34;
    $offset = ((count($lines) - 1) * $lineHeight) / 2;

    foreach ($lines as $index => $line) {
        $y = $startY + ($index * $lineHeight) - $offset;
        $rendered[] = sprintf(
            '<text x="150" y="%s" text-anchor="middle" fill="#ffffff" font-family="Segoe UI, Arial, sans-serif" font-size="24" font-weight="700" letter-spacing="1">%s</text>',
            number_format((float) $y, 1, '.', ''),
            htmlspecialchars($line, ENT_QUOTES | ENT_XML1, 'UTF-8')
        );
    }

    return $rendered;
}

function buildSvg(string $label, array $lines, string $bgA, string $bgB, string $accent): string
{
    $text = implode('', renderLines($lines));
    $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_XML1, 'UTF-8');

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="300" height="169" viewBox="0 0 300 169" role="img" aria-label="{$safeLabel}">
<defs>
<linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
<stop offset="0%" stop-color="{$bgA}"/>
<stop offset="100%" stop-color="{$bgB}"/>
</linearGradient>
</defs>
<rect width="300" height="169" rx="22" fill="url(#bg)"/>
<circle cx="38" cy="34" r="44" fill="#ffffff10"/>
<circle cx="264" cy="139" r="58" fill="#ffffff10"/>
<rect x="26" y="28" width="248" height="113" rx="18" fill="#ffffff12" stroke="#ffffff20"/>
<rect x="26" y="28" width="8" height="113" rx="4" fill="{$accent}"/>
{$text}
</svg>
SVG;
}

$topics = [
    'Arte' => [
        'path' => '/upload/domanda/image/art_generic.svg',
        'label' => 'Arte',
        'lines' => ['ARTE'],
        'bgA' => '#5f0f40',
        'bgB' => '#9a031e',
        'accent' => '#fb8b24',
    ],
    'Attualità' => [
        'path' => '/upload/domanda/image/att_generic.svg',
        'label' => 'Attualita',
        'lines' => ['ATTUALITA'],
        'bgA' => '#0b132b',
        'bgB' => '#1c2541',
        'accent' => '#5bc0be',
    ],
    'Cucina' => [
        'path' => '/upload/domanda/image/cui_generic.svg',
        'label' => 'Cucina',
        'lines' => ['CUCINA'],
        'bgA' => '#5f3726',
        'bgB' => '#bc6c25',
        'accent' => '#ffd166',
    ],
    'Cultura Generale' => [
        'path' => '/upload/domanda/image/gnk_generic.svg',
        'label' => 'Cultura Generale',
        'lines' => ['CULTURA', 'GENERALE'],
        'bgA' => '#1d3557',
        'bgB' => '#457b9d',
        'accent' => '#f1faee',
    ],
    'Geografia' => [
        'path' => '/upload/domanda/image/geo_generic.svg',
        'label' => 'Geografia',
        'lines' => ['GEOGRAFIA'],
        'bgA' => '#0b6e4f',
        'bgB' => '#1f8a70',
        'accent' => '#ffd166',
    ],
    'Letteratura' => [
        'path' => '/upload/domanda/image/lit_generic.svg',
        'label' => 'Letteratura',
        'lines' => ['LETTERATURA'],
        'bgA' => '#3d405b',
        'bgB' => '#6d597a',
        'accent' => '#f2cc8f',
    ],
    'Lingua Italiana' => [
        'path' => '/upload/domanda/image/litg_generic.svg',
        'label' => 'Lingua Italiana',
        'lines' => ['LINGUA', 'ITALIANA'],
        'bgA' => '#264653',
        'bgB' => '#2a9d8f',
        'accent' => '#e9c46a',
    ],
    'Matematica' => [
        'path' => '/upload/domanda/image/mat_generic.svg',
        'label' => 'Matematica',
        'lines' => ['MATEMATICA'],
        'bgA' => '#1f2f5a',
        'bgB' => '#0b1220',
        'accent' => '#8ecae6',
    ],
    'Mitologia' => [
        'path' => '/upload/domanda/image/myt_generic.svg',
        'label' => 'Mitologia',
        'lines' => ['MITOLOGIA'],
        'bgA' => '#3a0f4b',
        'bgB' => '#6a1b9a',
        'accent' => '#ffd166',
    ],
    'Musica' => [
        'path' => '/upload/domanda/image/mus_generic.svg',
        'label' => 'Musica',
        'lines' => ['MUSICA'],
        'bgA' => '#14213d',
        'bgB' => '#3a86ff',
        'accent' => '#ffbe0b',
    ],
    'Musica Rap Trap Italiana' => [
        'path' => '/upload/domanda/image/rapit_generic.svg',
        'label' => 'Musica Rap Trap Italiana',
        'lines' => ['RAP TRAP', 'ITALIANA'],
        'bgA' => '#111111',
        'bgB' => '#3d0c11',
        'accent' => '#ef476f',
    ],
    'Natura' => [
        'path' => '/upload/domanda/image/nat_generic.svg',
        'label' => 'Natura',
        'lines' => ['NATURA'],
        'bgA' => '#1b4332',
        'bgB' => '#2d6a4f',
        'accent' => '#95d5b2',
    ],
    'Sport' => [
        'path' => '/upload/domanda/image/spo_generic.svg',
        'label' => 'Sport',
        'lines' => ['SPORT'],
        'bgA' => '#003049',
        'bgB' => '#1d3557',
        'accent' => '#f77f00',
    ],
    'Storia' => [
        'path' => '/upload/domanda/image/hst_generic.svg',
        'label' => 'Storia',
        'lines' => ['STORIA'],
        'bgA' => '#4a2c2a',
        'bgB' => '#7f5539',
        'accent' => '#ddb892',
    ],
    'Tecnologia' => [
        'path' => '/upload/domanda/image/tec_generic.svg',
        'label' => 'Tecnologia',
        'lines' => ['TECNOLOGIA'],
        'bgA' => '#0b1f33',
        'bgB' => '#144552',
        'accent' => '#00b4d8',
    ],
    'TV e Serie' => [
        'path' => '/upload/domanda/image/tvs_generic.svg',
        'label' => 'TV e Serie',
        'lines' => ['TV E SERIE'],
        'bgA' => '#2b2d42',
        'bgB' => '#4a4e69',
        'accent' => '#ef233c',
    ],
    'Videogiochi' => [
        'path' => '/upload/domanda/image/vgm_generic.svg',
        'label' => 'Videogiochi',
        'lines' => ['VIDEOGIOCHI'],
        'bgA' => '#240046',
        'bgB' => '#3c096c',
        'accent' => '#7b2cbf',
    ],
];

$imageDir = BASE_PATH . '/public/upload/domanda/image';
foreach ($topics as $topic => $config) {
    $fullPath = BASE_PATH . '/public' . $config['path'];
    file_put_contents(
        $fullPath,
        buildSvg(
            $config['label'],
            $config['lines'],
            $config['bgA'],
            $config['bgB'],
            $config['accent']
        ),
        LOCK_EX
    );
}

$pdo = Database::getInstance();
$updateStmt = $pdo->prepare(
    'UPDATE domande d
     LEFT JOIN argomenti a ON a.id = d.argomento_id
     SET d.media_image_path = :path
     WHERE d.media_image_path LIKE \'%.svg\'
       AND a.nome = :topic'
);

$report = [];
$updatedTotal = 0;

foreach ($topics as $topic => $config) {
    $updateStmt->execute([
        'path' => $config['path'],
        'topic' => $topic,
    ]);
    $count = $updateStmt->rowCount();
    if ($count > 0) {
        $report[] = [
            'topic' => $topic,
            'path' => $config['path'],
            'updated' => $count,
        ];
        $updatedTotal += $count;
    }
}

echo json_encode([
    'generated' => count($topics),
    'updated_total' => $updatedTotal,
    'items' => $report,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
