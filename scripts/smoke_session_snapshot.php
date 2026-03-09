<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use App\Core\Database;
use App\Models\Sessione;
use App\Services\Question\ImpostoreModeService;
use App\Services\Question\MemeModeService;
use App\Services\SessioneService;

$sessioneId = (int) ($argv[1] ?? 0);
if ($sessioneId <= 0) {
    fwrite(STDERR, "Usage: php scripts/smoke_session_snapshot.php <sessione_id>\n");
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
$impostoreService = new ImpostoreModeService();
$memeService = new MemeModeService();

$puntateStmt = $pdo->prepare(
    "SELECT pl.partecipazione_id, u.nome, pl.importo, pl.aggiornato_il
     FROM puntate_live pl
     JOIN partecipazioni p ON p.id = pl.partecipazione_id
     JOIN utenti u ON u.id = p.utente_id
     WHERE pl.sessione_id = :sessione_id
     ORDER BY pl.aggiornato_il DESC, pl.partecipazione_id ASC"
);
$puntateStmt->execute(['sessione_id' => $sessioneId]);

$risposteStmt = $pdo->prepare(
    "SELECT r.id,
            r.partecipazione_id,
            u.nome,
            r.domanda_id,
            r.opzione_id,
            r.corretta,
            r.puntata,
            r.punti,
            r.tempo_risposta,
            o_sel.testo AS risposta_data_testo,
            o_ok.testo AS risposta_corretta_testo
     FROM risposte r
     JOIN partecipazioni p ON p.id = r.partecipazione_id
     JOIN utenti u ON u.id = p.utente_id
     LEFT JOIN opzioni o_sel ON o_sel.id = r.opzione_id
     LEFT JOIN opzioni o_ok
       ON o_ok.domanda_id = r.domanda_id
      AND o_ok.corretta = 1
     WHERE p.sessione_id = :sessione_id
     ORDER BY r.id DESC
     LIMIT 20"
);
$risposteStmt->execute(['sessione_id' => $sessioneId]);

$config = [
    'numero_domande' => (int) ($sessione['numero_domande'] ?? 0),
    'pool_tipo' => (string) ($sessione['pool_tipo'] ?? ''),
    'selezione_tipo' => (string) ($sessione['selezione_tipo'] ?? ''),
    'argomento_id' => isset($sessione['argomento_id']) ? (int) $sessione['argomento_id'] : null,
];

echo json_encode([
    'sessione' => $sessione,
    'config' => $config,
    'domanda_corrente' => $domandaCorrente,
    'runtime' => [
        'impostore_enabled' => $domandaId > 0 ? $impostoreService->isEnabledForQuestion($sessioneId, $domandaId) : false,
        'impostore_assignment' => $domandaId > 0 ? $impostoreService->getAssignment($sessioneId, $domandaId) : null,
        'meme_enabled' => $domandaId > 0 ? $memeService->isEnabledForQuestion($sessioneId, $domandaId) : false,
        'meme_state' => $memeService->getRuntimeState($sessioneId),
    ],
    'puntate_live' => $puntateStmt->fetchAll() ?: [],
    'classifica' => $service->classifica(),
    'ultime_risposte' => $risposteStmt->fetchAll() ?: [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
