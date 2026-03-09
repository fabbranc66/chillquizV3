<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use App\Core\Database;
use App\Models\Sessione;
use App\Services\Question\ImpostoreModeService;
use App\Services\Question\MemeModeService;
use App\Services\Question\QuestionModeResolver;
use App\Services\SessioneService;

function smoke_issue(string $level, string $code, string $message, array $meta = []): array
{
    return [
        'level' => $level,
        'code' => $code,
        'message' => $message,
        'meta' => $meta,
    ];
}

$sessioneId = (int) ($argv[1] ?? 0);
if ($sessioneId <= 0) {
    fwrite(STDERR, "Usage: php scripts/smoke_session_assert.php <sessione_id>\n");
    exit(1);
}

$pdo = Database::getInstance();
$sessione = (new Sessione())->trova($sessioneId);
if (!$sessione) {
    fwrite(STDERR, "Sessione non trovata: {$sessioneId}\n");
    exit(1);
}

$service = new SessioneService($sessioneId);
$domandaCorrente = $service->domandaCorrente();
$domandaId = (int) ($domandaCorrente['id'] ?? 0);
$issues = [];

$numeroDomande = (int) ($sessione['numero_domande'] ?? 0);

if ($domandaId <= 0 && (string) ($sessione['stato'] ?? '') !== 'conclusa') {
    $issues[] = smoke_issue('error', 'missing_current_question', 'La sessione non ha una domanda corrente valida.');
}

if (
    (string) ($sessione['stato'] ?? '') === 'conclusa'
    && $numeroDomande > 0
    && (int) ($sessione['domanda_corrente'] ?? 0) < $numeroDomande
) {
    $issues[] = smoke_issue(
        'error',
        'completed_too_early',
        'La sessione risulta conclusa prima dell ultima domanda configurata.',
        [
            'domanda_corrente' => (int) ($sessione['domanda_corrente'] ?? 0),
            'numero_domande' => $numeroDomande,
        ]
    );
}

$duplicateStmt = $pdo->prepare(
    "SELECT r.partecipazione_id, u.nome, r.domanda_id, COUNT(*) AS totale
     FROM risposte r
     JOIN partecipazioni p ON p.id = r.partecipazione_id
     JOIN utenti u ON u.id = p.utente_id
     WHERE p.sessione_id = :sessione_id
     GROUP BY r.partecipazione_id, r.domanda_id
     HAVING COUNT(*) > 1"
);
$duplicateStmt->execute(['sessione_id' => $sessioneId]);
foreach ($duplicateStmt->fetchAll() ?: [] as $row) {
    $issues[] = smoke_issue(
        'error',
        'duplicate_answers',
        'Trovate risposte multiple per lo stesso player sulla stessa domanda.',
        $row
    );
}

if ($domandaId > 0) {
    $liveAndAnsweredStmt = $pdo->prepare(
        "SELECT pl.partecipazione_id, u.nome, pl.importo, r.id AS risposta_id
         FROM puntate_live pl
         JOIN partecipazioni p ON p.id = pl.partecipazione_id
         JOIN utenti u ON u.id = p.utente_id
         JOIN risposte r
           ON r.partecipazione_id = pl.partecipazione_id
          AND r.domanda_id = :domanda_id
         WHERE pl.sessione_id = :sessione_id"
    );
    $liveAndAnsweredStmt->execute([
        'sessione_id' => $sessioneId,
        'domanda_id' => $domandaId,
    ]);

    foreach ($liveAndAnsweredStmt->fetchAll() ?: [] as $row) {
        $issues[] = smoke_issue(
            'error',
            'live_bet_and_answer',
            'Un player risulta ancora in puntate_live pur avendo gia una risposta sulla domanda corrente.',
            $row
        );
    }

    $modeMeta = (new QuestionModeResolver())->resolveFromRow($domandaCorrente ?: []);
    $baseType = strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? 'CLASSIC')));
    $impostoreService = new ImpostoreModeService();
    $memeService = new MemeModeService();
    $impostoreEnabled = $impostoreService->isEnabledForQuestion($sessioneId, $domandaId);
    $memeState = $memeService->getRuntimeState($sessioneId) ?? [];
    $memeEnabled = $memeService->isEnabledForQuestion($sessioneId, $domandaId);

    if ($baseType === 'SARABANDA' && $impostoreEnabled) {
        $issues[] = smoke_issue('error', 'impostore_on_sarabanda', 'IMPOSTORE attivo su una domanda SARABANDA.', [
            'domanda_id' => $domandaId,
        ]);
    }

    if ($baseType === 'SARABANDA' && $memeEnabled) {
        $issues[] = smoke_issue('error', 'meme_on_sarabanda', 'MEME attivo su una domanda SARABANDA.', [
            'domanda_id' => $domandaId,
        ]);
    }

    if ($memeEnabled && trim((string) ($memeState['meme_text'] ?? '')) === '') {
        $issues[] = smoke_issue('error', 'meme_without_text', 'MEME attivo senza testo assurdo valorizzato.', [
            'domanda_id' => $domandaId,
        ]);
    }

    if (
        (string) ($sessione['stato'] ?? '') === 'domanda'
        && strtoupper(trim((string) (($domandaCorrente['tipo_domanda'] ?? 'CLASSIC')))) !== 'SARABANDA'
        && (float) ($sessione['inizio_domanda'] ?? 0) <= 0
    ) {
        $issues[] = smoke_issue('error', 'missing_question_start', 'La sessione e in domanda ma inizio_domanda non e valorizzato.', [
            'domanda_id' => $domandaId,
        ]);
    }
}

$summary = [
    'sessione_id' => $sessioneId,
    'stato' => (string) ($sessione['stato'] ?? ''),
    'domanda_corrente' => (int) ($sessione['domanda_corrente'] ?? 0),
    'current_question_id' => $domandaId,
    'issues_total' => count($issues),
    'errors' => count(array_filter($issues, static fn (array $issue): bool => $issue['level'] === 'error')),
    'warnings' => count(array_filter($issues, static fn (array $issue): bool => $issue['level'] === 'warning')),
];

echo json_encode([
    'summary' => $summary,
    'issues' => $issues,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($summary['errors'] > 0 ? 1 : 0);
