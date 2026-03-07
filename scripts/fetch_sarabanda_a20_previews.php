<?php
declare(strict_types=1);

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=Sql1874742_4;charset=utf8mb4',
    'root',
    '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$entries = [
    ['slug' => 'tran_tran', 'artist' => 'Sfera Ebbasta', 'title' => 'Tran Tran'],
    ['slug' => 'cupido', 'artist' => 'Sfera Ebbasta', 'title' => 'Cupido'],
    ['slug' => 'blun7_a_swishland', 'artist' => 'thasup', 'title' => 'blun7 a swishland'],
    ['slug' => 'siri', 'artist' => 'thasup', 'title' => 's!r!'],
    ['slug' => 'auto_blu', 'artist' => 'Shiva', 'title' => 'Auto Blu'],
    ['slug' => 'soldi_puliti', 'artist' => 'Shiva', 'title' => 'Soldi Puliti'],
    ['slug' => 'tesla', 'artist' => 'Capo Plaza', 'title' => 'Tesla'],
    ['slug' => 'giovane_fuoriclasse', 'artist' => 'Capo Plaza', 'title' => 'Giovane Fuoriclasse'],
    ['slug' => 'money', 'artist' => 'ANNA', 'title' => 'Money'],
    ['slug' => '30c', 'artist' => 'ANNA', 'title' => '30 C'],
    ['slug' => 'vetri_neri', 'artist' => 'AVA ANNA Capo Plaza', 'title' => 'Vetri Neri'],
    ['slug' => 'istinto_animale', 'artist' => 'Madame', 'title' => 'Istinto Animale'],
    ['slug' => 'tu_mi_hai_capito', 'artist' => 'Madame e Sfera Ebbasta', 'title' => 'Tu mi hai capito'],
    ['slug' => 'click_boom', 'artist' => 'Rose Villain', 'title' => 'Click Boom'],
    ['slug' => 'io_me_ed_altri_guai', 'artist' => 'Rose Villain', 'title' => 'Io Me Ed Altri Guai'],
    ['slug' => 'fiori_rosa_fiori_di_pesco', 'artist' => 'Rose Villain e Gue', 'title' => 'Fiori Rosa Fiori Di Pesco'],
    ['slug' => 'chiagne', 'artist' => 'Geolier Lazza Takagi Ketra', 'title' => 'Chiagne'],
    ['slug' => 'i_p_me_tu_p_te', 'artist' => 'Geolier', 'title' => "I P Me Tu P Te"],
    ['slug' => 'episodio_damore', 'artist' => 'Geolier', 'title' => "Episodio D'Amore"],
    ['slug' => 'gangsta_love', 'artist' => 'Geolier Sfera Ebbasta', 'title' => 'Gangsta Love'],
    ['slug' => 'cenere', 'artist' => 'Lazza', 'title' => 'Cenere'],
    ['slug' => 'panico', 'artist' => 'Lazza', 'title' => 'Panico'],
    ['slug' => 'hot', 'artist' => 'Lazza Tedua', 'title' => 'Hot'],
    ['slug' => 'hoe', 'artist' => 'Tedua Sfera Ebbasta', 'title' => 'Hoe'],
    ['slug' => 'vertigini', 'artist' => 'Tedua', 'title' => 'Vertigini'],
    ['slug' => 'mi_fidero', 'artist' => 'Blanco Madame', 'title' => 'Mi Fidero'],
    ['slug' => 'brividi', 'artist' => 'Mahmood Blanco', 'title' => 'Brividi'],
    ['slug' => 'dorado', 'artist' => 'Mahmood Sfera Ebbasta Feid', 'title' => 'Dorado'],
    ['slug' => 'tuta_gold', 'artist' => 'Mahmood', 'title' => 'Tuta Gold'],
    ['slug' => 'soldi', 'artist' => 'Mahmood', 'title' => 'Soldi'],
    ['slug' => 'lacri_mie', 'artist' => 'Marracash Sfera Ebbasta Gue', 'title' => 'Lacrime'],
    ['slug' => 'crazy_love', 'artist' => 'Marracash Elodie', 'title' => 'Crazy Love'],
    ['slug' => 'niente_canzoni_damore', 'artist' => 'Marracash Federica Abbate Coez', 'title' => 'Niente Canzoni D Amore'],
    ['slug' => 'io_tu_noi_loro', 'artist' => 'Marracash', 'title' => 'Io'],
    ['slug' => 'casa_mia', 'artist' => 'Ghali', 'title' => 'Casa Mia'],
    ['slug' => 'paprika', 'artist' => 'Ghali', 'title' => 'Paprika'],
    ['slug' => 'good_times', 'artist' => 'Ghali', 'title' => 'Good Times'],
    ['slug' => 'petrolio', 'artist' => 'Rkomi', 'title' => 'Petrolio'],
    ['slug' => 'insuperabile', 'artist' => 'Rkomi', 'title' => 'Insuperabile'],
    ['slug' => 'taxi_sulla_luna', 'artist' => 'Rkomi Sfera Ebbasta', 'title' => 'Taxi Sulla Luna'],
    ['slug' => 'pazzo_di_te', 'artist' => 'Rkomi Elodie', 'title' => 'Pazzo Di Te'],
    ['slug' => 'paranoia', 'artist' => 'Artie 5ive', 'title' => 'Paranoia'],
    ['slug' => 'milano_testarossa', 'artist' => 'Artie 5ive Gue', 'title' => 'Milano Testarossa'],
    ['slug' => 'extasy', 'artist' => 'Tony Effe Gaia', 'title' => 'Sesso E Samba'],
    ['slug' => 'taxi_sulla_luna_tonyeffe', 'artist' => 'Tony Effe Emma', 'title' => 'Taxi Sulla Luna'],
    ['slug' => 'mione', 'artist' => 'Tony Boy', 'title' => 'Mione'],
    ['slug' => 'fortuna', 'artist' => 'Tony Boy', 'title' => 'Fortuna'],
    ['slug' => 'ilva', 'artist' => 'Kid Yugi', 'title' => 'Ilva'],
    ['slug' => 'taf_taf', 'artist' => 'Simba La Rue', 'title' => 'Taf Taf'],
    ['slug' => 'stavo_pensando_a_te', 'artist' => 'Fabri Fibra', 'title' => 'Stavo Pensando A Te'],
];

