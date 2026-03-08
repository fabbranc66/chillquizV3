<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/Database.php';

use App\Core\Database;

function slugify(string $value): string
{
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = trim($value);
    $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($trans) && $trans !== '') {
        $value = $trans;
    }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;
    return trim($value, '_');
}

function httpGetJson(string $url): ?array
{
    $raw = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'ChillQuizBackfill/1.0',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $response = curl_exec($ch);
        if (is_string($response)) {
            $raw = $response;
        }
        curl_close($ch);
    }

    if (!is_string($raw) || $raw === '') {
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

        $fallback = @file_get_contents($url, false, $context);
        if (is_string($fallback)) {
            $raw = $fallback;
        }
    }

    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function fetchBinary(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'ChillQuizBackfill/1.0',
            CURLOPT_HTTPHEADER => ['Accept: image/*,*/*;q=0.8'],
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if (is_string($response) && $response !== '' && $status >= 200 && $status < 300) {
            return $response;
        }
    }

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

function isValidImageBinary(string $binary): bool
{
    if ($binary === '') {
        return false;
    }

    $trimmed = ltrim($binary);
    if (str_starts_with($trimmed, '<!DOCTYPE html') || str_starts_with($trimmed, '<html')) {
        return false;
    }

    if (str_starts_with($trimmed, '<?xml') || str_starts_with($trimmed, '<svg')) {
        return true;
    }

    if (str_starts_with($binary, "\xFF\xD8\xFF")) {
        return true;
    }

    if (str_starts_with($binary, "\x89PNG\r\n\x1A\n")) {
        return true;
    }

    if (str_starts_with($binary, "GIF87a") || str_starts_with($binary, "GIF89a")) {
        return true;
    }

    if (substr($binary, 0, 4) === 'RIFF' && substr($binary, 8, 4) === 'WEBP') {
        return true;
    }

    return false;
}

function imageExtensionFromUrl(string $url, string $binary): string
{
    $path = parse_url($url, PHP_URL_PATH);
    $ext = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'], true)) {
        return $ext === 'jpeg' ? 'jpg' : $ext;
    }

    $trimmed = ltrim($binary);
    if (str_starts_with($trimmed, '<?xml') || str_starts_with($trimmed, '<svg')) {
        return 'svg';
    }
    if (str_starts_with($binary, "\x89PNG\r\n\x1A\n")) {
        return 'png';
    }
    if (str_starts_with($binary, "GIF87a") || str_starts_with($binary, "GIF89a")) {
        return 'gif';
    }
    if (substr($binary, 0, 4) === 'RIFF' && substr($binary, 8, 4) === 'WEBP') {
        return 'webp';
    }
    return 'jpg';
}

function cleanSearchTerm(string $value): string
{
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    $value = preg_replace('/[“”"!?]+/u', '', $value) ?? $value;
    return trim($value);
}

function isWeakAnswer(string $answer): bool
{
    $answer = mb_strtolower(trim($answer), 'UTF-8');
    if ($answer === '') {
        return true;
    }

    if (preg_match('/^[0-9 .,:×x^\/+-]+[a-z°²³%]*$/iu', $answer)) {
        return true;
    }

    $weakAnswers = [
        'neutra',
        'greca',
        'greci',
        'latini',
        'babilonese',
        'africa',
        'america del sud',
        'una unità di distanza',
        'numero di protoni',
        'due cellule geneticamente identiche',
        'primo livello',
        'forza',
        'timina',
        'covalente',
        'fotone',
        'insula', // guardia contro match sporchi
    ];

    return in_array($answer, $weakAnswers, true);
}

function extractSearchCoreFromQuestion(string $question): string
{
    $question = cleanSearchTerm($question);
    $patterns = [
        '/^In quale anno (.+)$/iu',
        '/^In quale secolo (.+)$/iu',
        '/^Chi fu (.+)$/iu',
        '/^Chi guid[oò] (.+)$/iu',
        '/^Chi guid[òo] (.+)$/iu',
        '/^Chi era (.+)$/iu',
        '/^Come si chiamava (.+)$/iu',
        '/^Quale (.+)$/iu',
        '/^Il processo con cui (.+)$/iu',
        '/^La differenza di potenziale elettrico (.+)$/iu',
        '/^La seconda legge della termodinamica (.+)$/iu',
        '/^La relativit[aà] generale (.+)$/iu',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $question, $matches) === 1) {
            return cleanSearchTerm((string) $matches[1]);
        }
    }

    return $question;
}

