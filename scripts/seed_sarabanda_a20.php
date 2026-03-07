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
    ['slug' => 'tran_tran', 'artist' => 'Sfera Ebbasta', 'title' => 'Tran Tran', 'options' => ['Sfera Ebbasta', 'Shiva', 'Capo Plaza', 'Tony Effe']],
    ['slug' => 'cupido', 'artist' => 'Sfera Ebbasta', 'title' => 'Cupido', 'options' => ['Sfera Ebbasta', 'Ghali', 'Lazza', 'Marracash']],
    ['slug' => 'blun7_a_swishland', 'artist' => 'thasup', 'title' => 'blun7 a swishland', 'options' => ['thasup', 'Shiva', 'Kid Yugi', 'Tony Boy']],
    ['slug' => 'siri', 'artist' => 'thasup', 'title' => 's!r!', 'options' => ['thasup', 'Shiva', 'Lazza', 'Tedua']],
    ['slug' => 'auto_blu', 'artist' => 'Shiva', 'title' => 'Auto Blu', 'options' => ['Shiva', 'Sfera Ebbasta', 'Capo Plaza', 'Paky']],
    ['slug' => 'soldi_puliti', 'artist' => 'Shiva', 'title' => 'Soldi Puliti', 'options' => ['Shiva', 'Tony Effe', 'Paky', 'Capo Plaza']],
    ['slug' => 'tesla', 'artist' => 'Capo Plaza', 'title' => 'Tesla', 'options' => ['Capo Plaza', 'Shiva', 'Sfera Ebbasta', 'Tony Boy']],
    ['slug' => 'giovane_fuoriclasse', 'artist' => 'Capo Plaza', 'title' => 'Giovane Fuoriclasse', 'options' => ['Capo Plaza', 'Baby Gang', 'Paky', 'Shiva']],
    ['slug' => 'money', 'artist' => 'ANNA', 'title' => 'Money', 'options' => ['ANNA', 'Beba', 'Madame', 'Rose Villain']],
    ['slug' => '30c', 'artist' => 'ANNA', 'title' => '30??C', 'options' => ['ANNA', 'Madame', 'Rose Villain', 'Elodie']],
    ['slug' => 'vetri_neri', 'artist' => 'AVA, ANNA e Capo Plaza', 'title' => 'Vetri Neri', 'options' => ['AVA, ANNA e Capo Plaza', 'ANNA e AVA', 'Rose Villain e Gu?', 'Madame e Sfera Ebbasta']],
    ['slug' => 'istinto_animale', 'artist' => 'Madame', 'title' => 'ISTINTO ANIMALE', 'options' => ['Madame', 'Rose Villain', 'Beba', 'ANNA']],
    ['slug' => 'tu_mi_hai_capito', 'artist' => 'Madame e Sfera Ebbasta', 'title' => 'Tu mi hai capito', 'options' => ['Madame e Sfera Ebbasta', 'Madame', 'ANNA e Lazza', 'Rose Villain e Gu??']],
    ['slug' => 'click_boom', 'artist' => 'Rose Villain', 'title' => 'CLICK BOOM!', 'options' => ['Rose Villain', 'Elodie', 'Madame', 'Annalisa']],
    ['slug' => 'io_me_ed_altri_guai', 'artist' => 'Rose Villain', 'title' => 'Io, me ed altri guai', 'options' => ['Rose Villain', 'Madame', 'Elodie', 'ANNA']],
    ['slug' => 'fiori_rosa_fiori_di_pesco', 'artist' => 'Rose Villain e Gu??', 'title' => 'Fiori rosa, fiori di pesco', 'options' => ['Rose Villain e Gu??', 'Rose Villain', 'Madame e Sfera Ebbasta', 'ANNA e AVA']],
    ['slug' => 'chiagne', 'artist' => 'Geolier, Lazza e Takagi & Ketra', 'title' => 'Chiagne', 'options' => ['Geolier, Lazza e Takagi & Ketra', 'Geolier', 'Lazza', 'Gu?? e Marracash']],
    ['slug' => 'i_p_me_tu_p_te', 'artist' => 'Geolier', 'title' => "I p' me, tu p' te", 'options' => ['Geolier', 'Lazza', 'Shiva', 'Tony Effe']],
    ['slug' => 'episodio_damore', 'artist' => 'Geolier', 'title' => "Episodio d'amore", 'options' => ['Geolier', 'Shiva', 'Tedua', 'Capo Plaza']],
    ['slug' => 'gangsta_love', 'artist' => 'Geolier e Sfera Ebbasta', 'title' => 'GANGSTA LOVE', 'options' => ['Geolier e Sfera Ebbasta', 'Geolier', 'Sfera Ebbasta', 'Shiva e Tedua']],
    ['slug' => 'cenere', 'artist' => 'Lazza', 'title' => 'Cenere', 'options' => ['Lazza', 'Geolier', 'Shiva', 'Tony Effe']],
    ['slug' => 'panico', 'artist' => 'Lazza', 'title' => 'Panico', 'options' => ['Lazza', 'Tedua', 'Shiva', 'Capo Plaza']],
    ['slug' => 'hot', 'artist' => 'Lazza e Tedua', 'title' => 'Hot', 'options' => ['Lazza e Tedua', 'Lazza', 'Tedua', 'Shiva e Sfera Ebbasta']],
    ['slug' => 'hoe', 'artist' => 'Tedua e Sfera Ebbasta', 'title' => 'Hoe', 'options' => ['Tedua e Sfera Ebbasta', 'Tedua', 'Sfera Ebbasta', 'Geolier e Shiva']],
    ['slug' => 'vertigini', 'artist' => 'Tedua', 'title' => 'Vertigini', 'options' => ['Tedua', 'Shiva', 'Lazza', 'Rkomi']],
    ['slug' => 'mi_fidero', 'artist' => 'Blanco e Madame', 'title' => 'Mi Fidero', 'options' => ['Blanco e Madame', 'Blanco', 'Madame', 'Mahmood e Blanco']],
    ['slug' => 'brividi', 'artist' => 'Mahmood e Blanco', 'title' => 'Brividi', 'options' => ['Mahmood e Blanco', 'Mahmood', 'Blanco', 'Mr.Rain e Lazza']],
    ['slug' => 'dorado', 'artist' => 'Mahmood, Sfera Ebbasta e Feid', 'title' => 'Dorado', 'options' => ['Mahmood, Sfera Ebbasta e Feid', 'Mahmood e Blanco', 'Sfera Ebbasta e Shiva', 'Lazza e Geolier']],
    ['slug' => 'tuta_gold', 'artist' => 'Mahmood', 'title' => 'Tuta Gold', 'options' => ['Mahmood', 'Lazza', 'Blanco', 'Geolier']],
    ['slug' => 'soldi', 'artist' => 'Mahmood', 'title' => 'Soldi', 'options' => ['Mahmood', 'Blanco', 'Marracash', 'Rkomi']],
    ['slug' => 'lacri_mie', 'artist' => 'Marracash, Sfera Ebbasta e Gu??', 'title' => 'Lacrime', 'options' => ['Marracash, Sfera Ebbasta e Gu??', 'Marracash e Gu??', 'Gu?? e Lazza', 'Sfera Ebbasta e Shiva']],
    ['slug' => 'crazy_love', 'artist' => 'Marracash e Elodie', 'title' => 'Crazy Love', 'options' => ['Marracash e Elodie', 'Marracash', 'Elodie', 'Gu?? e Rose Villain']],
    ['slug' => 'niente_canzoni_damore', 'artist' => 'Marracash, Federica Abbate e Coez', 'title' => "Niente Canzoni d'Amore", 'options' => ['Marracash, Federica Abbate e Coez', 'Marracash e Gu??', 'Fedez, Achille Lauro e Tedua', 'Coez e Frah Quintale']],
    ['slug' => 'io_tu_noi_loro', 'artist' => 'Marracash', 'title' => 'Io', 'options' => ['Marracash', 'Gu??', 'Fabri Fibra', 'Salmo']],
    ['slug' => 'casa_mia', 'artist' => 'Ghali', 'title' => 'Casa Mia', 'options' => ['Ghali', 'Mahmood', 'Marracash', 'Rkomi']],
    ['slug' => 'paprika', 'artist' => 'Ghali', 'title' => 'Paprika', 'options' => ['Ghali', 'Mahmood', 'Marracash', 'Rkomi']],
    ['slug' => 'good_times', 'artist' => 'Ghali', 'title' => 'Good Times', 'options' => ['Ghali', 'Sfera Ebbasta', 'Shiva', 'Capo Plaza']],
    ['slug' => 'petrolio', 'artist' => 'Rkomi', 'title' => 'Petrolio', 'options' => ['Rkomi', 'Marracash', 'Ghali', 'Irama']],
    ['slug' => 'insuperabile', 'artist' => 'Rkomi', 'title' => 'Insuperabile', 'options' => ['Rkomi', 'Irama', 'Ghali', 'Lazza']],
    ['slug' => 'taxi_sulla_luna', 'artist' => 'Rkomi e Sfera Ebbasta', 'title' => 'Taxi Sulla Luna', 'options' => ['Rkomi e Sfera Ebbasta', 'Rkomi', 'Sfera Ebbasta', 'Marracash e Gu??']],
    ['slug' => 'pazzo_di_te', 'artist' => 'Rkomi e Elodie', 'title' => 'Pazzo di te', 'options' => ['Rkomi e Elodie', 'Rkomi', 'Elodie', 'Marracash e Elodie']],
    ['slug' => 'paranoia', 'artist' => 'Artie 5ive', 'title' => 'PARANOIA', 'options' => ['Artie 5ive', 'Kid Yugi', 'Shiva', 'Tony Boy']],
    ['slug' => 'milano_testarossa', 'artist' => 'Artie 5ive e Gu??', 'title' => 'Milano Testarossa', 'options' => ['Artie 5ive e Gu??', 'Artie 5ive', 'Gu??', 'Tony Boy e Shiva']],
    ['slug' => 'extasy', 'artist' => 'Tony Effe e Gaia', 'title' => 'SESSO E SAMBA', 'options' => ['Tony Effe e Gaia', 'Tony Effe', 'Gaia', 'Shiva e ANNA']],
    ['slug' => 'taxi_sulla_luna_tonyeffe', 'artist' => 'Tony Effe e Emma', 'title' => 'Taxi sulla luna', 'options' => ['Tony Effe e Emma', 'Tony Effe e Gaia', 'Rkomi e Sfera Ebbasta', 'Tedua e Annalisa']],
    ['slug' => 'mione', 'artist' => 'Tony Boy', 'title' => 'MIONE', 'options' => ['Tony Boy', 'Artie 5ive', 'Kid Yugi', 'Shiva']],
    ['slug' => 'fortuna', 'artist' => 'Tony Boy', 'title' => 'Fortuna', 'options' => ['Tony Boy', 'Artie 5ive', 'Kid Yugi', 'Shiva']],
    ['slug' => 'ilva', 'artist' => 'Kid Yugi', 'title' => 'Ilva', 'options' => ['Kid Yugi', 'Artie 5ive', 'Tony Boy', 'Shiva']],
    ['slug' => 'taf_taf', 'artist' => 'Simba La Rue', 'title' => 'TAF TAF', 'options' => ['Simba La Rue', 'Baby Gang', 'Paky', 'Shiva']],
    ['slug' => 'stavo_pensando_a_te', 'artist' => 'Fabri Fibra', 'title' => 'Stavo pensando a te', 'options' => ['Fabri Fibra', 'Marracash', 'Salmo', 'Gu????']],
];

