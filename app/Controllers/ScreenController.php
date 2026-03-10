<?php

namespace App\Controllers;

use App\Models\AppSettings;
use App\Models\Sessione;

class ScreenController
{
    public function index($sessioneId = null): void
    {
        $sessioneId = $sessioneId !== null ? (int) $sessioneId : 0;

        if ($sessioneId <= 0) {
            $corrente = (new Sessione())->corrente();
            $sessioneId = (int) ($corrente['id'] ?? 0);
        }

        $settings = (new AppSettings())->all();
        $showModuleTags = $settings['show_module_tags'];
        $screenLogoPath = ltrim(trim((string) (($settings['configurazioni_sistema']['logo'] ?? ''))), '/');

        require BASE_PATH . '/app/Views/screen/index.php';
    }
}
