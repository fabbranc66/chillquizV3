<?php

namespace App\Controllers;

use App\Models\AppSettings;
use App\Models\Sessione;

class AdminController
{
    public function index()
    {
        $sessioneModel = new Sessione();
        $corrente = $sessioneModel->corrente();

        $sessioneId = (int) ($corrente['id'] ?? 0);
        $nomeSessione = trim((string) (($corrente['nome_sessione'] ?? $corrente['nome'] ?? $corrente['titolo'] ?? '')));

        $showModuleTags = (new AppSettings())->all()['show_module_tags'];

        require BASE_PATH . '/app/Views/admin/index.php';
    }

    public function media(): void
    {
        require BASE_PATH . '/app/Views/admin/media.php';
    }

    public function settings(): void
    {
        $settings = (new AppSettings())->all();

        require BASE_PATH . '/app/Views/admin/settings.php';
    }

    public function quizConfigV2(): void
    {
        $showModuleTags = (new AppSettings())->all()['show_module_tags'];

        require BASE_PATH . '/app/Views/admin/quiz_config_v2.php';
    }
}