<?php

namespace App\Controllers;

use App\Models\AppSettings;

class ScreenController
{
    public function index($sessioneId = null): void
    {
        $sessioneId = $sessioneId !== null ? (int) $sessioneId : 0;

        $showModuleTags = (new AppSettings())->all()['show_module_tags'];

        require BASE_PATH . '/app/Views/screen/index.php';
    }
}