function norm(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = preg_replace('/[^a-z0-9]+/i', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
    return $value;
}

function scoreResult(array $entry, array $result): int
{
    $artistNeedle = explode(' ', norm($entry['artist']));
    $titleNeedle = explode(' ', norm($entry['title']));
    $artist = norm((string) ($result['artistName'] ?? ''));
    $track = norm((string) ($result['trackName'] ?? ''));
    $score = 0;

    foreach ($artistNeedle as $token) {
        if ($token !== '' && strlen($token) >= 3 && str_contains($artist, $token)) {
            $score += 5;
        }
    }

    foreach ($titleNeedle as $token) {
        if ($token !== '' && strlen($token) >= 3 && str_contains($track, $token)) {
            $score += 7;
        }
    }

    return $score;
}

$updateDomanda = $pdo->prepare("UPDATE domande SET media_audio_path = :path, media_audio_preview_sec = 5 WHERE id = :id");
$outDir = __DIR__ . '/../public/upload/domanda/audio';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

$domandaIds = $pdo->query("SELECT id FROM domande WHERE codice_domanda LIKE 'SRB-A020-%' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
if (count($domandaIds) !== count($entries)) {
    throw new RuntimeException('Le domande SRB-A020 nel DB non corrispondono al catalogo previsto.');
}

$report = [];
$updated = 0;

foreach ($entries as $index => $entry) {
    $domandaId = (int) $domandaIds[$index];

    $term = rawurlencode($entry['artist'] . ' ' . $entry['title']);
    $json = @file_get_contents("https://itunes.apple.com/search?term={$term}&entity=song&limit=10&country=IT");
    if (!is_string($json) || trim($json) === '') {
        $report[] = [$entry['slug'], $domandaId, 'lookup_failed'];
        continue;
    }

    $decoded = json_decode($json, true);
    $results = is_array($decoded['results'] ?? null) ? $decoded['results'] : [];
    usort($results, static function (array $a, array $b) use ($entry): int {
        return scoreResult($entry, $b) <=> scoreResult($entry, $a);
    });

    $best = $results[0] ?? null;
    $score = is_array($best) ? scoreResult($entry, $best) : 0;
    $previewUrl = is_array($best) ? (string) ($best['previewUrl'] ?? '') : '';

    if ($score < 10 || $previewUrl === '') {
        $report[] = [$entry['slug'], $domandaId, 'no_strong_match'];
        continue;
    }

    $filename = sprintf('srb_%d_%s_itn.m4a', $domandaId, $entry['slug']);
    $targetPath = $outDir . '/' . $filename;
    $audio = @file_get_contents($previewUrl);
    if (!is_string($audio) || $audio === '') {
        $report[] = [$entry['slug'], $domandaId, 'download_failed'];
        continue;
    }

    file_put_contents($targetPath, $audio);
    $updateDomanda->execute([
        'path' => '/upload/domanda/audio/' . $filename,
        'id' => $domandaId,
    ]);
    $updated++;
    $report[] = [$entry['slug'], $domandaId, 'updated'];
}

echo 'UPDATED=' . $updated . PHP_EOL;
foreach ($report as $row) {
    echo implode("\t", $row) . PHP_EOL;
}


