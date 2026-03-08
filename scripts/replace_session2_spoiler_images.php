<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/Database.php';

use App\Core\Database;

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * @param array<int, string> $nodes
 */
function svgDocument(string $label, string $bgA, string $bgB, array $nodes): string
{
    return sprintf(
        <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="300" height="169" viewBox="0 0 300 169" role="img" aria-label="%s">
<defs>
<linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
<stop offset="0%%" stop-color="%s"/>
<stop offset="100%%" stop-color="%s"/>
</linearGradient>
</defs>
<rect width="300" height="169" rx="22" fill="url(#bg)"/>
<circle cx="34" cy="30" r="42" fill="#ffffff10"/>
<circle cx="272" cy="144" r="54" fill="#ffffff10"/>
<rect x="20" y="20" width="260" height="129" rx="18" fill="#ffffff10" stroke="#ffffff22"/>
%s
</svg>
SVG,
        esc($label),
        $bgA,
        $bgB,
        implode("\n", $nodes)
    );
}

function dnaSvg(): string
{
    return svgDocument('DNA pattern', '#0f3d3e', '#1b6b73', [
        '<path d="M92 42 C132 60, 168 108, 208 126" fill="none" stroke="#d8f3dc" stroke-width="7" stroke-linecap="round"/>',
        '<path d="M208 42 C168 60, 132 108, 92 126" fill="none" stroke="#9bf6ff" stroke-width="7" stroke-linecap="round"/>',
        '<path d="M115 53 L185 53" stroke="#ffffffbb" stroke-width="4" stroke-linecap="round"/>',
        '<path d="M105 72 L195 72" stroke="#ffffff99" stroke-width="4" stroke-linecap="round"/>',
        '<path d="M98 92 L202 92" stroke="#ffffffbb" stroke-width="4" stroke-linecap="round"/>',
        '<path d="M104 112 L196 112" stroke="#ffffff99" stroke-width="4" stroke-linecap="round"/>',
        '<circle cx="86" cy="84" r="16" fill="#ffffff14"/>',
        '<circle cx="214" cy="84" r="16" fill="#ffffff14"/>',
    ]);
}

function probabilitySvg(): string
{
    return svgDocument('Coin toss odds', '#102542', '#1f487e', [
        '<circle cx="106" cy="84" r="38" fill="#ffd166" stroke="#fffb" stroke-width="4"/>',
        '<circle cx="106" cy="84" r="22" fill="none" stroke="#f4a261" stroke-width="4"/>',
        '<path d="M170 54 h44 a12 12 0 0 1 12 12 v0 a12 12 0 0 1 -12 12 h-44 z" fill="#ffffff18" stroke="#ffffff44"/>',
        '<path d="M170 90 h60 a12 12 0 0 1 12 12 v0 a12 12 0 0 1 -12 12 h-60 z" fill="#ffffff18" stroke="#ffffff44"/>',
        '<path d="M196 44 v80" stroke="#ffffff55" stroke-width="3" stroke-dasharray="5 6"/>',
        '<circle cx="200" cy="66" r="8" fill="#caf0f8"/>',
        '<circle cx="222" cy="102" r="8" fill="#90e0ef"/>',
    ]);
}

function literatureSvg(): string
{
    return svgDocument('Classic literature journey', '#403d39', '#6c584c', [
        '<rect x="72" y="56" width="78" height="60" rx="6" fill="#f8f4e3"/>',
        '<path d="M111 56 v60" stroke="#d4c2a8" stroke-width="3"/>',
        '<path d="M83 71 h22 M83 82 h18 M117 71 h22 M117 82 h18" stroke="#b08968" stroke-width="3" stroke-linecap="round"/>',
        '<path d="M178 107 q16 -25 38 -10 q-15 -1 -18 17 q-10 -10 -20 -7z" fill="#9ad1d4"/>',
        '<path d="M190 73 l12 18 h-24z" fill="#fefae0" stroke="#ffffff77"/>',
        '<path d="M214 90 l0 26" stroke="#fefae0" stroke-width="3"/>',
        '<path d="M72 126 q52 -16 104 0" fill="none" stroke="#ffffff35" stroke-width="4" stroke-linecap="round"/>',
    ]);
}

function artSvg(): string
{
    return svgDocument('Renaissance atelier', '#5f0f40', '#9a031e', [
        '<rect x="70" y="42" width="98" height="84" rx="10" fill="#f3d5b5" stroke="#e6ccb2" stroke-width="5"/>',
        '<path d="M86 101 q22 -42 44 -10 t44 0" fill="none" stroke="#bc6c25" stroke-width="6" stroke-linecap="round"/>',
        '<circle cx="102" cy="66" r="8" fill="#dda15e"/>',
        '<circle cx="136" cy="58" r="8" fill="#e9edc9"/>',
        '<circle cx="148" cy="112" r="8" fill="#ccd5ae"/>',
        '<path d="M198 55 l22 56" stroke="#fefae0" stroke-width="8" stroke-linecap="round"/>',
        '<ellipse cx="230" cy="119" rx="18" ry="12" fill="#f4a261"/>',
        '<circle cx="222" cy="115" r="5" fill="#2a9d8f"/>',
        '<circle cx="232" cy="122" r="5" fill="#e76f51"/>',
        '<circle cx="239" cy="113" r="5" fill="#e9c46a"/>',
    ]);
}

