<?php

namespace App\Controllers;

class ScreenController
{
    public function index($sessioneId = null): void
    {
        $sessioneId = $sessioneId !== null ? (int) $sessioneId : 0;

        require BASE_PATH . '/app/Views/screen/index.php';
    }
}