if (count($entries) !== 50) {
    throw new RuntimeException('Il seed deve contenere esattamente 50 domande.');
}

$insertDomanda = $pdo->prepare(
    "INSERT INTO domande (
        testo, codice_domanda, fingerprint_unico, difficolta, tipo_domanda, modalita_party, fase_domanda,
        media_image_path, media_audio_path, media_audio_preview_sec, media_caption, config_json, argomento_id, attiva
    ) VALUES (
        'Chi canta questo brano?', NULL, :fingerprint, :difficolta, 'SARABANDA', NULL, 'intro',
        '/upload/domanda/image/sarabanda_neutral.svg', NULL, 5, NULL, NULL, 20, 1
    )"
);
$insertOpzione = $pdo->prepare("INSERT INTO opzioni (domanda_id, testo, corretta) VALUES (:domanda_id, :testo, :corretta)");
$updateCodice = $pdo->prepare("UPDATE domande SET codice_domanda = :codice WHERE id = :id");
$checkFingerprint = $pdo->prepare("SELECT id FROM domande WHERE fingerprint_unico = :fingerprint LIMIT 1");

$pdo->beginTransaction();
$created = 0;
$skipped = 0;

foreach ($entries as $entry) {
    $fingerprint = sha1('srb|a20|' . $entry['slug'] . '|' . $entry['artist']);
    $checkFingerprint->execute(['fingerprint' => $fingerprint]);
    $existingId = (int) ($checkFingerprint->fetch()['id'] ?? 0);
    if ($existingId > 0) {
        $skipped++;
        continue;
    }

    $baseDifficulty = 1.5;
    if (str_contains($entry['artist'], ' e ') || str_contains($entry['artist'], ',')) {
        $baseDifficulty += 0.1;
    }

    $insertDomanda->execute([
        'fingerprint' => $fingerprint,
        'difficolta' => $baseDifficulty,
    ]);

    $domandaId = (int) $pdo->lastInsertId();
    foreach ($entry['options'] as $index => $optionText) {
        $insertOpzione->execute([
            'domanda_id' => $domandaId,
            'testo' => $optionText,
            'corretta' => $index === 0 ? 1 : 0,
        ]);
    }

    $updateCodice->execute([
        'codice' => sprintf('SRB-A020-%05d', $domandaId),
        'id' => $domandaId,
    ]);

    $created++;
}

$pdo->commit();

echo 'CREATED=' . $created . PHP_EOL;
echo 'SKIPPED=' . $skipped . PHP_EOL;


