<?php

namespace App\Controllers\Traits;

use App\Models\Sessione;
use App\Services\Admin\SessionImageSearchService;

trait HandlesAdminSessionActions
{
    private function handleAdminPhaseAction(string $action, int $sessioneId): bool
    {
        switch ($action) {
            case 'avvia-puntata':
                $this->clearAudioPreviewCommand($sessioneId);
                (new \App\Services\SessioneService($sessioneId))->avviaPuntata();
                $this->json([
                    'success' => true,
                    'action' => $action
                ]);
                return true;

            case 'avvia-domanda':
                $this->clearAudioPreviewCommand($sessioneId);
                (new \App\Services\SessioneService($sessioneId))->avviaDomanda();
                $this->json([
                    'success' => true,
                    'action' => $action
                ]);
                return true;

            case 'risultati':
                $this->clearAudioPreviewCommand($sessioneId);
                (new \App\Services\SessioneService($sessioneId))->chiudiDomanda();
                $this->json([
                    'success' => true,
                    'action' => $action
                ]);
                return true;

            case 'prossima':
                $this->clearAudioPreviewCommand($sessioneId);
                (new \App\Services\SessioneService($sessioneId))->prossimaFase();
                $this->json([
                    'success' => true,
                    'action' => $action
                ]);
                return true;

            case 'riavvia':
                $this->clearAudioPreviewCommand($sessioneId);
                (new \App\Services\SessioneService($sessioneId))->resetTotale();
                $this->json([
                    'success' => true,
                    'action' => $action
                ]);
                return true;
        }

        return false;
    }

    private function handleAdminSessionAction(string $action, int $sessioneId): bool
    {
        switch ($action) {
            case 'nuova-sessione':
                $nomeSessione = trim((string) ($_POST['nome'] ?? $_POST['sessione_nome'] ?? ''));

                $numeroDomande = (int) ($_POST['numero_domande'] ?? 0);
                $poolTipo = trim((string) ($_POST['pool_tipo'] ?? ''));
                $argomentoRaw = $_POST['argomento_id'] ?? null;
                $selezioneTipo = trim((string) ($_POST['selezione_tipo'] ?? ''));
                $maxPerArgomento = (int) ($_POST['max_per_argomento'] ?? 0);

                $configInput = [
                    'numero_domande' => $numeroDomande > 0 ? $numeroDomande : null,
                    'pool_tipo' => $poolTipo,
                    'argomento_id' => $argomentoRaw,
                    'selezione_tipo' => $selezioneTipo,
                    'max_per_argomento' => $maxPerArgomento > 0 ? $maxPerArgomento : null,
                ];

                $sessioneModel = new Sessione();
                $nuovaId = $sessioneModel->crea(1, $nomeSessione, $configInput);
                $sessioneCreata = $sessioneModel->trova($nuovaId) ?: [];

                $this->json([
                    'success' => true,
                    'action' => $action,
                    'sessione_id' => $nuovaId,
                    'nome_sessione' => $nomeSessione !== '' ? $nomeSessione : null,
                    'numero_domande' => (int) ($sessioneCreata['numero_domande'] ?? 0),
                    'pool_tipo' => (string) ($sessioneCreata['pool_tipo'] ?? ''),
                    'argomento_id' => $sessioneCreata['argomento_id'] ?? null,
                    'selezione_tipo' => (string) ($sessioneCreata['selezione_tipo'] ?? ''),
                    'max_per_argomento' => $sessioneCreata['max_per_argomento'] ?? null,
                ]);
                return true;

            case 'sessioni-lista':
                $sessioneModel = new Sessione();
                $corrente = $sessioneModel->corrente();

                $this->json([
                    'success' => true,
                    'action' => $action,
                    'sessione_corrente_id' => (int) ($corrente['id'] ?? 0),
                    'sessioni' => $sessioneModel->disponibili(200)
                ]);
                return true;

            case 'argomenti-lista':
                $pdo = \App\Core\Database::getInstance();
                $stmt = $pdo->query("SELECT id, nome FROM argomenti ORDER BY nome ASC");
                $this->json([
                    'success' => true,
                    'action' => $action,
                    'argomenti' => $stmt ? ($stmt->fetchAll() ?: []) : []
                ]);
                return true;

            case 'set-corrente':
                $targetSessioneId = (int) ($_POST['sessione_id'] ?? 0);
                if ($targetSessioneId <= 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Sessione non valida'
                    ]);
                    return true;
                }

                $ok = (new Sessione())->impostaCorrente($targetSessioneId);
                $this->json([
                    'success' => $ok,
                    'action' => $action,
                    'sessione_id' => $targetSessioneId,
                    'error' => $ok ? null : 'Impossibile impostare sessione corrente'
                ]);
                return true;

            case 'sessione-update':
                $targetSessioneId = (int) ($_POST['sessione_id'] ?? 0);
                if ($targetSessioneId <= 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Sessione non valida'
                    ]);
                    return true;
                }

                $nomeSessione = trim((string) ($_POST['nome_sessione'] ?? $_POST['nome'] ?? ''));
                $numeroDomande = (int) ($_POST['numero_domande'] ?? 0);
                $poolTipo = trim((string) ($_POST['pool_tipo'] ?? 'tutti'));
                $argomentoRaw = $_POST['argomento_id'] ?? null;
                $selezioneTipo = trim((string) ($_POST['selezione_tipo'] ?? 'random'));
                $maxPerArgomento = (int) ($_POST['max_per_argomento'] ?? 0);

                $sessioneModel = new Sessione();
                $ok = $sessioneModel->aggiornaSnapshot($targetSessioneId, [
                    'nome_sessione' => $nomeSessione,
                    'numero_domande' => $numeroDomande,
                    'pool_tipo' => $poolTipo,
                    'argomento_id' => $argomentoRaw,
                    'selezione_tipo' => $selezioneTipo,
                    'max_per_argomento' => $maxPerArgomento > 0 ? $maxPerArgomento : null,
                ]);

                $aggiornata = $sessioneModel->trova($targetSessioneId) ?: null;

                $this->json([
                    'success' => $ok,
                    'action' => $action,
                    'sessione_id' => $targetSessioneId,
                    'sessione' => $aggiornata,
                    'error' => $ok ? null : 'Impossibile aggiornare sessione'
                ]);
                return true;

            case 'sessione-image-search':
                $targetSessioneId = (int) ($_POST['sessione_id'] ?? $sessioneId ?? 0);
                if ($targetSessioneId <= 0) {
                    $corrente = (new Sessione())->corrente();
                    $targetSessioneId = (int) ($corrente['id'] ?? 0);
                }

                if ($targetSessioneId <= 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Sessione corrente non disponibile'
                    ]);
                    return true;
                }

                $report = (new SessionImageSearchService())->analyzeSession($targetSessioneId);
                $this->json([
                    'success' => true,
                    'action' => $action,
                    'sessione_id' => $targetSessioneId,
                    'report' => $report,
                ]);
                return true;
        }

        return false;
    }
}
