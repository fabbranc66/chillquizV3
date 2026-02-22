<?php

namespace App\Controllers;

use App\Services\SessioneService;

class GameController
{
    public function index()
    {
        echo "<h2>TEST BLOCCO SESSIONE CONCLUSA</h2>";

        try {

            $service = new SessioneService(30);

            echo "Stato attuale: " . $service->stato() . "<br><br>";

            echo "Provo ad avviare puntata...<br>";
            $service->avviaPuntata();

            echo "ERRORE: NON dovrebbe arrivare qui.";

        } catch (\Throwable $e) {
            echo "<strong>Eccezione catturata:</strong><br>";
            echo $e->getMessage();
        }
    }
}