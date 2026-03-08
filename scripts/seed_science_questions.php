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
    if (is_string($tr) && $tr !== '') {
        $v = $tr;
    }
    $v = preg_replace('/[^a-z0-9 ]+/i', '', $v) ?? $v;
    $v = preg_replace('/\s+/', ' ', trim($v)) ?? trim($v);
    return $v;
}

function tokenize(string $value): array
{
    $tokens = preg_split('/\s+/', norm($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $stopwords = [
        'quale', 'quali', 'chi', 'come', 'dove', 'quando', 'del', 'della', 'delle', 'degli', 'dei',
        'nel', 'nella', 'nelle', 'negli', 'nello', 'che', 'e', 'il', 'la', 'lo', 'i', 'gli', 'le',
        'di', 'a', 'da', 'per', 'con', 'in', 'su', 'tra', 'fra', 'si', 'detto', 'detta', 'chiamato',
        'chiamata', 'principale', 'questo', 'questa', 'queste', 'questi', 'scienza', 'scientifico',
        'formula', 'processo', 'sistema', 'misura', 'parte', 'partecella', 'energia', 'cellula',
        'pianeta', 'stella', 'gas', 'acido', 'base', 'forza', 'velocita'
    ];
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
    $chunks = [];
    $chunks[] = 't:' . norm((string) $q['testo']);
    $chunks[] = 'a:3';
    $chunks[] = 'y:' . norm('MEDIA');
    $ops = [];
    foreach ($q['opzioni'] as $idx => $opt) {
        $corr = ($idx === (int) $q['corretta']) ? '1' : '0';
        $ops[] = $corr . ':' . norm((string) $opt);
    }
    sort($ops, SORT_STRING);
    foreach ($ops as $o) {
        $chunks[] = 'o:' . $o;
    }
    return sha1(implode('|', $chunks));
}

function slugify(string $value): string
{
    return trim(str_replace(' ', '_', norm($value)), '_');
}

function fetchJson(string $url): ?array
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 20,
            'ignore_errors' => true,
            'header' => "User-Agent: ChillQuizSeeder/1.0\r\nAccept: application/json\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || $raw === '') return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function fetchBinary(string $url): ?string
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'ignore_errors' => true,
            'header' => "User-Agent: ChillQuizSeeder/1.0\r\nAccept: image/*,*/*;q=0.8\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    return is_string($raw) && $raw !== '' ? $raw : null;
}

function imageExtensionFromUrl(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH);
    $ext = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return $ext === 'jpeg' ? 'jpg' : $ext;
    }
    return 'jpg';
}