function buildSearchTerms(array $row): array
{
    $id = (int) ($row['id'] ?? 0);
    $question = (string) ($row['testo'] ?? '');
    $answer = (string) ($row['risposta_corretta'] ?? '');
    $subject = (string) ($row['argomento'] ?? '');

    $manual = [
        1 => ["Caduta dell'Impero romano d'Occidente", 'Impero romano d Occidente'],
        3 => ['Prima guerra mondiale'],
        4 => ['Prima guerra mondiale'],
        6 => ['Seconda guerra mondiale'],
        7 => ['Seconda guerra mondiale'],
        9 => ["Proclamazione del Regno d'Italia"],
        13 => ['Cristoforo Colombo'],
        17 => ["Caduta dell'Impero romano d'Occidente"],
        18 => ['Roma antica'],
        22 => ['Sparta'],
        23 => ['Rivoluzione francese'],
        26 => ['Magna Carta'],
        27 => ['Gaio Giulio Cesare'],
        29 => ['Partenone'],
        31 => ['Rivoluzione russa'],
        35 => ['Guerra civile spagnola'],
        38 => ['Presa della Bastiglia'],
        39 => ['Conquista normanna dell Inghilterra'],
        42 => ['Marcia su Roma'],
        44 => ['Pace di Vestfalia'],
        47 => ['Grande Muraglia cinese'],
        49 => ['Codice di Hammurabi'],
        50 => ['Trattato di Maastricht'],
        71 => ['Volga'],
        72 => ['Nilo'],
        73 => ['Antartide'],
        74 => ['Oceano Pacifico'],
        76 => ['Kenya'],
        77 => ['Brasile'],
        78 => ['Egitto'],
        80 => ['Washington, D.C.'],
        81 => ['Città del Messico'],
        86 => ['Pechino'],
        87 => ['Nuova Delhi'],
        90 => ['Stretto di Gibilterra'],
        91 => ['Monti Urali'],
        92 => ['Mare Adriatico'],
        95 => ['Pretoria'],
        96 => ['Moscow', 'Mosca'],
        97 => ['Kyiv'],
        101 => ['Isaac Newton', 'newton unità di misura'],
        103 => ['Carbonio'],
        104 => ['Azoto'],
        105 => ['Potenziale idrogenionico'],
        106 => ['Mitocondrio'],
        107 => ['Velocità della luce'],
        109 => ['DNA'],
        110 => ['Legge di Boyle'],
        111 => ['Ampere'],
        112 => ['Cervelletto'],
        113 => ['Acqua'],
        114 => ['Fotosintesi'],
        115 => ['Elettrone'],
        116 => ['Energia potenziale gravitazionale'],
        117 => ['Acido cloridrico'],
        118 => ['Terza legge di Newton'],
        119 => ['Insulina'],
        120 => ['Accelerazione di gravità'],
        121 => ['Legame covalente'],
        122 => ['Vitamina D'],
        123 => ['Omozigosi'],
        124 => ['Stratosfera'],
        125 => ['Volt'],
        126 => ['Grandezza vettoriale'],
        127 => ['Numero atomico'],
        128 => ['Amilasi'],
        129 => ['Anno luce'],
        130 => ['Reazione redox'],
        131 => ['Seconda legge della termodinamica'],
        132 => ['Timina'],
        133 => ['Rifrazione'],
        134 => ['Acidi carbossilici'],
        135 => ['Glycolysis', 'Glicolisi'],
        136 => ['Seconda legge di Newton'],
        137 => ['pH'],
        138 => ['Mitosi'],
        139 => ['Tesla unità di misura'],
        140 => ['Fotone'],
        141 => ['Principio di Archimede'],
        142 => ['Effetto tunnel'],
        143 => ['Reazione a catena della polimerasi'],
        144 => ['Numero di ossidazione'],
        145 => ['Produttore primario'],
        146 => ['Legge dei gas ideali'],
        147 => ['Spettrometria di massa'],
        148 => ['Relatività generale'],
        149 => ['Fisica teorica'],
        150 => ['Insulina'],
    ];

    $terms = [];
    if (isset($manual[$id])) {
        foreach ($manual[$id] as $term) {
            $terms[] = cleanSearchTerm($term);
        }
    }

    if (!isWeakAnswer($answer)) {
        $terms[] = cleanSearchTerm($answer);
    }

    $terms[] = extractSearchCoreFromQuestion($question);
    $terms[] = cleanSearchTerm($question);

    if ($subject !== '') {
        $terms[] = cleanSearchTerm($answer . ' ' . $subject);
    }

    $unique = [];
    foreach ($terms as $term) {
        if ($term === '') {
            continue;
        }
        $key = mb_strtolower($term, 'UTF-8');
        $unique[$key] = $term;
    }

    return array_values($unique);
}

function wikipediaSearch(string $lang, string $term): ?string
{
    $url = sprintf(
        'https://%s.wikipedia.org/w/api.php?action=query&list=search&srlimit=1&format=json&srsearch=%s',
        $lang,
        rawurlencode($term)
    );
    $data = httpGetJson($url);
    $title = (string) ($data['query']['search'][0]['title'] ?? '');
    return $title !== '' ? $title : null;
}

