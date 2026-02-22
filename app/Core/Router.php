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
           GESTIONE API SPECIALE
        ====================== */

        if ($controllerName === 'api') {

            $api = new \App\Controllers\ApiController();

            // Caso api/admin/azione/id
            if (($segments[1] ?? '') === 'admin') {

                $action = $segments[2] ?? '';
                $id = isset($segments[3]) ? (int) $segments[3] : 0;

                $api->admin($action, $id);
                return;
            }

            // Caso api/stato/13
            $method = $segments[1] ?? 'stato';
            $id = isset($segments[2]) ? (int) $segments[2] : 0;

            if (!method_exists($api, $method)) {
                $this->abort(404, 'API metodo non trovato');
                return;
            }

            $api->$method($id);
            return;
        }

        /* ======================
           ROUTING STANDARD
        ====================== */

        $methodName = $segments[1] ?? 'index';
        $params     = array_slice($segments, 2);

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

        call_user_func_array([$controller, $methodName], $params);
    }

    private function resolveController(string $name): ?string
    {
        $map = [
            'admin'     => 'App\\Controllers\\AdminController',
            'api'       => 'App\\Controllers\\ApiController',
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
}