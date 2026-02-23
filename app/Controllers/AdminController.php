<?php

namespace App\Controllers;

use App\Core\Database;

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

        require BASE_PATH . '/app/Views/admin/index.php';
    }
}