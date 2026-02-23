<?php

namespace App\Controllers;

class PlayerController
{
    public function index($sessioneId = null)
    {
        if (!$sessioneId) {
            echo "Sessione non specificata";
            exit;
        }

        $sessioneId = (int) $sessioneId;

        require BASE_PATH . '/app/Views/player/index.php';
    }
}