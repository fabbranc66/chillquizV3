<?php

namespace App\Controllers\Api\Traits;

trait JsonResponseTrait
{
    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function jsonOk(array $data = []): void
    {
        $this->json(array_merge(['success' => true], $data));
    }

    private function jsonFail(string $message): void
    {
        $this->json([
            'success' => false,
            'error' => $message
        ]);
    }
}