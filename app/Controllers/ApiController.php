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