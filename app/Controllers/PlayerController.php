<?php

namespace App\Controllers;

use App\Models\AppSettings;
use App\Models\Sessione;

class PlayerController
{
    public function index($sessioneId = null)
    {
        $sessioneId = $sessioneId !== null ? (int) $sessioneId : 0;

        if ($sessioneId <= 0) {
            $corrente = (new Sessione())->corrente();
            $sessioneId = (int) ($corrente['id'] ?? 0);
        }

        if ($sessioneId <= 0) {
            echo "Nessuna sessione attiva";
            exit;
        }

        $showModuleTags = (new AppSettings())->all()['show_module_tags'];

        require BASE_PATH . '/app/Views/player/index.php';
    }
}
