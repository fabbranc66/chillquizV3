<?php

namespace App\Core;

class Router
{
    public function dispatch(): void
    {
        $url = $_GET['url'] ?? '';

        $url = trim($url, '/');
        $segments = $url !== '' ? explode('/', $url) : [];

        $controllerName = $segments[0] ?? 'game';

        /* ======================
           GESTIONE API
        ====================== */

        if ($controllerName === 'api') {

            header('Content-Type: application/json');

            try {

                $api = new \App\Controllers\ApiController();

                // api/admin/azione/id
                if (($segments[1] ?? '') === 'admin') {

                    $params = array_slice($segments, 2);
                    $api->admin(...$params);
                    return;
                }

                // api/metodo/param1/param2/...
                $method = $segments[1] ?? 'stato';
                $params = array_slice($segments, 2);

                if (!method_exists($api, $method)) {
                    $this->apiAbort(404, 'API metodo non trovato');
                    return;
                }

                $api->$method(...$params);
                return;

            } catch (\Throwable $e) {

                $this->apiAbort(500, $e->getMessage());
                return;
            }
        }

        /* ======================
           ROUTING STANDARD
        ====================== */

// Caso speciale: player/{id} e screen/{id}
if (($controllerName === 'player' || $controllerName === 'screen') && isset($segments[1]) && is_numeric($segments[1])) {
    $methodName = 'index';
    $params = [(int)$segments[1]];
} else {
    $methodName = $segments[1] ?? 'index';
    $params     = array_slice($segments, 2);
}
        $controllerClass = $this->resolveController($controllerName);

        if (!$controllerClass) {
            $this->abort(404, 'Controller non trovato');
            return;
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $methodName)) {
            $this->abort(404, 'Metodo non trovato');
            return;
        }

        $controller->$methodName(...$params);
    }

    private function resolveController(string $name): ?string
    {
        $map = [
            'admin'     => 'App\\Controllers\\AdminController',
            'game'      => 'App\\Controllers\\GameController',
            'player'    => 'App\\Controllers\\PlayerController',
            'screen'    => 'App\\Controllers\\ScreenController',
        ];

        return $map[$name] ?? null;
    }

    private function abort(int $code, string $message): void
    {
        http_response_code($code);
        echo $message;
        exit;
    }

    private function apiAbort(int $code, string $message): void
    {
        http_response_code($code);

        echo json_encode([
            'success' => false,
            'error'   => $message
        ]);

        exit;
    }
}