$questions = [
    ['testo' => 'Quale scienziato formulo la teoria della relativita generale?', 'opzioni' => ['Albert Einstein', 'Niels Bohr', 'Max Planck', 'Enrico Fermi'], 'corretta' => 0, 'image_page' => 'Albert_Einstein'],
    ['testo' => 'Quale organo del corpo umano produce principalmente la bile?', 'opzioni' => ['Fegato', 'Pancreas', 'Milza', 'Rene'], 'corretta' => 0, 'image_page' => 'Liver'],
    ['testo' => 'Come si chiama la galassia in cui si trova il Sistema Solare?', 'opzioni' => ['Via Lattea', 'Andromeda', 'Triangolo', 'Sombrero'], 'corretta' => 0, 'image_page' => 'Milky_Way'],
    ['testo' => 'Quale roccia metamorfica deriva comunemente dal calcare?', 'opzioni' => ['Marmo', 'Ardesia', 'Gneiss', 'Quarzite'], 'corretta' => 0, 'image_page' => 'Marble'],
    ['testo' => 'Quale e la scala piu usata per misurare la magnitudo dei terremoti moderni?', 'opzioni' => ['Magnitudo momento', 'Scala Beaufort', 'Scala Mohs', 'Scala Fujita'], 'corretta' => 0, 'image_page' => 'Moment_magnitude_scale'],
    ['testo' => 'Quale molecola trasporta l informazione genetica dagli introni agli esoni dopo lo splicing?', 'opzioni' => ['mRNA maturo', 'tRNA', 'rRNA', 'ATP'], 'corretta' => 0, 'image_page' => 'Messenger_RNA'],
    ['testo' => 'Quale pianeta possiede il sistema di anelli piu esteso e visibile?', 'opzioni' => ['Saturno', 'Giove', 'Urano', 'Nettuno'], 'corretta' => 0, 'image_page' => 'Saturn'],
    ['testo' => 'Quale particella elementare media l interazione elettromagnetica?', 'opzioni' => ['Fotone', 'Gluone', 'Bosone W', 'Neutrino'], 'corretta' => 0, 'image_page' => 'Photon'],
    ['testo' => 'Quale processo cellulare produce due cellule figlie geneticamente identiche?', 'opzioni' => ['Mitosi', 'Meiosi', 'Fertilizzazione', 'Mutazione'], 'corretta' => 0, 'image_page' => 'Mitosis'],
    ['testo' => 'Quale strato dell atmosfera contiene la maggior parte dell ozono?', 'opzioni' => ['Stratosfera', 'Troposfera', 'Mesosfera', 'Termosfera'], 'corretta' => 0, 'image_page' => 'Stratosphere'],
    ['testo' => 'Quale elemento chimico ha simbolo Na?', 'opzioni' => ['Sodio', 'Azoto', 'Nichel', 'Neon'], 'corretta' => 0, 'image_page' => 'Sodium'],
    ['testo' => 'Quale apparato del corpo umano comprende trachea e bronchi?', 'opzioni' => ['Respiratorio', 'Digerente', 'Linfatico', 'Endocrino'], 'corretta' => 0, 'image_page' => 'Respiratory_system'],
    ['testo' => 'Quale missione porto per la prima volta esseri umani sulla Luna?', 'opzioni' => ['Apollo 11', 'Apollo 13', 'Gemini 4', 'Soyuz 1'], 'corretta' => 0, 'image_page' => 'Apollo_11'],
    ['testo' => 'Quale e l unita di misura della frequenza?', 'opzioni' => ['Hertz', 'Joule', 'Pascal', 'Volt'], 'corretta' => 0, 'image_page' => 'Hertz'],
    ['testo' => 'Quale organulo vegetale contiene clorofilla?', 'opzioni' => ['Cloroplasto', 'Lisosoma', 'Centriolo', 'Ribosoma'], 'corretta' => 0, 'image_page' => 'Chloroplast'],
    ['testo' => 'Quale scienziata ha contribuito in modo decisivo alla scoperta della struttura del DNA tramite cristallografia?', 'opzioni' => ['Rosalind Franklin', 'Marie Curie', 'Dorothy Hodgkin', 'Ada Lovelace'], 'corretta' => 0, 'image_page' => 'Rosalind_Franklin'],
    ['testo' => 'Quale tipo di legame chimico unisce due atomi condividendo coppie di elettroni?', 'opzioni' => ['Covalente', 'Ionico', 'Metallico', 'Nucleare'], 'corretta' => 0, 'image_page' => 'Covalent_bond'],
    ['testo' => 'Come si chiama il punto della Terra direttamente sopra l epicentro di un terremoto?', 'opzioni' => ['Epicentro', 'Ipocentro', 'Faglia', 'Fronte'], 'corretta' => 0, 'image_page' => 'Epicenter'],
    ['testo' => 'Quale tra questi animali e un mammifero marino?', 'opzioni' => ['Delfino', 'Squalo', 'Polpo', 'Tonno'], 'corretta' => 0, 'image_page' => 'Dolphin'],
    ['testo' => 'Quale e il satellite naturale piu grande di Giove?', 'opzioni' => ['Ganimede', 'Europa', 'Io', 'Callisto'], 'corretta' => 0, 'image_page' => 'Ganymede_(moon)'],
    ['testo' => 'Quale e il nome del processo con cui i batteri si dividono in due cellule figlie?', 'opzioni' => ['Scissione binaria', 'Gemmazione', 'Mitosi', 'Sporulazione'], 'corretta' => 0, 'image_page' => 'Binary_fission'],
    ['testo' => 'Quale e il metallo liquido a temperatura ambiente?', 'opzioni' => ['Mercurio', 'Gallio', 'Bromo', 'Cesio'], 'corretta' => 0, 'image_page' => 'Mercury_(element)'],
    ['testo' => 'Quale scienziato e associato alla selezione naturale?', 'opzioni' => ['Charles Darwin', 'Gregor Mendel', 'Louis Pasteur', 'Robert Hooke'], 'corretta' => 0, 'image_page' => 'Charles_Darwin'],
    ['testo' => 'Quale parte dell occhio regola la quantita di luce che entra?', 'opzioni' => ['Iride', 'Cornea', 'Retina', 'Cristallino'], 'corretta' => 0, 'image_page' => 'Iris_(anatomy)'],
    ['testo' => 'Quale gas nobile viene usato spesso nelle insegne luminose rosse?', 'opzioni' => ['Neon', 'Argon', 'Elio', 'Kripton'], 'corretta' => 0, 'image_page' => 'Neon'],
    ['testo' => 'Come si chiama il confine esterno dell eliosfera dove il vento solare rallenta bruscamente?', 'opzioni' => ['Termination shock', 'Fotosfera', 'Eliopausa', 'Magnetopausa'], 'corretta' => 0, 'image_page' => 'Termination_shock'],
    ['testo' => 'Quale apparato del corpo umano filtra il sangue e produce urina?', 'opzioni' => ['Urinario', 'Nervoso', 'Tegumentario', 'Muscolare'], 'corretta' => 0, 'image_page' => 'Urinary_system'],
    ['testo' => 'Quale e la durezza massima nella scala di Mohs?', 'opzioni' => ['10', '8', '12', '100'], 'corretta' => 0, 'image_page' => 'Mohs_scale'],
    ['testo' => 'Quale elemento e indispensabile per la formazione dell emoglobina?', 'opzioni' => ['Ferro', 'Calcio', 'Potassio', 'Iodio'], 'corretta' => 0, 'image_page' => 'Iron'],
    ['testo' => 'Quale branca della biologia studia i funghi?', 'opzioni' => ['Micologia', 'Entomologia', 'Ittiologia', 'Citologia'], 'corretta' => 0, 'image_page' => 'Mycology'],
    ['testo' => 'Quale strumento viene usato per osservare cellule e batteri?', 'opzioni' => ['Microscopio', 'Telescopio', 'Barometro', 'Sismografo'], 'corretta' => 0, 'image_page' => 'Microscope'],
    ['testo' => 'Quale e il nome del minerale piu abbondante nella crosta terrestre continentale?', 'opzioni' => ['Feldspato', 'Diamante', 'Calcite', 'Grafite'], 'corretta' => 0, 'image_page' => 'Feldspar'],
    ['testo' => 'Quale organo del sistema immunitario matura i linfociti T?', 'opzioni' => ['Timo', 'Milza', 'Fegato', 'Pancreas'], 'corretta' => 0, 'image_page' => 'Thymus'],
    ['testo' => 'Quale e il nome dell effetto per cui l universo in espansione allunga la luce verso il rosso?', 'opzioni' => ['Redshift', 'Blueshift', 'Albedo', 'Diffrazione'], 'corretta' => 0, 'image_page' => 'Redshift'],
    ['testo' => 'Quale composto ha formula chimica CO2?', 'opzioni' => ['Diossido di carbonio', 'Monossido di carbonio', 'Ozono', 'Metano'], 'corretta' => 0, 'image_page' => 'Carbon_dioxide'],
    ['testo' => 'Quale organo pompa il sangue nel corpo umano?', 'opzioni' => ['Cuore', 'Polmone', 'Fegato', 'Milza'], 'corretta' => 0, 'image_page' => 'Heart'],
    ['testo' => 'Quale pianeta e noto per la sua grande macchia rossa?', 'opzioni' => ['Giove', 'Marte', 'Venere', 'Mercurio'], 'corretta' => 0, 'image_page' => 'Jupiter'],
    ['testo' => 'Quale scienziato isolo per primo la penicillina osservando una muffa?', 'opzioni' => ['Alexander Fleming', 'Robert Koch', 'Jonas Salk', 'Edward Jenner'], 'corretta' => 0, 'image_page' => 'Alexander_Fleming'],
    ['testo' => 'Quale e il nome del processo con cui una stella come il Sole produce energia nel nucleo?', 'opzioni' => ['Fusione nucleare', 'Fissione nucleare', 'Combustione', 'Ionizzazione'], 'corretta' => 0, 'image_page' => 'Nuclear_fusion'],
    ['testo' => 'Quale vitamina viene sintetizzata nella pelle con l esposizione al sole?', 'opzioni' => ['Vitamina D', 'Vitamina C', 'Vitamina K', 'Vitamina B12'], 'corretta' => 0, 'image_page' => 'Vitamin_D'],
    ['testo' => 'Quale scala classifica i tornado in base ai danni osservati?', 'opzioni' => ['Fujita migliorata', 'Mercalli', 'Richter', 'Saffir Simpson'], 'corretta' => 0, 'image_page' => 'Enhanced_Fujita_scale'],
    ['testo' => 'Quale e la principale fonte di energia per il clima terrestre?', 'opzioni' => ['Sole', 'Luna', 'Calore geotermico', 'Maree'], 'corretta' => 0, 'image_page' => 'Sun'],
    ['testo' => 'Quale molecola fornisce energia immediata alle cellule?', 'opzioni' => ['ATP', 'DNA', 'Collagene', 'Urea'], 'corretta' => 0, 'image_page' => 'Adenosine_triphosphate'],
    ['testo' => 'Quale scienziato e considerato il padre della genetica per gli esperimenti sui piselli?', 'opzioni' => ['Gregor Mendel', 'James Watson', 'Thomas Hunt Morgan', 'Francis Crick'], 'corretta' => 0, 'image_page' => 'Gregor_Mendel'],
    ['testo' => 'Quale e l unita di misura della pressione nel Sistema Internazionale?', 'opzioni' => ['Pascal', 'Newton', 'Watt', 'Tesla'], 'corretta' => 0, 'image_page' => 'Pascal_(unit)'],
    ['testo' => 'Quale organo del corpo umano produce insulina?', 'opzioni' => ['Pancreas', 'Stomaco', 'Milza', 'Appendice'], 'corretta' => 0, 'image_page' => 'Pancreas'],
    ['testo' => 'Quale e il nome della fascia di corpi ghiacciati oltre Nettuno che include Plutone?', 'opzioni' => ['Fascia di Kuiper', 'Nube di Oort', 'Cintura principale', 'Fascia di Van Allen'], 'corretta' => 0, 'image_page' => 'Kuiper_belt'],
    ['testo' => 'Quale tipo di onda ha bisogno di un mezzo materiale per propagarsi?', 'opzioni' => ['Onda sonora', 'Onda luminosa', 'Onda radio', 'Raggi X'], 'corretta' => 0, 'image_page' => 'Sound'],
    ['testo' => 'Quale gas e prodotto in grande quantita dai lieviti durante la fermentazione alcolica?', 'opzioni' => ['Diossido di carbonio', 'Azoto', 'Ossigeno', 'Cloro'], 'corretta' => 0, 'image_page' => 'Fermentation'],
    ['testo' => 'Quale e il nome dello strumento che registra le onde sismiche?', 'opzioni' => ['Sismografo', 'Oscilloscopio', 'Altimetro', 'Anemometro'], 'corretta' => 0, 'image_page' => 'Seismometer'],
    ['testo' => 'Quale e il principale pigmento verde coinvolto nella fotosintesi?', 'opzioni' => ['Clorofilla a', 'Emoglobina', 'Melanina', 'Cheratina'], 'corretta' => 0, 'image_page' => 'Chlorophyll_a'],
];

