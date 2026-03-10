<?php

namespace App\Services\Question;

class SarabandaAudioModeService
{
    public const DEFAULT_FAST_FORWARD_RATE = 5.0;
    public const MIN_FAST_FORWARD_RATE = 2.0;
    public const MAX_FAST_FORWARD_RATE = 5.0;

    private function persistState(int $sessioneId, array $payload): bool
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            return false;
        }

        return @file_put_contents($this->runtimeFile($sessioneId), $json, LOCK_EX) !== false;
    }

    private function normalizeFastForwardRate($rate): float
    {
        $normalized = round((float) $rate, 1);
        if ($normalized < self::MIN_FAST_FORWARD_RATE) {
            return self::MIN_FAST_FORWARD_RATE;
        }
        if ($normalized > self::MAX_FAST_FORWARD_RATE) {
            return self::MAX_FAST_FORWARD_RATE;
        }
        return $normalized;
    }

    private function buildPayload(
        int $sessioneId,
        int $questionId,
        bool $audioEnabled,
        bool $reverseEnabled,
        bool $fastForwardEnabled,
        bool $brokenRecordEnabled,
        ?float $fastForwardRate = null
    ): array
    {
        return [
            'sessione_id' => $sessioneId,
            'question_id' => $questionId,
            'audio_enabled' => $audioEnabled ? 1 : 0,
            'reverse_enabled' => $reverseEnabled ? 1 : 0,
            'fast_forward_enabled' => $fastForwardEnabled ? 1 : 0,
            'broken_record_enabled' => $brokenRecordEnabled ? 1 : 0,
            'fast_forward_rate' => $this->normalizeFastForwardRate($fastForwardRate ?? self::DEFAULT_FAST_FORWARD_RATE),
            'updated_at' => round(microtime(true), 3),
        ];
    }

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

    public function clearRuntimeState(int $sessioneId): bool
    {
        if ($sessioneId <= 0) {
            return false;
        }

        $file = $this->runtimeFile($sessioneId);
        if (!is_file($file)) {
            return true;
        }

        return @unlink($file);
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

    public function isAudioEnabledForQuestion(int $sessioneId, int $questionId): bool
    {
        $state = $this->getRuntimeState($sessioneId);
        if (!is_array($state)) {
            return false;
        }

        return (int) ($state['question_id'] ?? 0) === $questionId
            && (int) ($state['audio_enabled'] ?? 0) === 1;
    }

    public function isFastForwardEnabledForQuestion(int $sessioneId, int $questionId): bool
    {
        $state = $this->getRuntimeState($sessioneId);
        if (!is_array($state)) {
            return false;
        }

        return (int) ($state['question_id'] ?? 0) === $questionId
            && (int) ($state['fast_forward_enabled'] ?? 0) === 1;
    }

    public function isBrokenRecordEnabledForQuestion(int $sessioneId, int $questionId): bool
    {
        $state = $this->getRuntimeState($sessioneId);
        if (!is_array($state)) {
            return false;
        }

        return (int) ($state['question_id'] ?? 0) === $questionId
            && (int) ($state['broken_record_enabled'] ?? 0) === 1;
    }

    public function getFastForwardRateForQuestion(int $sessioneId, int $questionId): float
    {
        $state = $this->getRuntimeState($sessioneId);
        if (!is_array($state) || (int) ($state['question_id'] ?? 0) !== $questionId) {
            return self::DEFAULT_FAST_FORWARD_RATE;
        }

        return $this->normalizeFastForwardRate($state['fast_forward_rate'] ?? self::DEFAULT_FAST_FORWARD_RATE);
    }

    public function setReverseEnabledForQuestion(int $sessioneId, int $questionId, bool $enabled): bool
    {
        if ($sessioneId <= 0 || $questionId <= 0) {
            return false;
        }

        $existingRate = $this->getFastForwardRateForQuestion($sessioneId, $questionId);
        $audioEnabled = $enabled ? true : $this->isAudioEnabledForQuestion($sessioneId, $questionId);
        $payload = $this->buildPayload($sessioneId, $questionId, $audioEnabled, $enabled, false, false, $existingRate);
        return $this->persistState($sessioneId, $payload);
    }

    public function setAudioEnabledForQuestion(int $sessioneId, int $questionId, bool $enabled): bool
    {
        if ($sessioneId <= 0 || $questionId <= 0) {
            return false;
        }

        $existingRate = $this->getFastForwardRateForQuestion($sessioneId, $questionId);
        $reverseEnabled = $enabled ? $this->isReverseEnabledForQuestion($sessioneId, $questionId) : false;
        $fastForwardEnabled = $enabled ? $this->isFastForwardEnabledForQuestion($sessioneId, $questionId) : false;
        $brokenRecordEnabled = $enabled ? $this->isBrokenRecordEnabledForQuestion($sessioneId, $questionId) : false;
        $payload = $this->buildPayload($sessioneId, $questionId, $enabled, $reverseEnabled, $fastForwardEnabled, $brokenRecordEnabled, $existingRate);
        return $this->persistState($sessioneId, $payload);
    }

    public function setFastForwardEnabledForQuestion(int $sessioneId, int $questionId, bool $enabled, ?float $rate = null): bool
    {
        if ($sessioneId <= 0 || $questionId <= 0) {
            return false;
        }

        $existingRate = $this->getFastForwardRateForQuestion($sessioneId, $questionId);
        $audioEnabled = $enabled ? true : $this->isAudioEnabledForQuestion($sessioneId, $questionId);
        $payload = $this->buildPayload($sessioneId, $questionId, $audioEnabled, false, $enabled, false, $rate ?? $existingRate);
        return $this->persistState($sessioneId, $payload);
    }

    public function setBrokenRecordEnabledForQuestion(int $sessioneId, int $questionId, bool $enabled): bool
    {
        if ($sessioneId <= 0 || $questionId <= 0) {
            return false;
        }

        $existingRate = $this->getFastForwardRateForQuestion($sessioneId, $questionId);
        $audioEnabled = $enabled ? true : $this->isAudioEnabledForQuestion($sessioneId, $questionId);
        $payload = $this->buildPayload($sessioneId, $questionId, $audioEnabled, false, false, $enabled, $existingRate);
        return $this->persistState($sessioneId, $payload);
    }

    public function setFastForwardRateForQuestion(int $sessioneId, int $questionId, float $rate): bool
    {
        if ($sessioneId <= 0 || $questionId <= 0) {
            return false;
        }

        $state = $this->getRuntimeState($sessioneId);
        $reverseEnabled = is_array($state)
            && (int) ($state['question_id'] ?? 0) === $questionId
            && (int) ($state['reverse_enabled'] ?? 0) === 1;
        $fastForwardEnabled = is_array($state)
            && (int) ($state['question_id'] ?? 0) === $questionId
            && (int) ($state['fast_forward_enabled'] ?? 0) === 1;
        $brokenRecordEnabled = is_array($state)
            && (int) ($state['question_id'] ?? 0) === $questionId
            && (int) ($state['broken_record_enabled'] ?? 0) === 1;

        $audioEnabled = is_array($state)
            && (int) ($state['question_id'] ?? 0) === $questionId
            && (int) ($state['audio_enabled'] ?? 0) === 1;

        $payload = $this->buildPayload($sessioneId, $questionId, $audioEnabled, $reverseEnabled, $fastForwardEnabled, $brokenRecordEnabled, $rate);
        return $this->persistState($sessioneId, $payload);
    }
}
