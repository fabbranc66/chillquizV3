<?php
declare(strict_types=1);

$dsn = 'mysql:host=127.0.0.1;dbname=Sql1874742_4;charset=utf8mb4';
$user = 'root';
$pass = '';

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function norm(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = str_replace(["\r", "\n", "\t"], ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return $value;
}

function clampDifficulty(float $value): float
{
    $value = max(1.0, min(2.0, $value));
    return round($value, 1);
}

function lexicalComplexity(string $text): float
{
    $text = norm($text);
    $len = mb_strlen($text, 'UTF-8');
    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $unique = array_unique($words);

    $score = 0.0;

    if ($len >= 55) $score += 0.10;
    if ($len >= 85) $score += 0.08;
    if (count($words) >= 10) $score += 0.06;
    if (count($unique) >= 9) $score += 0.05;

    if (preg_match('/\b(quale|chi|come|dove|quando|perche)\b/u', $text)) $score += 0.02;
    if (preg_match('/\btradizionalmente|formalmente|principale|conferenza|dinastia|promotore|associato\b/u', $text)) $score += 0.10;
    if (preg_match('/\banno\b/u', $text)) $score -= 0.08;
    if (preg_match('/\bsecolo\b/u', $text)) $score -= 0.05;

    return $score;
}

function optionDiscrimination(array $options): float
{
    if (count($options) < 2) return 0.0;

    $normalized = array_map(static fn(array $o): string => norm((string) ($o['testo'] ?? '')), $options);
    $pairs = 0;
    $similaritySum = 0.0;
    $sameKindBonus = 0.0;

    for ($i = 0; $i < count($normalized); $i++) {
        for ($j = $i + 1; $j < count($normalized); $j++) {
            $a = $normalized[$i];
            $b = $normalized[$j];
            if ($a === '' || $b === '') continue;
            similar_text($a, $b, $pct);
            $similaritySum += ($pct / 100.0);
            $pairs++;
        }
    }

    $numericCount = 0;
    foreach ($normalized as $item) {
        if (preg_match('/^[0-9xivlcdm\s\.\-\(\)]+$/iu', $item)) {
            $numericCount++;
        }
    }
    if ($numericCount === count($normalized)) {
        $sameKindBonus -= 0.08;
    } elseif ($numericCount >= 2) {
        $sameKindBonus -= 0.03;
    } else {
        $sameKindBonus += 0.04;
    }

    $avgSimilarity = $pairs > 0 ? ($similaritySum / $pairs) : 0.0;
    return ($avgSimilarity * 0.35) + $sameKindBonus;
}

function typeComplexity(array $domanda): float
{
    $tipo = strtoupper((string) ($domanda['tipo_domanda'] ?? 'CLASSIC'));
    $fase = strtolower((string) ($domanda['fase_domanda'] ?? 'domanda'));
    $audioPreview = (int) ($domanda['media_audio_preview_sec'] ?? 0);
    $image = trim((string) ($domanda['media_image_path'] ?? '')) !== '';
    $audio = trim((string) ($domanda['media_audio_path'] ?? '')) !== '';

    $score = 0.0;

    if ($tipo === 'MEDIA') {
        $score += ($image || $audio) ? 0.08 : 0.02;
    }

    if ($tipo === 'SARABANDA') {
        $score += 0.28;
        if ($fase === 'intro') $score += 0.05;
        if ($audioPreview > 0 && $audioPreview <= 5) $score += 0.08;
        if ($audioPreview > 5 && $audioPreview <= 10) $score += 0.05;
    }

    if (in_array($tipo, ['IMPOSTORE', 'MEME', 'MAJORITY', 'RANDOM_BONUS', 'BOMB', 'CHAOS', 'AUDIO_PARTY', 'IMAGE_PARTY'], true)) {
        $score += 0.18;
    }

    return $score;
}

function correctAnswerComplexity(?array $correct): float
{
    if (!$correct) return 0.0;
    $text = norm((string) ($correct['testo'] ?? ''));
    if ($text === '') return 0.0;

    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $score = 0.0;

    if (count($words) >= 2) $score += 0.03;
    if (count($words) >= 3) $score += 0.04;
    if (mb_strlen($text, 'UTF-8') >= 16) $score += 0.03;

    return $score;
}

function looksLikeArtistName(string $text): bool
{
    $text = trim($text);
    if ($text === '') return false;
    if (preg_match('/\d{3,}/', $text)) return false;
    if (preg_match('/^[0-9xivlcdm\s\.\-\(\)]+$/iu', $text)) return false;

    $tokens = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($tokens) < 1 || count($tokens) > 5) return false;

    $allowedShort = ['x', '&', 'e', 'the', 'mr.', 'mr', 'dj', 'mc'];
    foreach ($tokens as $token) {
        $tokenNorm = mb_strtolower(trim($token), 'UTF-8');
        if (in_array($tokenNorm, $allowedShort, true)) continue;
        if (mb_strlen($tokenNorm, 'UTF-8') < 2) return false;
    }

    return true;
}

function sarabandaPlausibilityBonus(array $domanda, array $options): float
{
    $tipo = strtoupper((string) ($domanda['tipo_domanda'] ?? 'CLASSIC'));
    if ($tipo !== 'SARABANDA' || count($options) === 0) return 0.0;

    $artistLike = 0;
    foreach ($options as $option) {
        if (looksLikeArtistName((string) ($option['testo'] ?? ''))) {
            $artistLike++;
        }
    }

    if ($artistLike === count($options) && count($options) >= 4) {
        return 0.18;
    }

    if ($artistLike >= 3) {
        return 0.10;
    }

    if ($artistLike >= 2) {
        return 0.04;
    }

    return 0.0;
}

$stmtDomande = $pdo->query(
    "SELECT id, testo, tipo_domanda, fase_domanda, media_image_path, media_audio_path, media_audio_preview_sec
     FROM domande
     ORDER BY id"
);
$domande = $stmtDomande->fetchAll();

$stmtOpzioni = $pdo->prepare(
    "SELECT testo, corretta
     FROM opzioni
     WHERE domanda_id = :domanda_id
     ORDER BY id"
);

$update = $pdo->prepare("UPDATE domande SET difficolta = :difficolta WHERE id = :id");

$pdo->beginTransaction();

$updated = 0;
foreach ($domande as $domanda) {
    $stmtOpzioni->execute(['domanda_id' => (int) $domanda['id']]);
    $options = $stmtOpzioni->fetchAll();
    $correct = null;
    foreach ($options as $option) {
        if ((int) ($option['corretta'] ?? 0) === 1) {
            $correct = $option;
            break;
        }
    }

    $score = 1.0;
    $score += lexicalComplexity((string) $domanda['testo']);
    $score += optionDiscrimination($options);
    $score += typeComplexity($domanda);
    $score += correctAnswerComplexity($correct);
    $score += sarabandaPlausibilityBonus($domanda, $options);

    $difficulty = clampDifficulty($score);
    $update->execute([
        'difficolta' => $difficulty,
        'id' => (int) $domanda['id'],
    ]);
    $updated++;
}

$pdo->commit();

echo "UPDATED=" . $updated . PHP_EOL;
$stats = $pdo->query("SELECT MIN(difficolta) AS min_d, MAX(difficolta) AS max_d, ROUND(AVG(difficolta), 3) AS avg_d FROM domande")->fetch();
echo json_encode($stats, JSON_UNESCAPED_UNICODE) . PHP_EOL;
