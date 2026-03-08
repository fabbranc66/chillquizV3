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
        'di', 'a', 'da', 'per', 'con', 'in', 'su', 'tra', 'fra', 'si', 'chiamata', 'chiamato', 'detto',
        'mare', 'montagna', 'fiume', 'lago', 'deserto', 'catena', 'stretto', 'golfo', 'paese', 'stato'
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
    $chunks[] = 'a:2';
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
    ['testo' => 'Quale e il fiume piu lungo del mondo secondo la misurazione piu usata nei quiz geografici?', 'opzioni' => ['Nilo', 'Amazzoni', 'Mississippi', 'Yangtze'], 'corretta' => 0, 'image_page' => 'Nile'],
    ['testo' => 'Quale catena montuosa separa tradizionalmente Europa e Asia nella Russia europea?', 'opzioni' => ['Urali', 'Caucaso', 'Altai', 'Carpazi'], 'corretta' => 0, 'image_page' => 'Ural_Mountains'],
    ['testo' => 'Quale e il deserto caldo piu esteso della Terra?', 'opzioni' => ['Sahara', 'Arabico', 'Kalahari', 'Thar'], 'corretta' => 0, 'image_page' => 'Sahara'],
    ['testo' => 'Quale stretto separa la Sicilia dalla Calabria?', 'opzioni' => ['Stretto di Messina', 'Stretto di Otranto', 'Canale di Sicilia', 'Stretto di Bonifacio'], 'corretta' => 0, 'image_page' => 'Strait_of_Messina'],
    ['testo' => 'Quale e il lago piu profondo del mondo?', 'opzioni' => ['Bajkal', 'Tanganica', 'Superiore', 'Vittoria'], 'corretta' => 0, 'image_page' => 'Lake_Baikal'],
    ['testo' => 'Quale oceano bagna la costa occidentale del Perù?', 'opzioni' => ['Pacifico', 'Atlantico', 'Indiano', 'Artico'], 'corretta' => 0, 'image_page' => 'Pacific_Ocean'],
    ['testo' => 'Quale monte e la vetta piu alta delle Alpi?', 'opzioni' => ['Monte Bianco', 'Cervino', 'Monte Rosa', 'Gran Paradiso'], 'corretta' => 0, 'image_page' => 'Mont_Blanc'],
    ['testo' => 'Quale e l isola piu grande del Mediterraneo?', 'opzioni' => ['Sicilia', 'Sardegna', 'Cipro', 'Corsica'], 'corretta' => 0, 'image_page' => 'Sicily'],
    ['testo' => 'Quale fiume attraversa Budapest dividendo Buda e Pest?', 'opzioni' => ['Danubio', 'Tibisco', 'Elba', 'Drava'], 'corretta' => 0, 'image_page' => 'Danube'],
    ['testo' => 'Quale e il paese piu esteso dell Africa?', 'opzioni' => ['Algeria', 'Sudan', 'Libia', 'Congo'], 'corretta' => 0, 'image_page' => 'Algeria'],
    ['testo' => 'Quale regione desertica si trova nel nord della Cina e nel sud della Mongolia?', 'opzioni' => ['Gobi', 'Taklamakan', 'Karakum', 'Negev'], 'corretta' => 0, 'image_page' => 'Gobi_Desert'],
    ['testo' => 'Quale e il golfo su cui si affaccia la città di Napoli?', 'opzioni' => ['Golfo di Napoli', 'Golfo di Salerno', 'Golfo di Gaeta', 'Golfo di Taranto'], 'corretta' => 0, 'image_page' => 'Gulf_of_Naples'],
    ['testo' => 'Quale arco montuoso attraversa la penisola scandinava tra Norvegia e Svezia?', 'opzioni' => ['Monti Scandinavi', 'Carpazi', 'Alpi Dinariche', 'Pirenei'], 'corretta' => 0, 'image_page' => 'Scandinavian_Mountains'],
    ['testo' => 'Quale mare interno separa l Europa sudorientale dall Asia Minore?', 'opzioni' => ['Mar Nero', 'Mar Baltico', 'Mar d Azov', 'Mar Caspio'], 'corretta' => 0, 'image_page' => 'Black_Sea'],
    ['testo' => 'Quale fiume sfocia nel Mar Nero formando un grande delta tra Romania e Ucraina?', 'opzioni' => ['Danubio', 'Don', 'Dnestr', 'Volga'], 'corretta' => 0, 'image_page' => 'Danube_Delta'],
    ['testo' => 'Quale e la piu grande isola del mondo non considerata continente?', 'opzioni' => ['Groenlandia', 'Nuova Guinea', 'Borneo', 'Madagascar'], 'corretta' => 0, 'image_page' => 'Greenland'],
    ['testo' => 'Quale e il monte piu alto del continente africano?', 'opzioni' => ['Kilimangiaro', 'Monte Kenya', 'Ruwenzori', 'Atlante'], 'corretta' => 0, 'image_page' => 'Mount_Kilimanjaro'],
    ['testo' => 'Quale e il principale arcipelago vulcanico spagnolo nell Atlantico?', 'opzioni' => ['Canarie', 'Baleari', 'Azzorre', 'Madeira'], 'corretta' => 0, 'image_page' => 'Canary_Islands'],
    ['testo' => 'Quale stretto separa l Alaska dalla Siberia?', 'opzioni' => ['Stretto di Bering', 'Stretto di Drake', 'Stretto di Davis', 'Stretto di Magellano'], 'corretta' => 0, 'image_page' => 'Bering_Strait'],
    ['testo' => 'Quale e il fiume principale che attraversa l Egitto da sud a nord?', 'opzioni' => ['Nilo', 'Congo', 'Zambesi', 'Orange'], 'corretta' => 0, 'image_page' => 'Nile'],
    ['testo' => 'Quale paese ospita la maggior parte della foresta amazzonica?', 'opzioni' => ['Brasile', 'Perù', 'Colombia', 'Bolivia'], 'corretta' => 0, 'image_page' => 'Amazon_rainforest'],
    ['testo' => 'Quale e la penisola su cui si trovano Norvegia e Svezia?', 'opzioni' => ['Scandinava', 'Iberica', 'Balcanica', 'Jutland'], 'corretta' => 0, 'image_page' => 'Scandinavian_Peninsula'],
    ['testo' => 'Quale e il lago africano piu grande per superficie?', 'opzioni' => ['Lago Vittoria', 'Tanganica', 'Malawi', 'Turkana'], 'corretta' => 0, 'image_page' => 'Lake_Victoria'],
    ['testo' => 'Quale monte domina la città di Tokyo nelle giornate limpide?', 'opzioni' => ['Fuji', 'Aso', 'Ontake', 'Haku'], 'corretta' => 0, 'image_page' => 'Mount_Fuji'],
    ['testo' => 'Quale e il fiume piu lungo della penisola italiana?', 'opzioni' => ['Po', 'Adige', 'Tevere', 'Arno'], 'corretta' => 0, 'image_page' => 'Po_(river)'],
    ['testo' => 'Quale paese e interamente circondato dal territorio del Sudafrica?', 'opzioni' => ['Lesotho', 'Eswatini', 'Botswana', 'Namibia'], 'corretta' => 0, 'image_page' => 'Lesotho'],
    ['testo' => 'Quale mare bagna sia Israele sia la Giordania pur essendo in realtà un lago salato?', 'opzioni' => ['Mar Morto', 'Mar Rosso', 'Mar Nero', 'Mar Caspio'], 'corretta' => 0, 'image_page' => 'Dead_Sea'],
    ['testo' => 'Quale canale collega il Mediterraneo al Mar Rosso?', 'opzioni' => ['Canale di Suez', 'Canale di Panama', 'Canale di Corinto', 'Canale di Kiel'], 'corretta' => 0, 'image_page' => 'Suez_Canal'],
    ['testo' => 'Quale e la capitale amministrativa del Sudafrica tra queste opzioni?', 'opzioni' => ['Pretoria', 'Johannesburg', 'Durban', 'Bloemfontein'], 'corretta' => 0, 'image_page' => 'Pretoria'],
    ['testo' => 'Quale e il punto piu basso delle terre emerse?', 'opzioni' => ['Riva del Mar Morto', 'Valle della Morte', 'Depressione del Qattara', 'Fossa di Afar'], 'corretta' => 0, 'image_page' => 'Dead_Sea'],
    ['testo' => 'Quale grande barriera corallina si trova al largo della costa nordorientale australiana?', 'opzioni' => ['Grande Barriera Corallina', 'Barriera del Belize', 'Barriera delle Maldive', 'Barriera di Palau'], 'corretta' => 0, 'image_page' => 'Great_Barrier_Reef'],
    ['testo' => 'Quale mare separa la penisola arabica dal Corno d Africa?', 'opzioni' => ['Mar Rosso', 'Mar Arabico', 'Golfo Persico', 'Mar Caspio'], 'corretta' => 0, 'image_page' => 'Red_Sea'],
    ['testo' => 'Quale e il più grande lago del Nord America per superficie?', 'opzioni' => ['Lago Superiore', 'Lago Michigan', 'Lago Huron', 'Lago Erie'], 'corretta' => 0, 'image_page' => 'Lake_Superior'],
    ['testo' => 'Quale catena montuosa attraversa il Sud America lungo il Pacifico?', 'opzioni' => ['Ande', 'Rocciose', 'Appalachi', 'Drakensberg'], 'corretta' => 0, 'image_page' => 'Andes'],
    ['testo' => 'Quale stretto separa la Corsica dalla Sardegna?', 'opzioni' => ['Stretto di Bonifacio', 'Stretto di Otranto', 'Stretto di Gibilterra', 'Canale di Corsica'], 'corretta' => 0, 'image_page' => 'Strait_of_Bonifacio'],
    ['testo' => 'Quale fiume attraversa Parigi?', 'opzioni' => ['Senna', 'Loira', 'Rodano', 'Garonna'], 'corretta' => 0, 'image_page' => 'Seine'],
    ['testo' => 'Quale paese possiede la maggior parte del deserto del Kalahari?', 'opzioni' => ['Botswana', 'Namibia', 'Sudafrica', 'Angola'], 'corretta' => 0, 'image_page' => 'Kalahari_Desert'],
    ['testo' => 'Quale e l isola principale del Giappone dove si trovano Tokyo e Kyoto?', 'opzioni' => ['Honshu', 'Hokkaido', 'Kyushu', 'Shikoku'], 'corretta' => 0, 'image_page' => 'Honshu'],
    ['testo' => 'Quale e il principale fiume che attraversa Baghdad?', 'opzioni' => ['Tigri', 'Eufrate', 'Giordano', 'Kura'], 'corretta' => 0, 'image_page' => 'Tigris'],
    ['testo' => 'Quale paese africano e attraversato sia dall Equatore sia dal meridiano di Greenwich?', 'opzioni' => ['Gabon', 'Congo', 'Kenya', 'Ghana'], 'corretta' => 0, 'image_page' => 'Gabon'],
    ['testo' => 'Quale e il piu grande arcipelago del mondo per numero di isole?', 'opzioni' => ['Indonesia', 'Filippine', 'Giappone', 'Grecia'], 'corretta' => 0, 'image_page' => 'Indonesia'],
    ['testo' => 'Quale e il nome della regione polare che circonda il Polo Nord?', 'opzioni' => ['Artico', 'Antartide', 'Patagonia', 'Lapponia'], 'corretta' => 0, 'image_page' => 'Arctic'],
    ['testo' => 'Quale paese europeo confina sia con il Mar Baltico sia con il Mare del Nord?', 'opzioni' => ['Danimarca', 'Germania', 'Svezia', 'Polonia'], 'corretta' => 0, 'image_page' => 'Denmark'],
    ['testo' => 'Quale e il mare tra Italia e penisola balcanica?', 'opzioni' => ['Adriatico', 'Ionio', 'Tirreno', 'Egeo'], 'corretta' => 0, 'image_page' => 'Adriatic_Sea'],
    ['testo' => 'Quale e il vulcano attivo piu alto d Europa?', 'opzioni' => ['Etna', 'Vesuvio', 'Stromboli', 'Teide'], 'corretta' => 0, 'image_page' => 'Mount_Etna'],
    ['testo' => 'Quale e il principale altopiano desertico del Cile settentrionale?', 'opzioni' => ['Atacama', 'Altiplano', 'Patagonia', 'Puna'], 'corretta' => 0, 'image_page' => 'Atacama_Desert'],
    ['testo' => 'Quale e il fiume piu lungo della penisola iberica?', 'opzioni' => ['Tago', 'Ebro', 'Duero', 'Guadalquivir'], 'corretta' => 0, 'image_page' => 'Tagus'],
    ['testo' => 'Quale e la più grande isola delle Baleari?', 'opzioni' => ['Maiorca', 'Minorca', 'Ibiza', 'Formentera'], 'corretta' => 0, 'image_page' => 'Mallorca'],
    ['testo' => 'Quale mare bagna le coste della Croazia?', 'opzioni' => ['Adriatico', 'Ionio', 'Egeo', 'Nero'], 'corretta' => 0, 'image_page' => 'Adriatic_Sea'],
    ['testo' => 'Quale e il paese più popoloso del Sud America?', 'opzioni' => ['Brasile', 'Argentina', 'Colombia', 'Perù'], 'corretta' => 0, 'image_page' => 'Brazil'],
    ['testo' => 'Quale e il principale fiume del Pakistan che sfocia nel Mar Arabico?', 'opzioni' => ['Indo', 'Gange', 'Brahmaputra', 'Irrawaddy'], 'corretta' => 0, 'image_page' => 'Indus_River'],
];

