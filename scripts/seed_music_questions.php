<?php

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

function fingerprint(array $q): string
{
    $chunks = [];
    $chunks[] = 't:' . norm((string) $q['testo']);
    $chunks[] = 'a:6';
    $chunks[] = 'y:' . norm('CLASSIC');
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

$questions = [
    ['testo'=>'Quale cantante e soprannominata Queen of Pop?','opzioni'=>['Madonna','Cher','Celine Dion','Shakira'],'corretta'=>0,'difficolta'=>1.1],
    ['testo'=>'Quale band ha pubblicato Clocks?','opzioni'=>['Coldplay','Keane','U2','Muse'],'corretta'=>0,'difficolta'=>1.1],
    ['testo'=>'Quale cantante e noto per Purple Rain?','opzioni'=>['Prince','Lenny Kravitz','George Michael','Seal'],'corretta'=>0,'difficolta'=>1.2],
    ['testo'=>'Quale gruppo ha inciso Sunday Bloody Sunday?','opzioni'=>['U2','The Police','The Cure','Oasis'],'corretta'=>0,'difficolta'=>1.2],
    ['testo'=>'Quale compositore ha scritto Il flauto magico?','opzioni'=>['Mozart','Haydn','Schubert','Mendelssohn'],'corretta'=>0,'difficolta'=>1.3],
    ['testo'=>'Quale artista canta Born This Way?','opzioni'=>['Lady Gaga','Katy Perry','Pink','Dua Lipa'],'corretta'=>0,'difficolta'=>1.0],
    ['testo'=>'Quale band e famosa per Nothing Else Matters?','opzioni'=>['Metallica','Nirvana','Pearl Jam','AC/DC'],'corretta'=>0,'difficolta'=>1.1],
    ['testo'=>'Quale cantante ha pubblicato Rolling in the Deep nel 2010?','opzioni'=>['Adele','Sia','Lorde','Amy Winehouse'],'corretta'=>0,'difficolta'=>1.0],
    ['testo'=>'Quale strumento e tipico del flamenco spagnolo?','opzioni'=>['Chitarra','Violino','Tromba','Clarinetto'],'corretta'=>0,'difficolta'=>1.2],
    ['testo'=>'Quale gruppo ha pubblicato Chasing Cars?','opzioni'=>['Snow Patrol','The Fray','OneRepublic','Travis'],'corretta'=>0,'difficolta'=>1.4],
    ['testo'=>'Quale cantante e autore di Thinking Out Loud?','opzioni'=>['Ed Sheeran','Sam Smith','Shawn Mendes','Lewis Capaldi'],'corretta'=>0,'difficolta'=>1.0],
    ['testo'=>'Quale band ha inciso Smoke on the Water?','opzioni'=>['Deep Purple','Led Zeppelin','Black Sabbath','The Doors'],'corretta'=>0,'difficolta'=>1.2],
    ['testo'=>'Quale compositore e autore del Barbiere di Siviglia?','opzioni'=>['Rossini','Puccini','Donizetti','Verdi'],'corretta'=>0,'difficolta'=>1.3],
    ['testo'=>'Quale cantante ha reso celebre Rehab?','opzioni'=>['Amy Winehouse','Alicia Keys','Rihanna','Beyonce'],'corretta'=>0,'difficolta'=>1.1],
    ['testo'=>'Quale band ha pubblicato Thunderstruck?','opzioni'=>['AC/DC','Scorpions','Bon Jovi','Aerosmith'],'corretta'=>0,'difficolta'=>1.1],
    ['testo'=>'Quale artista italiana canta Occidentalis Karma?','opzioni'=>['Francesco Gabbani','Nek','Tiziano Ferro','Marco Mengoni'],'corretta'=>0,'difficolta'=>1.2],
    ['testo'=>'Quale cantante ha pubblicato Toxic?','opzioni'=>['Britney Spears','Christina Aguilera','Kylie Minogue','Avril Lavigne'],'corretta'=>0,'difficolta'=>1.0],
    ['testo'=>'Quale band e famosa per Mr. Brightside?','opzioni'=>['The Killers','Arctic Monkeys','The Strokes','Kasabian'],'corretta'=>0,'difficolta'=>1.1],
    ['testo'=>'Quale compositore ha scritto la Sinfonia Dal Nuovo Mondo?','opzioni'=>['Dvorak','Brahms','Liszt','Mahler'],'corretta'=>0,'difficolta'=>1.6],
    ['testo'=>'Quale cantante canta Shake It Off?','opzioni'=>['Taylor Swift','Demi Lovato','Miley Cyrus','Selena Gomez'],'corretta'=>0,'difficolta'=>1.0],
    ['testo'=>'Quale gruppo ha inciso Wonderwall negli anni 90?','opzioni'=>['Oasis','Blur','Pulp','Suede'],'corretta'=>0,'difficolta'=>1.2],
    ['testo'=>'Quale strumento produce il suono pizzicando corde con archetto assente?','opzioni'=>['Arpa','Oboe','Tuba','Fagotto'],'corretta'=>0,'difficolta'=>1.5],
    ['testo'=>'Quale cantante ha pubblicato Roar?','opzioni'=>['Katy Perry','Ariana Grande','Sia','Lana Del Rey'],'corretta'=>0,'difficolta'=>1.0],
    ['testo'=>'Quale band ha pubblicato Numb?','opzioni'=>['Linkin Park','Sum 41','Blink-182','Green Day'],'corretta'=>0,'difficolta'=>1.1],
    ['testo'=>'Quale compositore italiano e noto per Turandot?','opzioni'=>['Puccini','Rossini','Mascagni','Leoncavallo'],'corretta'=>0,'difficolta'=>1.3],
    ['testo'=>'Quale artista canta Levitating?','opzioni'=>['Dua Lipa','Doja Cat','Halsey','Bebe Rexha'],'corretta'=>0,'difficolta'=>1.0],
    ['testo'=>'Quale gruppo ha inciso Creep?','opzioni'=>['Radiohead','Muse','Placebo','Keane'],'corretta'=>0,'difficolta'=>1.2],
    ['testo'=>'Quale cantante ha pubblicato Viva Forever con le Spice Girls?','opzioni'=>['Spice Girls','All Saints','Sugababes','Bananarama'],'corretta'=>0,'difficolta'=>1.5],
    ['testo'=>'Quale band e famosa per Californication?','opzioni'=>['Red Hot Chili Peppers','Green Day','The Offspring','No Doubt'],'corretta'=>0,'difficolta'=>1.1],
    ['testo'=>'Quale compositore ha scritto il Requiem in re minore K.626?','opzioni'=>['Mozart','Beethoven','Schumann','Satie'],'corretta'=>0,'difficolta'=>1.6],
    ['testo'=>'Quale cantante canta Set Fire to the Rain?','opzioni'=>['Adele','Ellie Goulding','Sia','Lorde'],'corretta'=>0,'difficolta'=>1.1],
    ['testo'=>'Quale gruppo ha pubblicato Every Breath You Take?','opzioni'=>['The Police','U2','Dire Straits','INXS'],'corretta'=>0,'difficolta'=>1.2],
    ['testo'=>'Quale strumento appartiene alla famiglia degli ottoni?','opzioni'=>['Trombone','Oboe','Violino','Arpa'],'corretta'=>0,'difficolta'=>1.3],
    ['testo'=>'Quale cantante ha pubblicato Billie Jean?','opzioni'=>['Michael Jackson','Prince','Stevie Wonder','Usher'],'corretta'=>0,'difficolta'=>1.0],
    ['testo'=>'Quale band ha inciso Yellow?','opzioni'=>['Coldplay','Keane','Travis','Snow Patrol'],'corretta'=>0,'difficolta'=>1.0],
    ['testo'=>'Quale cantante italiana e nota per il brano Nessun grado di separazione?','opzioni'=>['Francesca Michielin','Annalisa','Emma','Noemi'],'corretta'=>0,'difficolta'=>1.3],
    ['testo'=>'Quale compositore e autore della Sinfonia n.40 in sol minore?','opzioni'=>['Mozart','Haydn','Bruckner','Mahler'],'corretta'=>0,'difficolta'=>1.5],
    ['testo'=>'Quale band e famosa per In the End?','opzioni'=>['Linkin Park','Papa Roach','Limp Bizkit','Korn'],'corretta'=>0,'difficolta'=>1.1],
    ['testo'=>'Quale cantante ha pubblicato Single Ladies?','opzioni'=>['Beyonce','Rihanna','Alicia Keys','Jennifer Lopez'],'corretta'=>0,'difficolta'=>1.0],
    ['testo'=>'Quale gruppo ha inciso The Scientist?','opzioni'=>['Coldplay','Muse','Radiohead','Keane'],'corretta'=>0,'difficolta'=>1.1],
    ['testo'=>'Quale compositore ha scritto Peer Gynt?','opzioni'=>['Edvard Grieg','Jean Sibelius','Franz Liszt','Antonin Dvorak'],'corretta'=>0,'difficolta'=>1.7],
    ['testo'=>'Quale cantante canta All of Me?','opzioni'=>['John Legend','Sam Smith','Bruno Mars','Jason Mraz'],'corretta'=>0,'difficolta'=>1.0],
    ['testo'=>'Quale band ha pubblicato Zombie?','opzioni'=>['The Cranberries','The Corrs','No Doubt','Garbage'],'corretta'=>0,'difficolta'=>1.2],
    ['testo'=>'Quale strumento ha pedaliera e tastiere in una chiesa?','opzioni'=>['Organo','Pianoforte','Clavicembalo','Fisarmonica'],'corretta'=>0,'difficolta'=>1.3],
    ['testo'=>'Quale cantante ha pubblicato Bad Romance?','opzioni'=>['Lady Gaga','Kesha','Sia','P!nk'],'corretta'=>0,'difficolta'=>1.0],
    ['testo'=>'Quale gruppo ha inciso Basket Case?','opzioni'=>['Green Day','Blink-182','The Offspring','Sum 41'],'corretta'=>0,'difficolta'=>1.2],
    ['testo'=>'Quale cantante italiana canta Sinceramente?','opzioni'=>['Annalisa','Elodie','Arisa','Noemi'],'corretta'=>0,'difficolta'=>1.1],
    ['testo'=>'Quale compositore ha scritto Le nozze di Figaro?','opzioni'=>['Mozart','Rossini','Verdi','Bizet'],'corretta'=>0,'difficolta'=>1.4],
    ['testo'=>'Quale band e famosa per Don t Stop Me Now?','opzioni'=>['Queen','ABBA','The Beatles','ELO'],'corretta'=>0,'difficolta'=>1.0],
    ['testo'=>'Quale cantante ha pubblicato Sorry?','opzioni'=>['Justin Bieber','Shawn Mendes','The Weeknd','Bruno Mars'],'corretta'=>0,'difficolta'=>1.0],
    ['testo'=>'Quale gruppo ha inciso Imagine?','opzioni'=>['John Lennon','The Beatles','Pink Floyd','Eagles'],'corretta'=>0,'difficolta'=>1.1],
    ['testo'=>'Quale compositore e noto per Le nozze di Figaro e Don Giovanni?','opzioni'=>['Mozart','Verdi','Puccini','Donizetti'],'corretta'=>0,'difficolta'=>1.5],
    ['testo'=>'Quale strumento usa un ancia doppia?','opzioni'=>['Oboe','Clarinetto','Flauto','Tromba'],'corretta'=>0,'difficolta'=>1.6],
    ['testo'=>'Quale band ha pubblicato Back in Black?','opzioni'=>['AC/DC','Metallica','Guns N Roses','Aerosmith'],'corretta'=>0,'difficolta'=>1.0],
    ['testo'=>'Quale artista canta Senorita con Shawn Mendes?','opzioni'=>['Camila Cabello','Dua Lipa','Ariana Grande','Bebe Rexha'],'corretta'=>0,'difficolta'=>1.1],
    ['testo'=>'Quale compositore ha scritto il Carnevale degli animali?','opzioni'=>['Saint-Saens','Ravel','Debussy','Fauré'],'corretta'=>0,'difficolta'=>1.8],
    ['testo'=>'Quale gruppo ha inciso Sultans of Swing?','opzioni'=>['Dire Straits','Genesis','Yes','The Who'],'corretta'=>0,'difficolta'=>1.4],
    ['testo'=>'Quale cantante italiana ha vinto Sanremo 2025 con Balorda nostalgia?','opzioni'=>['Olly','Irama','Diodato','Geolier'],'corretta'=>0,'difficolta'=>1.2],
    ['testo'=>'Quale artista canta As It Was?','opzioni'=>['Harry Styles','Niall Horan','Zayn','Liam Payne'],'corretta'=>0,'difficolta'=>1.0],
    ['testo'=>'Quale band ha pubblicato With or Without You?','opzioni'=>['U2','The Cure','Simple Minds','INXS'],'corretta'=>0,'difficolta'=>1.2],
    ['testo'=>'Quale compositore ha scritto la Moldava?','opzioni'=>['Smetana','Dvorak','Janacek','Kodaly'],'corretta'=>0,'difficolta'=>1.9],
    ['testo'=>'Quale cantante canta Chandelier?','opzioni'=>['Sia','P!nk','Adele','Lorde'],'corretta'=>0,'difficolta'=>1.1],
    ['testo'=>'Quale gruppo ha inciso Feel Good Inc.?','opzioni'=>['Gorillaz','Blur','Oasis','Pulp'],'corretta'=>0,'difficolta'=>1.3],
    ['testo'=>'Quale strumento e fondamentale nel trio jazz piano-basso-batteria?','opzioni'=>['Contrabbasso','Violoncello','Trombone','Clarinetto'],'corretta'=>0,'difficolta'=>1.5],
    ['testo'=>'Quale cantante ha pubblicato We Found Love con Calvin Harris?','opzioni'=>['Rihanna','Dua Lipa','Beyonce','Kesha'],'corretta'=>0,'difficolta'=>1.1],
    ['testo'=>'Quale gruppo e noto per Believer?','opzioni'=>['Imagine Dragons','OneRepublic','Maroon 5','The Script'],'corretta'=>0,'difficolta'=>1.0],
    ['testo'=>'Quale compositore ha scritto la danza ungherese n.5?','opzioni'=>['Brahms','Chopin','Liszt','Bartok'],'corretta'=>0,'difficolta'=>1.7]
];

$target = 50;
$inserted = 0;
$skipped = 0;

$checkDupStmt = $pdo->prepare("SELECT id FROM domande WHERE fingerprint_unico = :fp LIMIT 1");
$insertDomandaStmt = $pdo->prepare(
    "INSERT INTO domande (testo, codice_domanda, fingerprint_unico, difficolta, tipo_domanda, fase_domanda, argomento_id, attiva)
     VALUES (:testo, :codice_domanda, :fingerprint_unico, :difficolta, 'CLASSIC', 'domanda', 6, 1)"
);
$updateCodeStmt = $pdo->prepare("UPDATE domande SET codice_domanda = :codice WHERE id = :id");
$insertOpzioneStmt = $pdo->prepare("INSERT INTO opzioni (domanda_id, testo, corretta) VALUES (:domanda_id, :testo, :corretta)");

foreach ($questions as $q) {
    if ($inserted >= $target) {
        break;
    }

    $fp = fingerprint($q);
    $checkDupStmt->execute(['fp' => $fp]);
    $dup = $checkDupStmt->fetch(PDO::FETCH_ASSOC);
    if ($dup) {
        $skipped++;
        continue;
    }

    $pdo->beginTransaction();
    try {
        $insertDomandaStmt->execute([
            'testo' => $q['testo'],
            'codice_domanda' => '',
            'fingerprint_unico' => $fp,
            'difficolta' => number_format((float) $q['difficolta'], 1, '.', ''),
        ]);

        $domandaId = (int) $pdo->lastInsertId();
        $codice = sprintf('CLS-A006-%05d', $domandaId);
        $updateCodeStmt->execute(['codice' => $codice, 'id' => $domandaId]);

        foreach ($q['opzioni'] as $idx => $opt) {
            $insertOpzioneStmt->execute([
                'domanda_id' => $domandaId,
                'testo' => $opt,
                'corretta' => ($idx === (int) $q['corretta']) ? 1 : 0,
            ]);
        }

        $pdo->commit();
        $inserted++;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $skipped++;
    }
}

echo "Inserted: {$inserted}\n";
echo "Skipped: {$skipped}\n";
if ($inserted < $target) {
    echo "Missing to target: " . ($target - $inserted) . "\n";
}
