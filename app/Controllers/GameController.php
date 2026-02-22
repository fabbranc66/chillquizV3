<?php

namespace App\Controllers;

use App\Services\SessioneService;

class GameController
{
    public function index()
    {
        echo "<h2>TEST DOMANDA CORRENTE - SESSIONE 30</h2>";

        try {

            $service = new SessioneService(30);

            echo "Sessione caricata.<br>";

            echo "Domanda corrente: " . $service->stato() . "<br><br>";

            $domanda = $service->domandaCorrente();

            if (!$domanda) {
                echo "Nessuna domanda trovata.";
                return;
            }

            echo "<pre>";
            print_r($domanda);
            echo "</pre>";

        } catch (\Throwable $e) {
            echo "ERRORE:<br>";
            echo $e->getMessage();
        }
    }
}