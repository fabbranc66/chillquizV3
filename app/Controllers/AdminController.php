<?php

namespace App\Controllers;

use App\Models\AppSettings;
use App\Models\Sessione;
use App\Services\Auth\AdminAuthService;

class AdminController
{
    private AdminAuthService $auth;

    public function __construct()
    {
        $this->auth = new AdminAuthService();
    }

    private function requireAuth(): void
    {
        if ($this->auth->isAuthenticated()) {
            return;
        }

        header('Location: ' . chillquiz_public_url('index.php?url=admin/login'));
        exit;
    }

    public function index()
    {
        $this->requireAuth();
        $sessioneModel = new Sessione();
        $corrente = $sessioneModel->corrente();

        $sessioneId = (int) ($corrente['id'] ?? 0);
        $nomeSessione = trim((string) (($corrente['nome_sessione'] ?? $corrente['nome'] ?? $corrente['titolo'] ?? '')));

        $settings = (new AppSettings())->all();
        $showModuleTags = $settings['show_module_tags'];
        $adminLogoPath = ltrim(trim((string) (($settings['configurazioni_sistema']['logo'] ?? ''))), '/');
        $adminUsername = $this->auth->getAuthenticatedUsername();

        require BASE_PATH . '/app/Views/admin/index.php';
    }

    public function media(): void
    {
        $this->requireAuth();
        require BASE_PATH . '/app/Views/admin/media.php';
    }

    public function settings(): void
    {
        $this->requireAuth();
        $settings = (new AppSettings())->all();

        require BASE_PATH . '/app/Views/admin/settings.php';
    }

    public function login(): void
    {
        if ($this->auth->isAuthenticated()) {
            header('Location: ' . chillquiz_public_url('index.php?url=admin/index'));
            exit;
        }

        $error = '';
        $defaultUsername = $this->auth->getLoginUsername();

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if ($this->auth->verifyCredentials($username, $password)) {
                $this->auth->login($username);
                header('Location: ' . chillquiz_public_url('index.php?url=admin/index'));
                exit;
            }

            $error = 'Credenziali non valide';
        }

        require BASE_PATH . '/app/Views/admin/login.php';
    }

    public function logout(): void
    {
        $this->auth->logout();
        header('Location: ' . chillquiz_public_url('index.php?url=admin/login'));
        exit;
    }
}
