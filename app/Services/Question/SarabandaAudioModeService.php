<?php

namespace App\Services\Question;

class SarabandaAudioModeService
{
    private function runtimeDir(): string
    {
        $dir = STORAGE_PATH . '/runtime/sarabanda_audio';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function runtimeFile(int $sessioneId): string
    {
        return $this->runtimeDir() . '/session_' . $sessioneId . '.json';
    }

    public function getRuntimeState(int $sessioneId): ?array
    {
        if ($sessioneId <= 0) {
            return null;
        }

        $file = $this->runtimeFile($sessioneId);
        if (!is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function isReverseEnabledForQuestion(int $sessioneId, int $questionId): bool
    {
        $state = $this->getRuntimeState($sessioneId);
        if (!is_array($state)) {
            return false;
        }

        return (int) ($state['question_id'] ?? 0) === $questionId
            && (int) ($state['reverse_enabled'] ?? 0) === 1;
    }

    public function setReverseEnabledForQuestion(int $sessioneId, int $questionId, bool $enabled): bool
    {
        if ($sessioneId <= 0 || $questionId <= 0) {
            return false;
        }

        $payload = [
            'sessione_id' => $sessioneId,
            'question_id' => $questionId,
            'reverse_enabled' => $enabled ? 1 : 0,
            'updated_at' => round(microtime(true), 3),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            return false;
        }

        return @file_put_contents($this->runtimeFile($sessioneId), $json, LOCK_EX) !== false;
    }
}
