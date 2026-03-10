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

        $settings = (new AppSettings())->all();
        $showModuleTags = $settings['show_module_tags'];
        $playerLogoPath = ltrim(trim((string) (($settings['configurazioni_sistema']['logo'] ?? ''))), '/');

        require BASE_PATH . '/app/Views/player/index.php';
    }
}
