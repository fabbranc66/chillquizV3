<?php

namespace App\Controllers;

use App\Models\Utente;
use App\Models\Sessione;
use App\Models\Partecipazione;
use App\Core\Database;

class GameController
{
    public function index()
    {
        $utenteModel = new Utente();
        $sessioneModel = new Sessione();
        $partecipazioneModel = new Partecipazione();

        $sessioneId = $sessioneModel->crea(1);

        $u1 = $utenteModel->creaTemporaneo("Mario");
        $u2 = $utenteModel->creaTemporaneo("Luigi");
        $u3 = $utenteModel->creaTemporaneo("Peach");

        $p1 = $partecipazioneModel->entra($sessioneId, $u1);
        $p2 = $partecipazioneModel->entra($sessioneId, $u2);
        $p3 = $partecipazioneModel->entra($sessioneId, $u3);

        $this->setCapitale($p1, 1500);
        $this->setCapitale($p2, 800);
        $this->setCapitale($p3, 1200);

        $classifica = $partecipazioneModel->classifica($sessioneId);

        print_r($classifica);
    }

    private function setCapitale(int $id, int $valore): void
    {
        $pdo = Database::getInstance();

        $stmt = $pdo->prepare(
            "UPDATE partecipazioni
             SET capitale_attuale = :valore
             WHERE id = :id"
        );

        $stmt->execute([
            'valore' => $valore,
            'id' => $id
        ]);
    }
}