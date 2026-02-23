<?php

namespace App\Controllers;

class PlayerController
{
    public function index()
    {
        require BASE_PATH . '/app/Views/player/index.php';
    }
}