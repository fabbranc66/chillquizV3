<?php

namespace App\Controllers;

use App\Models\Sessione;
use App\Models\SelezioneDomande;
use App\Models\Partecipazione;
use App\Services\SessioneService;

class ApiController
{
    /* ======================
       PUBLIC API
    ====================== */

    public function crea(int $configurazioneId): void
    {
        $sessioneId = (new Sessione())->crea($configurazioneId);

        $this->json([
            'success' => true,
            'sessione_id' => $sessioneId
        ]);
    }

    public function stato(int $sessioneId): void
    {
        try {

            $service = new SessioneService($sessioneId);
            $service->verificaTimer();

            $sessione = (new Sessione())->trova($sessioneId);

            $this->json([
                'success' => true,
                'sessione' => $sessione
            ]);

        } catch (\Throwable $e) {

            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function domanda(int $sessioneId): void
    {
        try {

            $service = new SessioneService($sessioneId);
            $service->verificaTimer();

            if ($service->stato() !== 'domanda') {

                $this->json([
                    'success' => false,
                    'error' => 'Domanda non disponibile in questo stato'
                ]);

                return;
            }

            $domanda = $service->domandaCorrente();

            $this->json([
                'success' => true,
                'domanda' => $domanda
            ]);

        } catch (\Throwable $e) {

            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function classifica(int $sessioneId): void
    {
        try {

            $service = new SessioneService($sessioneId);

            $this->json([
                'success' => true,
                'classifica' => $service->classifica()
            ]);

        } catch (\Throwable $e) {

            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /* ======================
       PLAYER ACTIONS
    ====================== */

    public function puntata(int $sessioneId): void
    {
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

    public function risposta(int $sessioneId): void
    {
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

    public function admin(string $action, int $sessioneId): void
    {
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

                    $service = new SessioneService($sessioneId);
                    $service->avviaPuntata();
                    break;

                case 'avvia-domanda':

                    $service = new SessioneService($sessioneId);
                    $service->avviaDomanda();
                    break;

                case 'risultati':

                    $service = new SessioneService($sessioneId);
                    $service->chiudiDomanda();
                    break;

                case 'prossima':

                    $service = new SessioneService($sessioneId);
                    $service->prossimaFase();
                    break;

                case 'riavvia':

                    $service = new SessioneService($sessioneId);
                    $service->resetTotale();
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
                'action' => $action,
                'sessione_id' => $sessioneId
            ]);

        } catch (\Throwable $e) {

            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function json($data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}