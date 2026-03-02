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

        $showModuleTags = (new AppSettings())->all()['show_module_tags'];

        require BASE_PATH . '/app/Views/screen/index.php';
    }
}