$existingStmt = $pdo->query("SELECT id, testo, fingerprint_unico FROM domande WHERE argomento_id = 3");
$existingRows = $existingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$existingFingerprints = [];
$existingTokenMaps = [];
foreach ($existingRows as $row) {
    $fp = (string)($row['fingerprint_unico'] ?? '');
    if ($fp !== '') $existingFingerprints[$fp] = true;
    $existingTokenMaps[(int)$row['id']] = tokenize((string)($row['testo'] ?? ''));
}

$insertDomandaStmt = $pdo->prepare(
    "INSERT INTO domande (
        testo, codice_domanda, fingerprint_unico, difficolta, tipo_domanda, fase_domanda,
        media_image_path, argomento_id, attiva
    ) VALUES (
        :testo, :codice_domanda, :fingerprint_unico, :difficolta, 'MEDIA', 'domanda',
        NULL, 3, 1
    )"
);
$updateCodeStmt = $pdo->prepare("UPDATE domande SET codice_domanda = :codice WHERE id = :id");
$updateImageStmt = $pdo->prepare("UPDATE domande SET media_image_path = :path WHERE id = :id");
$insertOpzioneStmt = $pdo->prepare("INSERT INTO opzioni (domanda_id, testo, corretta) VALUES (:domanda_id, :testo, :corretta)");

