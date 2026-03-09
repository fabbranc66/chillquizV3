<?php

namespace App\Services\Question;

use App\Core\Database;
use PDO;

class MemeModeService
{
    public const ROTATION_MS = 250;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function applyRuntimeOverride(int $sessioneId, int $domandaId, array $modeMeta): array
    {
        $tipo = strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? QuestionMode::CLASSIC)));
        if ($tipo === QuestionMode::SARABANDA) {
            return $modeMeta;
        }

        if ($tipo === QuestionMode::MEME || $this->isEnabledForQuestion($sessioneId, $domandaId)) {
            $modeMeta['base_tipo_domanda'] = $tipo;
            $modeMeta['tipo_domanda'] = QuestionMode::MEME;
        }

        return $modeMeta;
    }

    public function isEnabledForQuestion(int $sessioneId, int $domandaId): bool
    {
        $state = $this->getRuntimeState($sessioneId);
        return $sessioneId > 0
            && $domandaId > 0
            && is_array($state)
            && !empty($state['enabled'])
            && (int) ($state['domanda_id'] ?? 0) === $domandaId;
    }

    public function setEnabledForQuestion(int $sessioneId, int $domandaId, bool $enabled, string $memeText = ''): void
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
            'meme_text' => trim($memeText),
            'display_wrong_option_id' => 0,
            'updated_at' => round(microtime(true), 3),
        ];
        $this->writeRuntimeState($sessioneId, $payload);
    }

    public function prepareForQuestion(int $sessioneId, int $domandaId): ?array
    {
        $state = $this->getRuntimeState($sessioneId);
        if (!is_array($state) || (int) ($state['domanda_id'] ?? 0) !== $domandaId || empty($state['enabled'])) {
            return null;
        }

        if ((int) ($state['display_wrong_option_id'] ?? 0) > 0) {
            return $state;
        }

        $stmt = $this->pdo->prepare(
            "SELECT id
             FROM opzioni
             WHERE domanda_id = :domanda_id
               AND corretta = 0
             ORDER BY id ASC"
        );
        $stmt->execute(['domanda_id' => $domandaId]);
        $rows = $stmt->fetchAll() ?: [];
        $ids = array_values(array_filter(array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $rows)));
        if ($ids === []) {
            return $state;
        }

        $state['display_wrong_option_id'] = $ids[random_int(0, count($ids) - 1)];
        $this->writeRuntimeState($sessioneId, $state);
        return $state;
    }

    public function decorateQuestion(array $domanda, int $sessioneId): array
    {
        $domandaId = (int) ($domanda['id'] ?? 0);
        $tipo = strtoupper(trim((string) ($domanda['tipo_domanda'] ?? 'CLASSIC')));
        if ($domandaId <= 0 || $tipo !== QuestionMode::MEME) {
            return $domanda;
        }

        $state = $this->prepareForQuestion($sessioneId, $domandaId);
        $memeText = trim((string) ($state['meme_text'] ?? ''));
        $displayWrongOptionId = (int) ($state['display_wrong_option_id'] ?? 0);
        $rotationMs = self::ROTATION_MS;

        foreach (($domanda['opzioni'] ?? []) as $index => $opzione) {
            $optionId = (int) ($opzione['id'] ?? 0);
            $isMemeDisplay = $displayWrongOptionId > 0 && $optionId === $displayWrongOptionId && $memeText !== '';
            $domanda['opzioni'][$index]['display_text'] = $isMemeDisplay
                ? $memeText
                : (string) ($opzione['testo'] ?? '');
            $domanda['opzioni'][$index]['is_meme_display'] = $isMemeDisplay;
            $domanda['opzioni'][$index]['letter_slot'] = $index;
        }

        $domanda['meme_mode'] = true;
        $domanda['meme_rotation_ms'] = $rotationMs;
        $domanda['meme_text'] = $memeText;
        $domanda['meme_display_wrong_option_id'] = $displayWrongOptionId;
        $domanda['meme_player_notice'] = 'Modalita MEME: le lettere A/B/C/D cambiano associazione ogni 0,25 secondi.';
        $domanda['meme_screen_notice'] = 'Modalita MEME attiva.';

        return $domanda;
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

    private function runtimeStateFile(int $sessioneId): string
    {
        $dir = STORAGE_PATH . '/runtime/meme';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir . '/session_' . $sessioneId . '_current.json';
    }

    private function writeRuntimeState(int $sessioneId, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            return;
        }

        @file_put_contents($this->runtimeStateFile($sessioneId), $json, LOCK_EX);
    }
}
