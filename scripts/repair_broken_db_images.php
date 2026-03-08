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

function httpGet(string $url, array $headers = [], int $timeout = 20): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => 'ChillQuizRepair/1.0',
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if (!is_string($response) || $response === '' || $status < 200 || $status >= 300) {
        return null;
    }

    return $response;
}

function httpGetJson(string $url): ?array
{
    $raw = httpGet($url, ['Accept: application/json'], 20);
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function wikipediaSummaryImageSource(string $lang, string $title): ?string
{
    $url = sprintf(
        'https://%s.wikipedia.org/api/rest_v1/page/summary/%s',
        $lang,
        rawurlencode($title)
    );
    $data = httpGetJson($url);
    if (!is_array($data)) {
        return null;
    }

    $source = (string) ($data['originalimage']['source'] ?? '');
    if ($source !== '') {
        return $source;
    }

    $source = (string) ($data['thumbnail']['source'] ?? '');
    return $source !== '' ? $source : null;
}

function fetchBinary(string $url): ?string
{
    return httpGet($url, ['Accept: image/*,*/*;q=0.8'], 30);
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
    if (str_starts_with($binary, 'GIF87a') || str_starts_with($binary, 'GIF89a')) {
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
    if (str_starts_with($binary, 'GIF87a') || str_starts_with($binary, 'GIF89a')) {
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

    $weak = [
        'si', 'no', 'vero', 'falso', 'classico', 'media', 'domanda', 'intro',
        'neutra', 'greca', 'greci', 'latini', 'africa', 'europa', 'asia',
    ];

    return in_array($answer, $weak, true);
}

function extractCoreFromQuestion(string $question): string
{
    $question = cleanSearchTerm($question);
    $patterns = [
        '/^In quale anno (.+)$/iu',
        '/^In quale secolo (.+)$/iu',
        '/^Chi fu (.+)$/iu',
        '/^Chi era (.+)$/iu',
        '/^Chi guid[oò] (.+)$/iu',
        '/^Come si chiamava (.+)$/iu',
        '/^Quale (.+)$/iu',
        '/^Qual è (.+)$/iu',
        '/^Qual e (.+)$/iu',
        '/^Il processo con cui (.+)$/iu',
        '/^La (.+)$/iu',
        '/^L[ae] (.+)$/iu',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $question, $matches) === 1) {
            return cleanSearchTerm((string) $matches[1]);
        }
    }

    return $question;
}

function filenameStemTerm(string $path): string
{
    $base = pathinfo((string) $path, PATHINFO_FILENAME);
    $base = preg_replace('/^[a-z]+_\d+_/i', '', $base) ?? $base;
    $base = preg_replace('/^q\d+_/i', '', $base) ?? $base;
    $base = str_replace('_', ' ', $base);
    return cleanSearchTerm($base);
}

function expandAliases(array $terms): array
{
    $aliases = [
        '2fa' => ['Two-factor authentication', 'Multi-factor authentication'],
        'mpeg-4' => ['MP4 file format', 'MPEG-4 Part 14'],
        'multifactorauthentication' => ['Multi-factor authentication', 'Two-factor authentication'],
        'call of duty warzone' => ['Call of Duty: Warzone'],
        'genv' => ['Gen V (TV series)', 'Gen V'],
        'pangod' => ['Pan (god)'],
        'narcissusmythology' => ['Narcissus (mythology)'],
        'chimeramythology' => ['Chimera (mythology)'],
        'atlasmythology' => ['Atlas (mythology)'],
        'parismythology' => ['Paris (mythology)'],
        'venusmythology' => ['Venus (mythology)'],
        'neptunemythology' => ['Neptune (mythology)'],
        'marsmythology' => ['Mars (mythology)'],
        'mercurymythology' => ['Mercury (mythology)'],
        'vestamythology' => ['Vesta (mythology)'],
        'dianamythology' => ['Diana (mythology)'],
        'sirenmythology' => ['Siren (mythology)'],
        'calypsomythology' => ['Calypso (mythology)'],
        'romulusandremus' => ['Romulus and Remus'],
        'setdeity' => ['Set (deity)'],
        'helbeing' => ['Hel (being)'],
        'fractionmathematics' => ['Fraction'],
        'squarenumber' => ['Square number'],
        'cubealgebra' => ['Cube (algebra)'],
        'primenumber' => ['Prime number'],
        'compositenumber' => ['Composite number'],
        'rightangle' => ['Right angle'],
        'straightangle' => ['Straight angle'],
        'equilateraltriangle' => ['Equilateral triangle'],
        'leastcommonmultiple' => ['Least common multiple'],
        'absolutevalue' => ['Absolute value'],
        'linearequation' => ['Linear equation'],
        'naturalnumber' => ['Natural number'],
        'negativenumber' => ['Negative number'],
        'probabilityaxioms' => ['Probability axioms'],
        'anaphorarhetoric' => ['Anaphora (rhetoric)'],
        'italiangrammar' => ['Italian grammar'],
        'italianpronouns' => ['Italian pronouns'],
    ];

    $expanded = $terms;
    foreach ($terms as $term) {
        $key = mb_strtolower(cleanSearchTerm($term), 'UTF-8');
        if (!isset($aliases[$key])) {
            continue;
        }

        foreach ($aliases[$key] as $alias) {
            $expanded[] = $alias;
        }
    }

    return $expanded;
}

function contextualSearchTerms(string $answer, string $argomento): array
{
    if ($answer === '') {
        return [];
    }

    $argomentoKey = mb_strtolower($argomento, 'UTF-8');
    $templates = [
        'videogiochi' => ['%s video game'],
        'mitologia' => ['%s mythology', '%s myth'],
        'tecnologia' => ['%s technology'],
        'matematica' => ['%s mathematics', '%s geometry', '%s arithmetic'],
        'lingua italiana' => ['%s grammar', '%s rhetoric', '%s linguistics', '%s italian language'],
        'tv e serie' => ['%s TV series', '%s television series'],
    ];

    if (!isset($templates[$argomentoKey])) {
        return [];
    }

    $terms = [];
    foreach ($templates[$argomentoKey] as $template) {
        $terms[] = sprintf($template, $answer);
    }

    return $terms;
}

function buildSearchTerms(array $row): array
{
    $terms = [];
    $answer = cleanSearchTerm((string) ($row['risposta_corretta'] ?? ''));
    $question = cleanSearchTerm((string) ($row['testo'] ?? ''));
    $argomento = cleanSearchTerm((string) ($row['argomento'] ?? ''));
    $stem = filenameStemTerm((string) ($row['media_image_path'] ?? ''));

    if ($stem !== '') {
        $terms[] = $stem;
    }
    $terms = array_merge($terms, contextualSearchTerms($answer, $argomento));
    if (!isWeakAnswer($answer)) {
        $terms[] = $answer;
    }

    $terms[] = extractCoreFromQuestion($question);
    $terms[] = $question;

    if ($argomento !== '' && !isWeakAnswer($answer)) {
        $terms[] = $answer . ' ' . $argomento;
    }

    $terms = expandAliases($terms);

    $unique = [];
    foreach ($terms as $term) {
        $term = cleanSearchTerm($term);
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
    return (string) ($data['query']['search'][0]['title'] ?? '') ?: null;
}

function wikipediaImageSource(string $lang, string $title): ?string
{
    $thumbUrl = sprintf(
        'https://%s.wikipedia.org/w/api.php?action=query&prop=pageimages&titles=%s&piprop=thumbnail&pithumbsize=1200&format=json',
        $lang,
        rawurlencode($title)
    );
    $thumbData = httpGetJson($thumbUrl);
    if (isset($thumbData['query']['pages']) && is_array($thumbData['query']['pages'])) {
        foreach ($thumbData['query']['pages'] as $page) {
            $source = (string) ($page['thumbnail']['source'] ?? '');
            if ($source !== '') {
                return $source;
            }
        }
    }

    $origUrl = sprintf(
        'https://%s.wikipedia.org/w/api.php?action=query&prop=pageimages&titles=%s&piprop=original&format=json',
        $lang,
        rawurlencode($title)
    );
    $origData = httpGetJson($origUrl);
    if (isset($origData['query']['pages']) && is_array($origData['query']['pages'])) {
        foreach ($origData['query']['pages'] as $page) {
            $source = (string) ($page['original']['source'] ?? '');
            if ($source !== '') {
                return $source;
            }
        }
    }

    return null;
}

function isBrokenStoredFile(string $fullPath): bool
{
    if (!is_file($fullPath)) {
        return true;
    }

    $head = file_get_contents($fullPath, false, null, 0, 512);
    $trim = ltrim((string) $head);
    if (stripos($trim, '<!DOCTYPE html') === 0 || stripos($trim, '<html') === 0) {
        return true;
    }

    return false;
}

$limit = 100;
foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m) === 1) {
        $limit = max(1, (int) $m[1]);
    }
}