$uploadDir = BASE_PATH . '/public/upload/domanda/image';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

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
    if ($duplicateByTokens) {
        $skipped++;
        continue;
    }

    $pdo->beginTransaction();
    try {
        $insertDomandaStmt->execute([
            'testo' => $question['testo'],
            'codice_domanda' => '',
            'fingerprint_unico' => $fp,
            'difficolta' => '1.0',
        ]);

        $domandaId = (int)$pdo->lastInsertId();
        $codice = sprintf('MED-A003-%05d', $domandaId);
        $updateCodeStmt->execute(['codice' => $codice, 'id' => $domandaId]);

        foreach ($question['opzioni'] as $index => $option) {
            $insertOpzioneStmt->execute([
                'domanda_id' => $domandaId,
                'testo' => $option,
                'corretta' => ($index === (int)$question['corretta']) ? 1 : 0,
            ]);
        }

        $summaryUrl = 'https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode((string)$question['image_page']);
        $summary = fetchJson($summaryUrl);
        $imageSource = '';
        if (is_array($summary)) {
            $imageSource = (string)($summary['thumbnail']['source'] ?? $summary['originalimage']['source'] ?? '');
        }

        if ($imageSource !== '') {
            $binary = fetchBinary($imageSource);
            if ($binary !== null) {
                $ext = imageExtensionFromUrl($imageSource);
                $filename = 'sci_' . $domandaId . '_' . slugify((string)$question['image_page']) . '.' . $ext;
                $fullPath = $uploadDir . '/' . $filename;
                file_put_contents($fullPath, $binary);
                $publicPath = '/upload/domanda/image/' . $filename;
                $updateImageStmt->execute(['path' => $publicPath, 'id' => $domandaId]);
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
