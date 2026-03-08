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
        'nel', 'nella', 'nelle', 'negli', 'nello', 'che', 'era', 'furono', 'fu', 'il', 'la', 'lo',
        'i', 'gli', 'le', 'di', 'a', 'da', 'per', 'con', 'in', 'su', 'tra', 'fra', 'si', 'chiamava',
        'anno', 'quella', 'quello', 'questo', 'questa'
    ];

    $out = [];
    foreach ($tokens as $token) {
        if (in_array($token, $stopwords, true)) {
            continue;
        }
        if (strlen($token) < 3) {
            continue;
        }
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
    if (count($union) === 0) {
        return 0.0;
    }
    return count($intersection) / count($union);
}

function fingerprint(array $q): string
{
    $chunks = [];
    $chunks[] = 't:' . norm((string) $q['testo']);
    $chunks[] = 'a:1';
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
    $slug = norm($value);
    $slug = str_replace(' ', '_', $slug);
    return trim($slug, '_');
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
    if (!is_string($raw) || $raw === '') {
        return null;
    }

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
    ['testo' => "Quale evento del 1453 segno tradizionalmente la fine dell'Impero bizantino?", 'opzioni' => ['Caduta di Costantinopoli', 'Battaglia di Lepanto', 'Scisma d Oriente', 'Concilio di Nicea'], 'corretta' => 0, 'image_page' => 'Fall_of_Constantinople'],
    ['testo' => 'Chi completo la prima circumnavigazione del globo dopo la morte di Magellano?', 'opzioni' => ['Juan Sebastian Elcano', 'Francis Drake', 'Amerigo Vespucci', 'Vasco da Gama'], 'corretta' => 0, 'image_page' => 'Juan_Sebastián_Elcano'],
    ['testo' => 'Quale provvedimento aboli la schiavitu negli Stati Uniti nel 1865?', 'opzioni' => ['Tredicesimo emendamento', 'Proclama di Monroe', 'Compromesso del Missouri', 'Dottrina Truman'], 'corretta' => 0, 'image_page' => 'Thirteenth_Amendment_to_the_United_States_Constitution'],
    ['testo' => 'Quale leader sudafricano fu liberato nel 1990 dopo 27 anni di prigionia?', 'opzioni' => ['Nelson Mandela', 'Desmond Tutu', 'Frederik de Klerk', 'Thabo Mbeki'], 'corretta' => 0, 'image_page' => 'Nelson_Mandela'],
    ['testo' => 'Quale accordo del 1978 apri la strada alla pace tra Egitto e Israele?', 'opzioni' => ['Accordi di Camp David', 'Accordi di Oslo', 'Trattato di Suez', 'Conferenza di Bandung'], 'corretta' => 0, 'image_page' => 'Camp_David_Accords'],
    ['testo' => "In quale anno si dissolse ufficialmente l'Unione Sovietica?", 'opzioni' => ['1991', '1989', '1993', '1987'], 'corretta' => 0, 'image_page' => 'Dissolution_of_the_Soviet_Union'],
    ['testo' => 'Quale leader francese vendette la Louisiana agli Stati Uniti nel 1803?', 'opzioni' => ['Napoleone Bonaparte', 'Luigi XVI', 'Talleyrand', 'Robespierre'], 'corretta' => 0, 'image_page' => 'Louisiana_Purchase'],
    ['testo' => 'In quale citta si svolsero i processi ai gerarchi nazisti dopo la Seconda guerra mondiale?', 'opzioni' => ['Norimberga', 'Berlino', 'Monaco', 'Vienna'], 'corretta' => 0, 'image_page' => 'Nuremberg_trials'],
    ['testo' => 'Chi guido la Lunga Marcia del Partito comunista cinese?', 'opzioni' => ['Mao Zedong', 'Sun Yat-sen', 'Chiang Kai-shek', 'Deng Xiaoping'], 'corretta' => 0, 'image_page' => 'Long_March'],
    ['testo' => "Quale sovrana britannica diede il nome all'eta vittoriana?", 'opzioni' => ['Regina Vittoria', 'Elisabetta I', 'Anna Stuart', 'Maria II'], 'corretta' => 0, 'image_page' => 'Queen_Victoria'],
    ['testo' => 'In quale anno crollo Wall Street dando inizio alla Grande Depressione?', 'opzioni' => ['1929', '1919', '1933', '1925'], 'corretta' => 0, 'image_page' => 'Wall_Street_Crash_of_1929'],
    ['testo' => 'Chi raggiunse per primo il Polo Sud nel 1911?', 'opzioni' => ['Roald Amundsen', 'Robert Falcon Scott', 'Ernest Shackleton', 'Fridtjof Nansen'], 'corretta' => 0, 'image_page' => 'Roald_Amundsen'],
    ['testo' => "Quale accordo del 1998 contribui alla pace in Irlanda del Nord?", 'opzioni' => ['Good Friday Agreement', 'Patto di Londra', 'Accordo di Schengen', 'Trattato di Maastricht'], 'corretta' => 0, 'image_page' => 'Good_Friday_Agreement'],
    ['testo' => 'Quale zar russo fondo San Pietroburgo?', 'opzioni' => ['Pietro il Grande', 'Ivan il Terribile', 'Nicola I', 'Alessandro I'], 'corretta' => 0, 'image_page' => 'Peter_the_Great'],
    ['testo' => "Quale rivoluzione del 1911 pose fine all'impero cinese?", 'opzioni' => ['Rivoluzione Xinhai', 'Rivoluzione culturale', 'Rivolta dei Boxer', 'Movimento del 4 maggio'], 'corretta' => 0, 'image_page' => '1911_Revolution'],
    ['testo' => 'Chi lancio il New Deal negli Stati Uniti?', 'opzioni' => ['Franklin D. Roosevelt', 'Herbert Hoover', 'Harry Truman', 'Woodrow Wilson'], 'corretta' => 0, 'image_page' => 'Franklin_D._Roosevelt'],
    ['testo' => 'Quale civilta precolombiana traccio le linee di Nazca?', 'opzioni' => ['Nazca', 'Maya', 'Olmechi', 'Toltechi'], 'corretta' => 0, 'image_page' => 'Nazca_Lines'],
    ['testo' => "In quale anno fu annunciato l'armistizio di Cassibile in Italia?", 'opzioni' => ['1943', '1944', '1942', '1945'], 'corretta' => 0, 'image_page' => 'Armistice_of_Cassibile'],
    ['testo' => 'Quale generale romano sconfisse Annibale nella battaglia di Zama?', 'opzioni' => ["Scipione l'Africano", 'Marco Antonio', 'Pompeo', 'Germanico'], 'corretta' => 0, 'image_page' => 'Battle_of_Zama'],
    ['testo' => "Quale documento del 1776 proclamo l'indipendenza delle tredici colonie?", 'opzioni' => ['Dichiarazione d indipendenza', 'Costituzione americana', 'Bill of Rights', 'Articoli della Confederazione'], 'corretta' => 0, 'image_page' => 'United_States_Declaration_of_Independence'],
    ['testo' => 'Quale zar emancipo i servi della gleba in Russia nel 1861?', 'opzioni' => ['Alessandro II', 'Nicola II', 'Pietro il Grande', 'Paolo I'], 'corretta' => 0, 'image_page' => 'Alexander_II_of_Russia'],
    ['testo' => 'Quale citta giapponese fu colpita dalla prima bomba atomica?', 'opzioni' => ['Hiroshima', 'Nagasaki', 'Tokyo', 'Osaka'], 'corretta' => 0, 'image_page' => 'Hiroshima'],
    ['testo' => 'Quale civilta aveva come capitale Tenochtitlan?', 'opzioni' => ['Azteca', 'Inca', 'Maya', 'Tolteca'], 'corretta' => 0, 'image_page' => 'Tenochtitlan'],
    ['testo' => 'Quale storico greco e ricordato come padre della storia?', 'opzioni' => ['Erodoto', 'Tucidide', 'Senofonte', 'Polibio'], 'corretta' => 0, 'image_page' => 'Herodotus'],
    ['testo' => 'In quale anno si verifico la crisi dei missili di Cuba?', 'opzioni' => ['1962', '1959', '1968', '1971'], 'corretta' => 0, 'image_page' => 'Cuban_Missile_Crisis'],
    ['testo' => 'Quale papa indisse il Concilio di Trento?', 'opzioni' => ['Paolo III', 'Giulio II', 'Pio IX', 'Leone X'], 'corretta' => 0, 'image_page' => 'Council_of_Trent'],
    ['testo' => 'Chi guido la flotta greca nella battaglia di Salamina?', 'opzioni' => ['Temistocle', 'Pericle', 'Leonida', 'Milziade'], 'corretta' => 0, 'image_page' => 'Battle_of_Salamis'],
    ['testo' => 'Quale regina inglese era figlia di Enrico VIII e Anna Bolena?', 'opzioni' => ['Elisabetta I', 'Maria Tudor', 'Vittoria', 'Anna Stuart'], 'corretta' => 0, 'image_page' => 'Elizabeth_I'],
    ['testo' => 'Quale trattato del 1957 istitui la Comunita Economica Europea?', 'opzioni' => ['Trattato di Roma', 'Trattato di Lisbona', 'Trattato di Parigi', 'Atto Unico Europeo'], 'corretta' => 0, 'image_page' => 'Treaty_of_Rome'],
    ['testo' => 'Chi guido il Vietnam del Nord durante la guerra del Vietnam?', 'opzioni' => ['Ho Chi Minh', 'Ngo Dinh Diem', 'Vo Nguyen Giap', 'Bao Dai'], 'corretta' => 0, 'image_page' => 'Ho_Chi_Minh'],
    ['testo' => 'Quale civilta costrui il palazzo di Cnosso a Creta?', 'opzioni' => ['Minoica', 'Micenea', 'Fenicia', 'Lidia'], 'corretta' => 0, 'image_page' => 'Knossos'],
    ['testo' => 'In quale anno fu completata la riunificazione tedesca?', 'opzioni' => ['1990', '1989', '1991', '1992'], 'corretta' => 0, 'image_page' => 'German_reunification'],
    ['testo' => "Quale battaglia navale del 1571 fermo l'espansione ottomana nel Mediterraneo?", 'opzioni' => ['Lepanto', 'Navarino', 'Actium', 'Trafalgar'], 'corretta' => 0, 'image_page' => 'Battle_of_Lepanto'],
    ['testo' => 'Chi consolido il potere sovietico dopo la morte di Lenin?', 'opzioni' => ['Iosif Stalin', 'Lev Trotsky', 'Nikita Krusciov', 'Michail Gorbaciov'], 'corretta' => 0, 'image_page' => 'Joseph_Stalin'],
    ['testo' => 'Quale manufatto racconta visivamente la conquista normanna del 1066?', 'opzioni' => ['Arazzo di Bayeux', 'Codice di Hammurabi', 'Colonna Traiana', 'Libro di Kells'], 'corretta' => 0, 'image_page' => 'Bayeux_Tapestry'],
    ['testo' => 'Quale citta divenne simbolo del ponte aereo del 1948-49?', 'opzioni' => ['Berlino Ovest', 'Dresda', 'Lipsia', 'Amburgo'], 'corretta' => 0, 'image_page' => 'Berlin_Blockade'],
    ['testo' => "Chi conquisto l'impero azteco per la Spagna?", 'opzioni' => ['Hernan Cortes', 'Francisco Pizarro', 'Pedro de Alvarado', 'Diego Velazquez'], 'corretta' => 0, 'image_page' => 'Hernán_Cortés'],
    ['testo' => 'Quale legislatore ateniese aboli la schiavitu per debiti?', 'opzioni' => ['Solone', 'Clistene', 'Dracone', 'Licurgo'], 'corretta' => 0, 'image_page' => 'Solon'],
    ['testo' => 'In quale anno si concluse la Guerra dei Cent anni?', 'opzioni' => ['1453', '1415', '1492', '1478'], 'corretta' => 0, 'image_page' => "Hundred_Years'_War"],
    ['testo' => "Chi fu assassinato a Sarajevo nel 1914, innescando la crisi che porto alla Prima guerra mondiale?", 'opzioni' => ['Francesco Ferdinando', 'Guglielmo II', 'Poincare', 'Franz Joseph'], 'corretta' => 0, 'image_page' => 'Archduke_Franz_Ferdinand_of_Austria'],
    ['testo' => "Quale conferenza del 1884-85 regolo la spartizione coloniale dell'Africa?", 'opzioni' => ['Conferenza di Berlino', 'Conferenza di Vienna', 'Conferenza di Yalta', 'Conferenza di Potsdam'], 'corretta' => 0, 'image_page' => 'Berlin_Conference'],
    ['testo' => 'Chi fu il primo cancelliere del Reich tedesco unificato?', 'opzioni' => ['Otto von Bismarck', 'Helmut Kohl', 'Paul von Hindenburg', 'Konrad Adenauer'], 'corretta' => 0, 'image_page' => 'Otto_von_Bismarck'],
    ['testo' => 'Quale faraone promosse il culto di Aton?', 'opzioni' => ['Akhenaton', 'Ramses II', 'Tutankhamon', 'Seti I'], 'corretta' => 0, 'image_page' => 'Akhenaten'],
    ['testo' => "Quale riforma politica del 1968 in Cecoslovacchia fu soffocata dai carri del Patto di Varsavia?", 'opzioni' => ['Primavera di Praga', 'Glasnost', 'Perestrojka', 'Rivoluzione di Velluto'], 'corretta' => 0, 'image_page' => 'Prague_Spring'],
    ['testo' => 'Chi guido la grande rivolta degli schiavi contro Roma nel I secolo a.C.?', 'opzioni' => ['Spartaco', 'Vercingetorige', 'Mitridate', 'Catilina'], 'corretta' => 0, 'image_page' => 'Spartacus'],
    ['testo' => 'Quale casata di shogun governo il Giappone durante il periodo Edo?', 'opzioni' => ['Tokugawa', 'Minamoto', 'Taira', 'Ashikaga'], 'corretta' => 0, 'image_page' => 'Tokugawa_shogunate'],
    ['testo' => 'In quale anno fu fondata l Organizzazione delle Nazioni Unite?', 'opzioni' => ['1945', '1944', '1947', '1950'], 'corretta' => 0, 'image_page' => 'United_Nations'],
    ['testo' => 'Quale regina francese fu ghigliottinata durante la Rivoluzione francese?', 'opzioni' => ['Maria Antonietta', 'Caterina de Medici', 'Anna d Austria', 'Maria Luisa'], 'corretta' => 0, 'image_page' => 'Marie_Antoinette'],
    ['testo' => 'Quale condottiero macedone fondo Alessandria d Egitto?', 'opzioni' => ['Alessandro Magno', 'Filippo II', 'Tolomeo I', 'Seleuco I'], 'corretta' => 0, 'image_page' => 'Alexander_the_Great'],
    ['testo' => 'Quale conflitto del XIX secolo oppose la Russia a Ottomani, Francia e Regno Unito?', 'opzioni' => ['Guerra di Crimea', 'Guerra dei Sette anni', 'Guerra russo-giapponese', 'Guerra boera'], 'corretta' => 0, 'image_page' => 'Crimean_War'],
];

