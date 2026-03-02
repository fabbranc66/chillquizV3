<?php

namespace App\Controllers\Api\Traits;

trait RequestUtilsTrait
{
    private function requirePost(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->json([
                'success' => false,
                'error' => 'Metodo non consentito'
            ]);
            return false;
        }
        return true;
    }

    private function getRequestHeader(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        if (isset($_SERVER[$key])) {
            return trim((string) $_SERVER[$key]);
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $headerName => $headerValue) {
                if (strcasecmp((string) $headerName, $name) === 0) {
                    return trim((string) $headerValue);
                }
            }
        }

        return null;
    }
}