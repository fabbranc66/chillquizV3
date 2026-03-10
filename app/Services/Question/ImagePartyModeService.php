<?php

namespace App\Services\Question;

class ImagePartyModeService
{
    private ?string $lastError = null;

    public function applyRuntimeOverride(int $sessioneId, int $domandaId, array $modeMeta): array
    {
        $tipo = strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? QuestionMode::CLASSIC)));
        if ($tipo === QuestionMode::SARABANDA) {
            return $modeMeta;
        }

        if ($tipo === QuestionMode::IMAGE_PARTY || $this->isEnabledForQuestion($sessioneId, $domandaId)) {
            $modeMeta['base_tipo_domanda'] = $tipo;
            $modeMeta['tipo_domanda'] = QuestionMode::IMAGE_PARTY;
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

    public function setEnabledForQuestion(int $sessioneId, int $domandaId, bool $enabled): bool
    {
        $this->lastError = null;

        if ($sessioneId <= 0 || $domandaId <= 0) {
            $this->lastError = 'Sessione o domanda non valida';
            return false;
        }

        if (!$enabled) {
            return $this->clearRuntimeState($sessioneId);
        }

        $payload = [
            'sessione_id' => $sessioneId,
            'domanda_id' => $domandaId,
            'enabled' => true,
            'updated_at' => round(microtime(true), 3),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            $this->lastError = 'Impossibile serializzare lo stato PIXELATE';
            return false;
        }

        $file = $this->runtimeStateFile($sessioneId);
        if ($file === '') {
            return false;
        }

        $written = @file_put_contents($file, $json, LOCK_EX);
        if ($written === false) {
            $this->lastError = 'Impossibile scrivere lo stato PIXELATE su storage/runtime/image_party';
            error_log('[ImagePartyModeService] ' . $this->lastError . ' (sessione ' . $sessioneId . ')');
            return false;
        }

        return true;
    }

    public function clearRuntimeState(int $sessioneId): bool
    {
        $this->lastError = null;

        if ($sessioneId <= 0) {
            $this->lastError = 'Sessione non valida';
            return false;
        }

        $file = $this->runtimeStateFile($sessioneId);
        if ($file === '') {
            return false;
        }

        if (!is_file($file)) {
            return true;
        }

        $deleted = @unlink($file);
        if (!$deleted && is_file($file)) {
            $this->lastError = 'Impossibile rimuovere lo stato PIXELATE';
            error_log('[ImagePartyModeService] ' . $this->lastError . ' (sessione ' . $sessioneId . ')');
            return false;
        }

        return true;
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

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    private function runtimeStateFile(int $sessioneId): string
    {
        $dir = STORAGE_PATH . '/runtime/image_party';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->lastError = 'Impossibile creare la cartella storage/runtime/image_party';
            error_log('[ImagePartyModeService] ' . $this->lastError);
            return '';
        }

        return $dir . '/session_' . $sessioneId . '_current.json';
    }
}