$existingStmt = $pdo->query("SELECT id, testo, fingerprint_unico FROM domande WHERE argomento_id = 1");
$existingRows = $existingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$existingFingerprints = [];
$existingTokenMaps = [];

foreach ($existingRows as $row) {
    $fp = (string) ($row['fingerprint_unico'] ?? '');
    if ($fp !== '') {
        $existingFingerprints[$fp] = true;
    }
    $existingTokenMaps[(int) $row['id']] = tokenize((string) ($row['testo'] ?? ''));
}

$insertDomandaStmt = $pdo->prepare(
    "INSERT INTO domande (
        testo, codice_domanda, fingerprint_unico, difficolta, tipo_domanda, fase_domanda,
        media_image_path, argomento_id, attiva
    ) VALUES (
        :testo, :codice_domanda, :fingerprint_unico, :difficolta, 'MEDIA', 'domanda',
        NULL, 1, 1
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
    $questionTokens = tokenize((string) $question['testo']);

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

        $domandaId = (int) $pdo->lastInsertId();
        $codice = sprintf('MED-A001-%05d', $domandaId);
        $updateCodeStmt->execute(['codice' => $codice, 'id' => $domandaId]);

        foreach ($question['opzioni'] as $index => $option) {
            $insertOpzioneStmt->execute([
                'domanda_id' => $domandaId,
                'testo' => $option,
                'corretta' => ($index === (int) $question['corretta']) ? 1 : 0,
            ]);
        }

        $summaryUrl = 'https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode((string) $question['image_page']);
        $summary = fetchJson($summaryUrl);
        $imageSource = '';
        if (is_array($summary)) {
            $imageSource = (string) ($summary['originalimage']['source'] ?? $summary['thumbnail']['source'] ?? '');
        }

        if ($imageSource !== '') {
            $binary = fetchBinary($imageSource);
            if ($binary !== null) {
                $extension = imageExtensionFromUrl($imageSource);
                $fileName = sprintf('hst_%d_%s.%s', $domandaId, slugify((string) $question['image_page']), $extension);
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
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $skipped++;
        $report[] = ['status' => 'error', 'testo' => $question['testo'], 'id' => null, 'error' => $e->getMessage()];
    }
}

echo 'INSERTED=' . $inserted . PHP_EOL;
echo 'SKIPPED=' . $skipped . PHP_EOL;
echo 'IMAGE_OK=' . $imageOk . PHP_EOL;
echo 'IMAGE_FAIL=' . $imageFail . PHP_EOL;
echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
