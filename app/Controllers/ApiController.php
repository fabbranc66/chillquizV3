<?php

namespace App\Controllers;

use App\Models\Sessione;
use App\Models\SelezioneDomande;
use App\Models\Partecipazione;

class ApiController
{
    /* ======================
       PUBLIC API
    ====================== */

    public function crea(int $configurazioneId): void
    {
        $sessioneId = (new Sessione())->crea($configurazioneId);

        $this->json([
            'ok' => true,
            'sessione_id' => $sessioneId
        ]);
    }

    public function stato(int $sessioneId): void
    {
        $sessione = (new Sessione())->trova($sessioneId);
        $this->json($sessione ?: []);
    }

    public function domanda(int $sessioneId): void
    {
        $sessioneModel = new Sessione();
        $selezione = new SelezioneDomande();

        $sessione = $sessioneModel->trova($sessioneId);

        if (!$sessione) {
            $this->json([]);
            return;
        }

        $numero = (int) $sessione['domanda_corrente'];
        $domanda = $selezione->domandaCorrente($sessioneId, $numero);

        $this->json($domanda ?: []);
    }

    public function classifica(int $sessioneId): void
    {
        $classifica = (new Partecipazione())->classifica($sessioneId);
        $this->json($classifica);
    }

    /* ======================
       PLAYER ACTIONS
    ====================== */

    public function puntata(int $sessioneId): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['errore' => 'Metodo non consentito']);
            return;
        }

        $partecipazioneId = (int) ($_POST['partecipazione_id'] ?? 0);
        $importo = (int) ($_POST['puntata'] ?? 0);

        if ($partecipazioneId <= 0 || $importo <= 0) {
            $this->json(['errore' => 'Dati non validi']);
            return;
        }

        $partecipazione = new Partecipazione();

        $ok = $partecipazione->registraPuntata($partecipazioneId, $importo);

        if (!$ok) {
            $this->json(['errore' => 'Puntata non valida']);
            return;
        }

        $this->json([
            'ok' => true,
            'puntata' => $importo
        ]);
    }

    public function risposta(int $sessioneId): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['errore' => 'Metodo non consentito']);
            return;
        }

        $partecipazioneId = (int) ($_POST['partecipazione_id'] ?? 0);
        $domandaId = (int) ($_POST['domanda_id'] ?? 0);
        $opzioneId = (int) ($_POST['opzione_id'] ?? 0);

        if ($partecipazioneId <= 0 || $domandaId <= 0 || $opzioneId <= 0) {
            $this->json(['errore' => 'Dati non validi']);
            return;
        }

        $partecipazione = new Partecipazione();

        $risultato = $partecipazione->registraRisposta(
            $partecipazioneId,
            $domandaId,
            $opzioneId
        );

        if (!$risultato) {
            $this->json(['errore' => 'Errore registrazione risposta']);
            return;
        }

        $this->json($risultato);
    }

    /* ======================
       ADMIN CONTROL
    ====================== */

    public function admin(string $action, int $sessioneId): void
    {
        $sessioneModel = new Sessione();

        switch ($action) {

            case 'avvia-puntata':
                $sessioneModel->cambiaStato($sessioneId, 'puntata');
                break;

            case 'avvia-domanda':
                $sessioneModel->cambiaStato($sessioneId, 'domanda');
                break;

            case 'risultati':
                $sessioneModel->cambiaStato($sessioneId, 'risultati');
                break;

            case 'prossima':
                $sessioneModel->avanzaDomanda($sessioneId);
                break;

            default:
                $this->json(['errore' => 'Azione non valida']);
                return;
        }

        $this->json([
            'ok' => true,
            'azione' => $action,
            'sessione_id' => $sessioneId
        ]);
    }

    private function json($data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}