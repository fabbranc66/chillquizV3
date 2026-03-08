<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/Database.php';

$pdo = App\Core\Database::getInstance();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function norm(string $value): string
{
    $v = mb_strtolower(trim($value), 'UTF-8');
    $v = str_replace(["\r", "\n", "\t"], ' ', $v);
    $v = preg_replace('/\s+/u', ' ', $v) ?? $v;
    $tr = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
    if (is_string($tr) && $tr !== '') $v = $tr;
    $v = preg_replace('/[^a-z0-9 ]+/i', '', $v) ?? $v;
    $v = preg_replace('/\s+/', ' ', trim($v)) ?? trim($v);
    return $v;
}

function tokenize(string $value): array
{
    $tokens = preg_split('/\s+/', norm($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $stopwords = ['quale','quali','chi','come','dove','quando','del','della','delle','degli','dei','nel','nella','nelle','negli','nello','che','e','il','la','lo','i','gli','le','di','a','da','per','con','in','su','tra','fra','si','film','cinema','regista','attore','attrice','saga','serie','personaggio','titolo'];
    $out = [];
    foreach ($tokens as $token) {
        if (in_array($token, $stopwords, true)) continue;
        if (strlen($token) < 3) continue;
        $out[$token] = true;
    }
    return array_keys($out);
}

function jaccard(array $a, array $b): float
{
    $aSet = array_fill_keys($a, true);
    $bSet = array_fill_keys($b, true);
    $intersection = array_intersect_key($aSet, $bSet);
    $union = $aSet + $bSet;
    return count($union) > 0 ? (count($intersection) / count($union)) : 0.0;
}

function fingerprint(array $q): string
{
    $chunks = ['t:' . norm((string)$q['testo']), 'a:5', 'y:media'];
    $ops = [];
    foreach ($q['opzioni'] as $idx => $opt) {
        $ops[] = (($idx === (int)$q['corretta']) ? '1:' : '0:') . norm((string)$opt);
    }
    sort($ops, SORT_STRING);
    foreach ($ops as $o) $chunks[] = 'o:' . $o;
    return sha1(implode('|', $chunks));
}

function slugify(string $value): string
{
    return trim(str_replace(' ', '_', norm($value)), '_');
}

function fetchJson(string $url): ?array
{
    $context = stream_context_create([
        'http' => ['timeout' => 20, 'ignore_errors' => true, 'header' => "User-Agent: ChillQuizSeeder/1.0\r\nAccept: application/json\r\n"],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || $raw === '') return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function fetchBinary(string $url): ?string
{
    $context = stream_context_create([
        'http' => ['timeout' => 30, 'ignore_errors' => true, 'header' => "User-Agent: ChillQuizSeeder/1.0\r\nAccept: image/*,*/*;q=0.8\r\n"],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $raw = @file_get_contents($url, false, $context);
    return is_string($raw) && $raw !== '' ? $raw : null;
}

function imageExtensionFromUrl(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH);
    $ext = strtolower((string)pathinfo((string)$path, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','webp'], true)) return $ext === 'jpeg' ? 'jpg' : $ext;
    return 'jpg';
}

$questionsPath = BASE_PATH . '/storage/cinema_questions.json';
$questions = json_decode((string)file_get_contents($questionsPath), true);
if (!is_array($questions)) throw new RuntimeException('Dataset cinema non valido');

$existingStmt = $pdo->query("SELECT id, testo, fingerprint_unico FROM domande WHERE argomento_id = 5");
$existingRows = $existingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$existingFingerprints = [];
$existingTokenMaps = [];
foreach ($existingRows as $row) {
    $fp = (string)($row['fingerprint_unico'] ?? '');
    if ($fp !== '') $existingFingerprints[$fp] = true;
    $existingTokenMaps[(int)$row['id']] = tokenize((string)($row['testo'] ?? ''));
}

$insertDomandaStmt = $pdo->prepare("INSERT INTO domande (testo, codice_domanda, fingerprint_unico, difficolta, tipo_domanda, fase_domanda, media_image_path, argomento_id, attiva) VALUES (:testo, :codice_domanda, :fingerprint_unico, :difficolta, 'MEDIA', 'domanda', NULL, 5, 1)");
$updateCodeStmt = $pdo->prepare("UPDATE domande SET codice_domanda = :codice WHERE id = :id");
$updateImageStmt = $pdo->prepare("UPDATE domande SET media_image_path = :path WHERE id = :id");
$insertOpzioneStmt = $pdo->prepare("INSERT INTO opzioni (domanda_id, testo, corretta) VALUES (:domanda_id, :testo, :corretta)");

$uploadDir = BASE_PATH . '/public/upload/domanda/image';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$report = [];
$inserted = 0;
$skipped = 0;
$imageOk = 0;
$imageFail = 0;

foreach ($questions as $question) {
    $fp = fingerprint($question);
    $questionTokens = tokenize((string)$question['testo']);
    if (isset($existingFingerprints[$fp])) {
        $skipped++;
        $report[] = ['status' => 'skip-fingerprint', 'testo' => $question['testo'], 'id' => null];
        continue;
    }
    $duplicateByTokens = false;
    foreach ($existingTokenMaps as $existingId => $existingTokens) {
        $score = jaccard($questionTokens, $existingTokens);
        $common = array_intersect($questionTokens, $existingTokens);
        if ($score >= 0.72 || (count($common) >= 5 && $score >= 0.55)) {
            $duplicateByTokens = true;
            $report[] = ['status' => 'skip-token', 'testo' => $question['testo'], 'id' => $existingId, 'score' => round($score, 3)];
            break;
        }
    }
    if ($duplicateByTokens) { $skipped++; continue; }

    $pdo->beginTransaction();
    try {
        $insertDomandaStmt->execute(['testo' => $question['testo'], 'codice_domanda' => '', 'fingerprint_unico' => $fp, 'difficolta' => '1.0']);
        $domandaId = (int)$pdo->lastInsertId();
        $updateCodeStmt->execute(['codice' => sprintf('MED-A005-%05d', $domandaId), 'id' => $domandaId]);
        foreach ($question['opzioni'] as $index => $option) {
            $insertOpzioneStmt->execute(['domanda_id' => $domandaId, 'testo' => $option, 'corretta' => ($index === (int)$question['corretta']) ? 1 : 0]);
        }
        $summaryUrl = 'https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode((string)$question['image_page']);
        $summary = fetchJson($summaryUrl);
        $imageSource = '';
        if (is_array($summary)) $imageSource = (string)($summary['thumbnail']['source'] ?? $summary['originalimage']['source'] ?? '');
        if ($imageSource !== '') {
            $binary = fetchBinary($imageSource);
            if ($binary !== null) {
                $ext = imageExtensionFromUrl($imageSource);
                $filename = 'cin_' . $domandaId . '_' . slugify((string)$question['image_page']) . '.' . $ext;
                file_put_contents($uploadDir . '/' . $filename, $binary);
                $updateImageStmt->execute(['path' => '/upload/domanda/image/' . $filename, 'id' => $domandaId]);
                $imageOk++;
            } else {
                $imageFail++;
            }
        } else {
            $imageFail++;
        }
        $pdo->commit();
        $inserted++;
        $existingFingerprints[$fp] = true;
        $existingTokenMaps[$domandaId] = $questionTokens;
        $report[] = ['status' => 'insert', 'testo' => $question['testo'], 'id' => $domandaId];
    } catch (Throwable $e) {
        $pdo->rollBack();
        $report[] = ['status' => 'error', 'testo' => $question['testo'], 'message' => $e->getMessage()];
    }
}

echo 'INSERTED=' . $inserted . PHP_EOL;
echo 'SKIPPED=' . $skipped . PHP_EOL;
echo 'IMAGE_OK=' . $imageOk . PHP_EOL;
echo 'IMAGE_FAIL=' . $imageFail . PHP_EOL;
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;