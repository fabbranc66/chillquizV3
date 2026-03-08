<?php

namespace App\Services\Admin;

use App\Core\Database;
use PDO;

class SessionImageSearchService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function analyzeSession(int $sessioneId): array
    {
        $sessionName = $this->loadSessionName($sessioneId);
        $stmt = $this->pdo->prepare(
            "SELECT
                sd.posizione,
                d.id AS domanda_id,
                d.codice_domanda,
                d.testo,
                d.tipo_domanda,
                d.media_image_path,
                a.nome AS argomento,
                o.testo AS risposta_corretta
             FROM sessione_domande sd
             JOIN domande d ON d.id = sd.domanda_id
             LEFT JOIN argomenti a ON a.id = d.argomento_id
             LEFT JOIN opzioni o ON o.domanda_id = d.id AND o.corretta = 1
             WHERE sd.sessione_id = :sessione_id
             ORDER BY sd.posizione ASC"
        );
        $stmt->execute(['sessione_id' => $sessioneId]);
        $rows = $stmt->fetchAll() ?: [];

        $items = [];
        $summary = [
            'total' => count($rows),
            'needs_attention' => 0,
            'generic_or_missing' => 0,
            'spoiler_risk_high' => 0,
        ];
        foreach ($rows as $row) {
            $item = $this->buildItem($row);
            $items[] = $item;

            if (!empty($item['needs_attention'])) {
                $summary['needs_attention']++;
            }
            if (in_array((string) ($item['status'] ?? ''), ['missing', 'generic'], true)) {
                $summary['generic_or_missing']++;
            }
            if ((string) ($item['spoiler_risk'] ?? '') === 'high') {
                $summary['spoiler_risk_high']++;
            }
        }

        return [
            'sessione_id' => $sessioneId,
            'sessione_nome' => $sessionName !== '' ? $sessionName : ('Sessione ' . $sessioneId),
            'summary' => $summary,
            'items' => $items,
        ];
    }

    private function loadSessionName(int $sessioneId): string
    {
        $columns = $this->pdo->query("SHOW COLUMNS FROM sessioni");
        $available = $columns ? ($columns->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
        $nameColumn = null;
        foreach (['nome_sessione', 'nome', 'titolo'] as $candidate) {
            if (in_array($candidate, $available, true)) {
                $nameColumn = $candidate;
                break;
            }
        }

        if ($nameColumn === null) {
            return '';
        }

        $stmt = $this->pdo->prepare("SELECT {$nameColumn} AS nome_sessione FROM sessioni WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $sessioneId]);
        $row = $stmt->fetch() ?: null;
        return trim((string) ($row['nome_sessione'] ?? ''));
    }

    private function buildItem(array $row): array
    {
        $questionId = (int) ($row['domanda_id'] ?? 0);
        $argomento = trim((string) ($row['argomento'] ?? ''));
        $question = trim((string) ($row['testo'] ?? ''));
        $answer = trim((string) ($row['risposta_corretta'] ?? ''));
        $imagePath = trim((string) ($row['media_image_path'] ?? ''));
        $fileName = strtolower((string) pathinfo($imagePath, PATHINFO_FILENAME));
        $extension = strtolower((string) pathinfo($imagePath, PATHINFO_EXTENSION));
        $isGeneric = $fileName !== '' && str_ends_with($fileName, '_generic');
        $hasImage = $imagePath !== '';

        $answerTokens = $this->meaningfulTokens($answer);
        $filenameContainsAnswer = $this->filenameContainsAnswer($fileName, $answerTokens);

        $status = 'ok';
        $spoilerRisk = 'low';
        $reasons = [];

        if (!$hasImage) {
            $status = 'missing';
            $spoilerRisk = 'medium';
            $reasons[] = 'Manca un\'immagine collegata.';
        } elseif ($isGeneric) {
            $status = 'generic';
            $spoilerRisk = 'medium';
            $reasons[] = 'L\'immagine attuale e un placeholder generico.';
        } elseif ($filenameContainsAnswer) {
            $status = 'spoiler';
            $spoilerRisk = 'high';
            $reasons[] = 'Il nome file richiama direttamente la risposta.';
        } elseif ($extension === 'svg') {
            $status = 'vector';
            $spoilerRisk = 'medium';
            $reasons[] = 'L\'immagine attuale e vettoriale; meglio una foto o un contesto reale.';
        } else {
            $reasons[] = 'Immagine presente; verifica editoriale consigliata.';
        }

        $suggestion = $this->buildSearchSuggestion($argomento, $question, $answer);
        $needsAttention = $status !== 'ok';
        $targetFilename = $this->buildTargetFilename($row, $imagePath, $argomento, $questionId);
        $targetFolder = 'c:\\xampp\\htdocs\\chillquiz\\public\\upload\\domanda\\image\\';
        $targetAbsolutePath = $targetFolder . $targetFilename;

        return [
            'posizione' => (int) ($row['posizione'] ?? 0),
            'domanda_id' => $questionId,
            'codice_domanda' => (string) ($row['codice_domanda'] ?? ''),
            'argomento' => $argomento,
            'tipo_domanda' => (string) ($row['tipo_domanda'] ?? ''),
            'testo' => $question,
            'risposta_corretta' => $answer,
            'media_image_path' => $imagePath,
            'status' => $status,
            'spoiler_risk' => $spoilerRisk,
            'needs_attention' => $needsAttention,
            'analysis_reason' => implode(' ', $reasons),
            'suggested_style' => 'foto contestuale, non spoiler',
            'search_query' => $suggestion['query'],
            'search_query_backup' => $suggestion['backup_query'],
            'target_folder' => $targetFolder,
            'target_filename' => $targetFilename,
            'target_absolute_path' => $targetAbsolutePath,
        ];
    }

    private function buildTargetFilename(array $row, string $imagePath, string $argomento, int $questionId): string
    {
        $currentName = trim((string) basename($imagePath));
        if ($currentName !== '') {
            return $currentName;
        }

        $topic = $this->normalize($argomento);
        $topic = $topic !== '' ? str_replace(' ', '_', $topic) : 'img';
        $code = trim((string) ($row['codice_domanda'] ?? ''));
        if ($code !== '') {
            $code = strtolower(str_replace('-', '_', $code));
            return $code . '.jpg';
        }

        return sprintf('%s_%d.jpg', $topic, max(1, $questionId));
    }

    /**
     * @return array<int, string>
     */
    private function meaningfulTokens(string $value): array
    {
        $normalized = $this->normalize($value);
        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/[^a-z0-9]+/', $normalized) ?: [];
        $stopwords = [
            'the', 'and', 'del', 'della', 'delle', 'degli', 'dello', 'dell', 'dei',
            'della', 'di', 'la', 'lo', 'gli', 'le', 'il', 'i', 'un', 'una', 'uno',
            'jonathan', 'sandro', 'mario', 'amazon',
        ];

        $tokens = [];
        foreach ($parts as $part) {
            $token = trim((string) $part);
            if ($token === '' || strlen($token) < 3) {
                continue;
            }
            if (in_array($token, $stopwords, true)) {
                continue;
            }
            $tokens[] = $token;
        }

        return array_values(array_unique($tokens));
    }

    private function filenameContainsAnswer(string $fileName, array $answerTokens): bool
    {
        if ($fileName === '' || $answerTokens === []) {
            return false;
        }

        $normalizedFile = $this->normalize($fileName);
        foreach ($answerTokens as $token) {
            if (strlen($token) >= 4 && str_contains($normalizedFile, $token)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($trans) && $trans !== '') {
            $value = $trans;
        }

        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    /**
     * @return array{query: string, backup_query: string}
     */
    private function buildSearchSuggestion(string $argomento, string $question, string $answer): array
    {
        $topicKey = $this->normalize($argomento);
        $questionKey = $this->normalize($question);
        $answerRaw = trim($answer);

        $query = $answerRaw !== '' ? ($answerRaw . ' photo') : 'contextual photo quiz';
        $backup = $answerRaw !== '' ? ($answerRaw . ' close up photo') : 'editorial context photo';

        switch ($topicKey) {
            case 'scienza':
                $query = str_contains($questionKey, 'dna')
                    ? ($answerRaw !== '' ? "{$answerRaw} dna molecule laboratory illustration" : 'dna laboratory test tubes close up photo')
                    : (($answerRaw !== '' ? "{$answerRaw} science concept photo" : 'science laboratory close up photo'));
                $backup = str_contains($questionKey, 'luce')
                    ? 'speed of light space science image'
                    : 'biology lab blue light photo';
                break;

            case 'matematica':
                $query = str_contains($questionKey, 'moneta')
                    ? 'coin toss probability photo'
                    : (($answerRaw !== '' ? "{$answerRaw} mathematics concept image" : 'mathematics chalkboard detail photo'));
                $backup = 'probability concept coins photo';
                break;

            case 'letteratura':
                $query = $answerRaw !== '' ? "{$answerRaw} writer portrait engraving" : 'old writing desk quill parchment photo';
                $backup = 'gulliver travels old book illustration';
                break;

            case 'arte':
                $query = $answerRaw !== '' ? "{$answerRaw} renaissance painting detail" : 'paint brushes palette studio photo';
                $backup = 'renaissance atelier painting photo';
                break;

            case 'tecnologia':
                if (str_contains($questionKey, 'aws')) {
                    $query = $answerRaw !== '' ? "{$answerRaw} cloud infrastructure data center" : 'server room data center photo';
                    $backup = 'cloud infrastructure racks photo';
                } elseif (str_contains($questionKey, 'memoria') || str_contains($questionKey, 'dischi')) {
                    $query = $answerRaw !== '' ? "{$answerRaw} storage drive photo" : 'computer motherboard storage close up photo';
                    $backup = 'computer hardware macro photo';
                } else {
                    $query = $answerRaw !== '' ? "{$answerRaw} technology photo" : 'technology hardware close up photo';
                    $backup = 'servers motherboard detail photo';
                }
                break;

            case 'cucina':
                $query = $answerRaw !== '' ? "{$answerRaw} drink photo" : 'iced coffee glass italian cafe photo';
                $backup = 'salento iced coffee photo';
                break;

            case 'storia':
                $query = str_contains($questionKey, '1066')
                    ? (($answerRaw !== '' ? "{$answerRaw} medieval tapestry detail" : 'medieval castle wall photo'))
                    : (($answerRaw !== '' ? "{$answerRaw} historical artifact photo" : 'medieval artifact close up photo'));
                $backup = 'medieval embroidery detail photo';
                break;

            case 'mitologia':
                $query = str_contains($questionKey, 'luna')
                    ? (($answerRaw !== '' ? "{$answerRaw} goddess moon art" : 'moon trees night sky photo'))
                    : (($answerRaw !== '' ? "{$answerRaw} mythology art" : 'forest moon bow silhouette photo'));
                $backup = 'night forest moon photo';
                break;

            case 'videogiochi':
                $query = $answerRaw !== '' ? "{$answerRaw} game screenshot art" : 'video game controller neon photo';
                $backup = 'gaming setup colorful photo';
                break;

            case 'musica':
                $query = $answerRaw !== '' ? "{$answerRaw} live concert photo" : 'music stage photo';
                $backup = $answerRaw !== '' ? "{$answerRaw} press photo" : 'recording studio artist photo';
                break;

            case 'geografia':
                $query = $answerRaw !== '' ? "{$answerRaw} city skyline photo" : 'city skyline travel photo';
                $backup = $answerRaw !== '' ? "{$answerRaw} landmark photo" : 'travel landmark photo';
                break;
        }

        return [
            'query' => $query,
            'backup_query' => $backup,
        ];
    }
}
