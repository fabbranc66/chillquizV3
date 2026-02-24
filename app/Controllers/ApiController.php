<?php

namespace App\Controllers;

use App\Models\Partecipazione;
use App\Models\Risposta;
use App\Models\Sessione;
use App\Models\Utente;
use App\Services\SessioneService;
use Throwable;

class ApiController
{
    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    public function stato($sessioneId): void
    {
        $sessioneId = (int) $sessioneId;

        try {
            $service = new SessioneService($sessioneId);
            $service->verificaTimer();

            $pdo = \App\Core\Database::getInstance();
            $stmt = $pdo->prepare("SELECT * FROM sessioni WHERE id = ?");
            $stmt->execute([$sessioneId]);

            $this->json([
                'success' => true,
                'sessione' => $stmt->fetch()
            ]);

        } catch (Throwable $e) {

            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function domanda($sessioneId): void
    {
        $sessioneId = (int) $sessioneId;

        try {

            $service = new SessioneService($sessioneId);

            $this->json([
                'success' => true,
                'domanda' => $service->domandaCorrente()
            ]);

        } catch (Throwable $e) {

            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function classifica($sessioneId): void
    {
        $sessioneId = (int) $sessioneId;

        try {

            $service = new SessioneService($sessioneId);

            $this->json([
                'success' => true,
                'classifica' => $service->classifica()
            ]);

        } catch (Throwable $e) {

            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /* ======================
       PLAYER ACTIONS
    ====================== */

public function join($sessioneId): void
{
    $sessioneId = (int) $sessioneId;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $this->json([
            'success' => false,
            'error' => 'Metodo non consentito'
        ]);
        return;
    }

    $nome = trim($_POST['nome'] ?? '');

    if ($nome === '') {
        $this->json([
            'success' => false,
            'error' => 'Nome non valido'
        ]);
        return;
    }

    try {

        // 🔒 BLOCCO: nome già usato nella stessa sessione (anche temporanei)
        $pdo = \App\Core\Database::getInstance();

        $check = $pdo->prepare("
            SELECT 1
            FROM partecipazioni p
            JOIN utenti u ON u.id = p.utente_id
            WHERE p.sessione_id = :sessione_id
              AND LOWER(u.nome) = LOWER(:nome)
            LIMIT 1
        ");

        $check->execute([
            'sessione_id' => $sessioneId,
            'nome' => $nome
        ]);

        if ($check->fetch()) {
            $this->json([
                'success' => false,
                'error' => 'Nome già utilizzato in questa sessione'
            ]);
            return;
        }

        // ✅ Se non esiste, crea utente temporaneo e partecipa
        $utenteModel = new Utente();
        $utenteId = $utenteModel->creaTemporaneo($nome);

        $partecipazioneModel = new Partecipazione();
        $partecipazioneId = $partecipazioneModel->entra($sessioneId, $utenteId);

        $partecipazione = $partecipazioneModel->trova($partecipazioneId);

        $this->json([
            'success' => true,
            'utente_id' => $utenteId,
            'partecipazione_id' => $partecipazioneId,
            'capitale' => $partecipazione['capitale_attuale'] ?? 0
        ]);

    } catch (\Throwable $e) {

        $this->json([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
    public function puntata($sessioneId): void
    {
        $sessioneId = (int) $sessioneId;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json([
                'success' => false,
                'error' => 'Metodo non consentito'
            ]);
            return;
        }

        $partecipazioneId = (int) ($_POST['partecipazione_id'] ?? 0);
        $importo = (int) ($_POST['puntata'] ?? 0);

        if ($partecipazioneId <= 0 || $importo <= 0) {
            $this->json([
                'success' => false,
                'error' => 'Dati non validi'
            ]);
            return;
        }

        try {

            $service = new SessioneService($sessioneId);

            if (!$service->puoPuntare()) {
                $this->json([
                    'success' => false,
                    'error' => 'Non è il momento di puntare'
                ]);
                return;
            }

            $partecipazione = new Partecipazione();
            $ok = $partecipazione->registraPuntata($partecipazioneId, $importo);

            if (!$ok) {
                $this->json([
                    'success' => false,
                    'error' => 'Puntata non valida'
                ]);
                return;
            }

            $service->salvaPuntataLive($partecipazioneId, $importo);

            $this->json([
                'success' => true,
                'puntata' => $importo
            ]);

        } catch (\Throwable $e) {

            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function risposta($sessioneId): void
    {
        $sessioneId = (int) $sessioneId;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json([
                'success' => false,
                'error' => 'Metodo non consentito'
            ]);
            return;
        }

        $partecipazioneId = (int) ($_POST['partecipazione_id'] ?? 0);
        $domandaId = (int) ($_POST['domanda_id'] ?? 0);
        $opzioneId = (int) ($_POST['opzione_id'] ?? 0);

        if ($partecipazioneId <= 0 || $domandaId <= 0 || $opzioneId <= 0) {
            $this->json([
                'success' => false,
                'error' => 'Dati non validi'
            ]);
            return;
        }

        try {

            $service = new SessioneService($sessioneId);

            if (!$service->puoRispondere()) {
                $this->json([
                    'success' => false,
                    'error' => 'Non è il momento di rispondere'
                ]);
                return;
            }

            $partecipazione = new Partecipazione();

            $risultato = $partecipazione->registraRisposta(
                $partecipazioneId,
                $domandaId,
                $opzioneId
            );

            if (!$risultato) {
                $this->json([
                    'success' => false,
                    'error' => 'Errore registrazione risposta'
                ]);
                return;
            }

            $service->rimuoviPuntataLive($partecipazioneId);

            $this->json([
                'success' => true,
                'risultato' => $risultato
            ]);

        } catch (\Throwable $e) {

            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /* ======================
       ADMIN CONTROL
    ====================== */

    public function admin($action, $sessioneId): void
    {
        $sessioneId = (int) $sessioneId;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json([
                'success' => false,
                'error' => 'Metodo non consentito'
            ]);
            return;
        }

        try {

            switch ($action) {

                case 'nuova-sessione':
                    $nuovaId = (new Sessione())->crea(1);
                    $this->json([
                        'success' => true,
                        'action' => $action,
                        'sessione_id' => $nuovaId
                    ]);
                    return;

                case 'avvia-puntata':
                    (new SessioneService($sessioneId))->avviaPuntata();
                    break;

                case 'avvia-domanda':
                    (new SessioneService($sessioneId))->avviaDomanda();
                    break;

                case 'risultati':
                    (new SessioneService($sessioneId))->chiudiDomanda();
                    break;

                case 'prossima':
                    (new SessioneService($sessioneId))->prossimaFase();
                    break;

                case 'riavvia':
                    (new SessioneService($sessioneId))->resetTotale();
                    break;

                default:
                    $this->json([
                        'success' => false,
                        'error' => 'Azione non valida'
                    ]);
                    return;
            }

            $this->json([
                'success' => true,
                'action' => $action
            ]);

        } catch (\Throwable $e) {

            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