$existingStmt = $pdo->query("SELECT id, testo, fingerprint_unico FROM domande WHERE argomento_id = 2");
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
        NULL, 2, 1
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
            $report[] = ['status' => 'skip-token', 'testo' => $question['testo'], 'id' => $existingId];
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
        $codice = sprintf('MED-A002-%05d', $domandaId);
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
                $extension = imageExtensionFromUrl($imageSource);
                $fileName = sprintf('geo_%d_%s.%s', $domandaId, slugify((string)$question['image_page']), $extension);
                $fullPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
                file_put_contents($fullPath, $binary);
                $updateImageStmt->execute([
                    'path' => '/upload/domanda/image/' . $fileName,
                    'id' => $domandaId,
                ]);
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
        $report[] = ['status' => 'inserted', 'testo' => $question['testo'], 'id' => $domandaId];

        usleep(250000);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $skipped++;
        $report[] = ['status' => 'error', 'testo' => $question['testo'], 'id' => null, 'error' => $e->getMessage()];
    }
}

echo 'INSERTED=' . $inserted . PHP_EOL;
echo 'SKIPPED=' . $skipped . PHP_EOL;
echo 'IMAGE_OK=' . $imageOk . PHP_EOL;
echo 'IMAGE_FAIL=' . $imageFail . PHP_EOL;
echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