$pdo = Database::getInstance();

$sql = "
    SELECT d.id, d.codice_domanda, d.testo, d.media_image_path, a.nome AS argomento, o.testo AS risposta_corretta
    FROM domande d
    LEFT JOIN argomenti a ON a.id = d.argomento_id
    LEFT JOIN opzioni o ON o.domanda_id = d.id AND o.corretta = 1
    WHERE d.media_image_path IS NOT NULL
      AND TRIM(d.media_image_path) <> ''
    ORDER BY d.id
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

$broken = [];
foreach ($rows as $row) {
    $fullPath = BASE_PATH . '/public' . (string) $row['media_image_path'];
    if (isBrokenStoredFile($fullPath)) {
        $broken[] = $row;
    }
}

$broken = array_slice($broken, 0, $limit);

$updateStmt = $pdo->prepare("UPDATE domande SET media_image_path = :path WHERE id = :id");
$report = [
    'total' => count($broken),
    'updated' => 0,
    'failed' => 0,
    'items' => [],
];

foreach ($broken as $row) {
    $terms = buildSearchTerms($row);
    $imageSource = null;
    $matchedTitle = null;
    $matchedLang = null;

    foreach ($terms as $term) {
        foreach (['it', 'en'] as $lang) {
            $source = wikipediaSummaryImageSource($lang, $term);
            if ($source !== null) {
                $imageSource = $source;
                $matchedTitle = $term;
                $matchedLang = $lang;
                break 2;
            }

            $source = wikipediaImageSource($lang, $term);
            if ($source !== null) {
                $imageSource = $source;
                $matchedTitle = $term;
                $matchedLang = $lang;
                break 2;
            }

            $title = wikipediaSearch($lang, $term);
            if ($title === null) {
                continue;
            }

            $source = wikipediaSummaryImageSource($lang, $title);
            if ($source !== null) {
                $imageSource = $source;
                $matchedTitle = $title;
                $matchedLang = $lang;
                break 2;
            }

            $source = wikipediaImageSource($lang, $title);
            if ($source !== null) {
                $imageSource = $source;
                $matchedTitle = $title;
                $matchedLang = $lang;
                break 2;
            }
        }
    }

    if ($imageSource === null) {
        $report['failed']++;
        $report['items'][] = [
            'id' => (int) $row['id'],
            'codice' => (string) $row['codice_domanda'],
            'status' => 'no_image_found',
            'terms' => $terms,
        ];
        continue;
    }

    $binary = fetchBinary($imageSource);
    if (!is_string($binary) || !isValidImageBinary($binary)) {
        $report['failed']++;
        $report['items'][] = [
            'id' => (int) $row['id'],
            'codice' => (string) $row['codice_domanda'],
            'status' => 'invalid_download',
            'title' => $matchedTitle,
            'lang' => $matchedLang,
            'image_source' => $imageSource,
        ];
        continue;
    }

    $prefix = 'img';
    if (preg_match('/^([a-z]+)_\d+_/i', (string) pathinfo((string) $row['media_image_path'], PATHINFO_FILENAME), $m) === 1) {
        $prefix = strtolower($m[1]);
    }

    $ext = imageExtensionFromUrl($imageSource, $binary);
    $fileName = sprintf('%s_%d_%s.%s', $prefix, (int) $row['id'], slugify((string) $matchedTitle), $ext);
    $publicPath = '/upload/domanda/image/' . $fileName;
    $fullPath = BASE_PATH . '/public' . $publicPath;

    file_put_contents($fullPath, $binary, LOCK_EX);
    $updateStmt->execute([
        'id' => (int) $row['id'],
        'path' => $publicPath,
    ]);

    $report['updated']++;
    $report['items'][] = [
        'id' => (int) $row['id'],
        'codice' => (string) $row['codice_domanda'],
        'status' => 'updated',
        'title' => $matchedTitle,
        'lang' => $matchedLang,
        'path' => $publicPath,
    ];
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