function cloudSvg(): string
{
    return svgDocument('Cloud infrastructure', '#0b1f33', '#144552', [
        '<path d="M97 96 h102 a20 20 0 0 0 0 -40 q-6 -22 -31 -22 q-20 0 -30 16 q-8 -7 -19 -7 q-26 0 -30 27 q-18 3 -18 18 q0 8 6 14 q6 6 20 6z" fill="#caf0f8" fill-opacity="0.9"/>',
        '<rect x="83" y="104" width="56" height="20" rx="6" fill="#1d3557" stroke="#8ecae6"/>',
        '<rect x="161" y="104" width="56" height="20" rx="6" fill="#1d3557" stroke="#8ecae6"/>',
        '<circle cx="95" cy="114" r="3" fill="#90e0ef"/>',
        '<circle cx="173" cy="114" r="3" fill="#90e0ef"/>',
        '<path d="M150 96 v28" stroke="#ffffff77" stroke-width="4" stroke-linecap="round"/>',
    ]);
}

function historySvg(): string
{
    return svgDocument('Medieval conquest motif', '#4a2c2a', '#7f5539', [
        '<path d="M96 120 L96 64 q0 -22 24 -30 q24 8 24 30 v56z" fill="#d4a373" stroke="#fefae0aa" stroke-width="4"/>',
        '<rect x="146" y="52" width="8" height="76" rx="4" fill="#ddb892"/>',
        '<path d="M154 54 q36 10 40 28 q-22 6 -40 0z" fill="#a4133c"/>',
        '<path d="M198 42 q12 6 0 24 q-12 -18 0 -24z" fill="#fefae0"/>',
        '<path d="M78 128 q40 -16 80 0" fill="none" stroke="#ffffff33" stroke-width="4" stroke-linecap="round"/>',
        '<path d="M174 114 q16 -8 28 0" fill="none" stroke="#ffffff33" stroke-width="4" stroke-linecap="round"/>',
    ]);
}

function storageSvg(): string
{
    return svgDocument('Digital storage hardware', '#102a43', '#243b53', [
        '<rect x="72" y="48" width="156" height="76" rx="12" fill="#0b132b" stroke="#8ecae6" stroke-width="4"/>',
        '<rect x="94" y="66" width="48" height="40" rx="6" fill="#1d3557" stroke="#caf0f8"/>',
        '<path d="M164 64 h42 M164 78 h42 M164 92 h42 M164 106 h28" stroke="#90e0ef" stroke-width="4" stroke-linecap="round"/>',
        '<path d="M82 48 v-10 M104 48 v-10 M126 48 v-10 M148 48 v-10 M170 48 v-10 M192 48 v-10 M214 48 v-10" stroke="#ffd166" stroke-width="4" stroke-linecap="round"/>',
        '<path d="M82 124 v10 M104 124 v10 M126 124 v10 M148 124 v10 M170 124 v10 M192 124 v10 M214 124 v10" stroke="#ffd166" stroke-width="4" stroke-linecap="round"/>',
    ]);
}

function mythologySvg(): string
{
    return svgDocument('Moon and hunt', '#2b124c', '#522b5b', [
        '<path d="M95 84 a34 34 0 1 0 42 -48 a28 28 0 1 1 -42 48z" fill="#fefae0"/>',
        '<path d="M172 46 q34 38 0 76" fill="none" stroke="#ffd166" stroke-width="7" stroke-linecap="round"/>',
        '<path d="M172 46 q22 38 0 76" fill="none" stroke="#ffffff88" stroke-width="2" stroke-linecap="round"/>',
        '<path d="M170 84 h44" stroke="#fefae0" stroke-width="4" stroke-linecap="round"/>',
        '<path d="M214 84 l-10 -8 M214 84 l-10 8" stroke="#fefae0" stroke-width="4" stroke-linecap="round"/>',
        '<circle cx="212" cy="52" r="4" fill="#ffffffaa"/>',
        '<circle cx="234" cy="72" r="3" fill="#ffffffaa"/>',
        '<circle cx="226" cy="106" r="4" fill="#ffffffaa"/>',
    ]);
}

$replacements = [
    132 => [
        'path' => '/upload/domanda/image/sci_132_dna_pattern.svg',
        'svg' => dnaSvg(),
    ],
    1649 => [
        'path' => '/upload/domanda/image/mat_1649_coin_odds.svg',
        'svg' => probabilitySvg(),
    ],
    950 => [
        'path' => '/upload/domanda/image/lit_950_classic_journey.svg',
        'svg' => literatureSvg(),
    ],
    877 => [
        'path' => '/upload/domanda/image/art_877_renaissance_atelier.svg',
        'svg' => artSvg(),
    ],
    543 => [
        'path' => '/upload/domanda/image/tec_543_cloud_infrastructure.svg',
        'svg' => cloudSvg(),
    ],
    385 => [
        'path' => '/upload/domanda/image/hst_385_medieval_conquest.svg',
        'svg' => historySvg(),
    ],
    502 => [
        'path' => '/upload/domanda/image/tec_502_storage_hardware.svg',
        'svg' => storageSvg(),
    ],
    1415 => [
        'path' => '/upload/domanda/image/myt_1415_moon_hunt.svg',
        'svg' => mythologySvg(),
    ],
];

$imageDir = BASE_PATH . '/public/upload/domanda/image';
foreach ($replacements as $config) {
    file_put_contents(BASE_PATH . '/public' . $config['path'], $config['svg'], LOCK_EX);
}

$pdo = Database::getInstance();
$updateStmt = $pdo->prepare(
    'UPDATE domande d
     INNER JOIN sessione_domande sd ON sd.domanda_id = d.id
     SET d.media_image_path = :path
     WHERE d.id = :id
       AND sd.sessione_id = 2'
);

$updated = [];
foreach ($replacements as $questionId => $config) {
    $updateStmt->execute([
        'id' => $questionId,
        'path' => $config['path'],
    ]);
    if ($updateStmt->rowCount() > 0) {
        $updated[] = [
            'id' => $questionId,
            'path' => $config['path'],
        ];
    }
}

echo json_encode([
    'session_id' => 2,
    'updated' => count($updated),
    'items' => $updated,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
