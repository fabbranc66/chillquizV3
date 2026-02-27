<?php

namespace App\Controllers;

use App\Core\Database;
use App\Models\AppSettings;

class AdminController
{
    public function index()
    {
        $pdo = Database::getInstance();

        // Prende l'ultima sessione creata
        $stmt = $pdo->query("
            SELECT id
            FROM sessioni
            ORDER BY id DESC
            LIMIT 1
        ");

        $row = $stmt->fetch();

        $sessioneId = $row['id'] ?? 0;

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
