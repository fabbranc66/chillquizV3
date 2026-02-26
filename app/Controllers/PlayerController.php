<?php

namespace App\Controllers;

use App\Models\AppSettings;

class PlayerController
{
    public function index($sessioneId = null)
    {
        if (!$sessioneId) {
            echo "Sessione non specificata";
            exit;
        }

        $sessioneId = (int) $sessioneId;

        $showModuleTags = (new AppSettings())->all()['show_module_tags'];

        require BASE_PATH . '/app/Views/player/index.php';
    }
}