function wikipediaDirectImageSource(string $lang, string $title): ?string
{
    return wikipediaImageSource($lang, $title);
}

function wikipediaImageSource(string $lang, string $title): ?string
{
    $url = sprintf(
        'https://%s.wikipedia.org/w/api.php?action=query&prop=pageimages&titles=%s&piprop=thumbnail&pithumbsize=1200&format=json',
        $lang,
        rawurlencode($title)
    );
    $data = httpGetJson($url);
    if (!isset($data['query']['pages']) || !is_array($data['query']['pages'])) {
        return null;
    }

    foreach ($data['query']['pages'] as $page) {
        $source = (string) ($page['thumbnail']['source'] ?? '');
        if ($source !== '') {
            return $source;
        }
    }

    $fallbackUrl = sprintf(
        'https://%s.wikipedia.org/w/api.php?action=query&prop=pageimages&titles=%s&piprop=original&format=json',
        $lang,
        rawurlencode($title)
    );
    $fallbackData = httpGetJson($fallbackUrl);
    if (!isset($fallbackData['query']['pages']) || !is_array($fallbackData['query']['pages'])) {
        return null;
    }

    foreach ($fallbackData['query']['pages'] as $page) {
        $source = (string) ($page['original']['source'] ?? '');
        if ($source !== '') {
            return $source;
        }
    }

    return null;
}

$pdo = Database::getInstance();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$rewriteIds = [56, 59, 64, 65, 96];

$uploadDir = BASE_PATH . '/public/upload/domanda/image';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
    throw new RuntimeException('Impossibile creare la cartella upload immagini.');
}

$selectSql = "
    SELECT d.id, d.codice_domanda, a.nome AS argomento, d.testo, o.testo AS risposta_corretta
    FROM domande d
    LEFT JOIN argomenti a ON a.id = d.argomento_id
    LEFT JOIN opzioni o ON o.domanda_id = d.id AND o.corretta = 1
    WHERE (
            (d.media_image_path IS NULL OR TRIM(d.media_image_path) = '')
            OR d.id IN (" . implode(',', $rewriteIds) . ")
          )
      AND d.codice_domanda LIKE 'CLS-%'
    ORDER BY d.id
";
$rows = $pdo->query($selectSql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

$updateStmt = $pdo->prepare("UPDATE domande SET media_image_path = :path WHERE id = :id");

$report = [
    'updated' => [],
    'failed' => [],
];

foreach ($rows as $row) {
    $id = (int) $row['id'];
    $searchTerms = buildSearchTerms($row);
    $matchedTitle = null;
    $matchedLang = null;
    $imageSource = null;

    foreach ($searchTerms as $term) {
        foreach (['it', 'en'] as $lang) {
            $directSource = wikipediaDirectImageSource($lang, $term);
            usleep(120000);
            if ($directSource !== null) {
                $matchedTitle = $term;
                $matchedLang = $lang;
                $imageSource = $directSource;
                break 2;
            }

            $title = wikipediaSearch($lang, $term);
            if ($title === null) {
                usleep(120000);
                continue;
            }

            $source = wikipediaImageSource($lang, $title);
            usleep(120000);
            if ($source === null) {
                continue;
            }

            $matchedTitle = $title;
            $matchedLang = $lang;
            $imageSource = $source;
            break 2;
        }
    }

    if ($imageSource === null) {
        $report['failed'][] = [
            'id' => $id,
            'codice' => $row['codice_domanda'],
            'reason' => 'no-image-found',
            'terms' => $searchTerms,
        ];
        continue;
    }

    $binary = fetchBinary($imageSource);
    usleep(120000);
    if (!is_string($binary) || !isValidImageBinary($binary)) {
        $report['failed'][] = [
            'id' => $id,
            'codice' => $row['codice_domanda'],
            'reason' => 'invalid-download',
            'title' => $matchedTitle,
            'lang' => $matchedLang,
            'image' => $imageSource,
        ];
        continue;
    }

    $ext = imageExtensionFromUrl($imageSource, $binary);
    $fileName = sprintf('cls_%d_%s.%s', $id, slugify($matchedTitle ?? (string) $row['codice_domanda']), $ext);
    $fullPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
    $publicPath = '/upload/domanda/image/' . $fileName;

    file_put_contents($fullPath, $binary, LOCK_EX);
    $updateStmt->execute([
        'id' => $id,
        'path' => $publicPath,
    ]);

    $report['updated'][] = [
        'id' => $id,
        'codice' => $row['codice_domanda'],
        'path' => $publicPath,
        'title' => $matchedTitle,
        'lang' => $matchedLang,
    ];
}

echo json_encode([
    'total' => count($rows),
    'updated' => count($report['updated']),
    'failed' => count($report['failed']),
    'sample_failed' => array_slice($report['failed'], 0, 25),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
