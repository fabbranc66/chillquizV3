<?php

namespace App\Services\Question;

class FadeModeService
{
    public function applyRuntimeOverride(int $sessioneId, int $domandaId, array $modeMeta): array
    {
        $tipo = strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? QuestionMode::CLASSIC)));
        if ($tipo === QuestionMode::SARABANDA) {
            return $modeMeta;
        }

        if ($tipo === QuestionMode::FADE || $this->isEnabledForQuestion($sessioneId, $domandaId)) {
            $modeMeta['base_tipo_domanda'] = $tipo;
            $modeMeta['tipo_domanda'] = QuestionMode::FADE;
        }

        return $modeMeta;
    }

    public function isEnabledForQuestion(int $sessioneId, int $domandaId): bool
    {
        if ($sessioneId <= 0 || $domandaId <= 0) {
            return false;
        }

        $state = $this->getRuntimeState($sessioneId);
        return (int) ($state['domanda_id'] ?? 0) === $domandaId && !empty($state['enabled']);
    }

    public function setEnabledForQuestion(int $sessioneId, int $domandaId, bool $enabled): void
    {
        if ($sessioneId <= 0 || $domandaId <= 0) {
            return;
        }

        if (!$enabled) {
            $this->clearRuntimeState($sessioneId);
            return;
        }

        $payload = [
            'sessione_id' => $sessioneId,
            'domanda_id' => $domandaId,
            'enabled' => true,
            'updated_at' => round(microtime(true), 3),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            return;
        }

        @file_put_contents($this->runtimeStateFile($sessioneId), $json, LOCK_EX);
    }

    public function clearRuntimeState(int $sessioneId): void
    {
        if ($sessioneId <= 0) {
            return;
        }

        $file = $this->runtimeStateFile($sessioneId);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public function getRuntimeState(int $sessioneId): ?array
    {
        if ($sessioneId <= 0) {
            return null;
        }

        $file = $this->runtimeStateFile($sessioneId);
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

    private function runtimeStateFile(int $sessioneId): string
    {
        $dir = STORAGE_PATH . '/runtime/fade';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir . '/session_' . $sessioneId . '_current.json';
    }
}
