<?php

namespace App\Services\Question;

use App\Core\Database;
use PDO;

class ImpostoreModeService
{
    private const DEFAULT_BONUS_MULTIPLIER = 0.5;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function assignForQuestion(int $sessioneId, int $domandaId): ?array
    {
        if ($sessioneId <= 0 || $domandaId <= 0) {
            return null;
        }

        $existing = $this->getAssignment($sessioneId, $domandaId);
        if ($existing) {
            return $existing;
        }

        $stmt = $this->pdo->prepare(
            "SELECT id
             FROM partecipazioni
             WHERE sessione_id = :sessione_id
             ORDER BY id ASC"
        );
        $stmt->execute(['sessione_id' => $sessioneId]);
        $rows = $stmt->fetchAll() ?: [];

        if ($rows === []) {
            return null;
        }

        $ids = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $rows);
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return null;
        }

        $selectedIndex = random_int(0, count($ids) - 1);
        $payload = [
            'sessione_id' => $sessioneId,
            'domanda_id' => $domandaId,
            'impostore_partecipazione_id' => $ids[$selectedIndex],
            'assigned_at' => round(microtime(true), 3),
        ];

        $this->writeAssignment($sessioneId, $domandaId, $payload);
        return $payload;
    }

    public function applyRuntimeOverride(int $sessioneId, int $domandaId, array $modeMeta): array
    {
        $tipo = strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? QuestionMode::CLASSIC)));
        if ($tipo === QuestionMode::SARABANDA) {
            return $modeMeta;
        }

        if ($tipo === QuestionMode::IMPOSTORE || $this->isEnabledForQuestion($sessioneId, $domandaId)) {
            $modeMeta['base_tipo_domanda'] = $tipo;
            $modeMeta['tipo_domanda'] = QuestionMode::IMPOSTORE;
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

    public function getAssignment(int $sessioneId, int $domandaId): ?array
    {
        if ($sessioneId <= 0 || $domandaId <= 0) {
            return null;
        }

        $file = $this->assignmentFile($sessioneId, $domandaId);
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

    public function decorateQuestionForViewer(
        int $sessioneId,
        array $domanda,
        string $viewer = 'generic',
        ?int $partecipazioneId = null
    ): array {
        $tipo = strtoupper(trim((string) ($domanda['tipo_domanda'] ?? 'CLASSIC')));
        if ($tipo !== QuestionMode::IMPOSTORE) {
            return $domanda;
        }

        $domandaId = (int) ($domanda['id'] ?? 0);
        $assignment = $this->getAssignment($sessioneId, $domandaId);
        $impostorePartecipazioneId = (int) ($assignment['impostore_partecipazione_id'] ?? 0);
        $isImpostore = $partecipazioneId !== null && $partecipazioneId > 0 && $impostorePartecipazioneId === $partecipazioneId;
        $viewerKey = strtolower(trim($viewer));
        $shouldMask = $viewerKey === 'screen' || ($viewerKey === 'player' && $isImpostore);

        $domanda['impostore_mode'] = true;
        $domanda['is_impostore'] = $isImpostore;
        $domanda['impostore_assigned'] = $impostorePartecipazioneId > 0;
        $domanda['impostore_masked'] = $shouldMask;
        $domanda['impostore_notice'] = $shouldMask
            ? 'Sei l\'impostore: osserva gli altri e prova a dedurre la risposta.'
            : 'Rispondi normalmente: tra i giocatori c\'e un impostore.';
        $domanda['impostore_screen_notice'] = 'Modalita IMPOSTORE: lo schermo non mostra la domanda.';

        if (!$shouldMask) {
            return $domanda;
        }

        $domanda['testo'] = '';
        $domanda['media_image_path'] = null;
        $domanda['media_audio_path'] = null;
        $domanda['media_audio_preview_sec'] = null;
        $domanda['media_caption'] = null;

        return $domanda;
    }

    public function calculateBonus(array $questionMeta, int $puntata, bool $corretta, bool $isImpostore): int
    {
        if (!$corretta || !$isImpostore || $puntata <= 0) {
            return 0;
        }

        $config = is_array($questionMeta['config'] ?? null) ? $questionMeta['config'] : [];
        $flat = (int) ($config['impostore_bonus_flat'] ?? 0);
        if ($flat > 0) {
            return $flat;
        }

        $multiplier = (float) ($config['impostore_bonus_multiplier'] ?? self::DEFAULT_BONUS_MULTIPLIER);
        if (!is_finite($multiplier) || $multiplier <= 0) {
            $multiplier = self::DEFAULT_BONUS_MULTIPLIER;
        }

        return (int) round($puntata * $multiplier);
    }

    private function assignmentFile(int $sessioneId, int $domandaId): string
    {
        $dir = STORAGE_PATH . '/runtime/impostore';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir . '/session_' . $sessioneId . '_domanda_' . $domandaId . '.json';
    }

    private function runtimeStateFile(int $sessioneId): string
    {
        $dir = STORAGE_PATH . '/runtime/impostore';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir . '/session_' . $sessioneId . '_current.json';
    }

    private function writeAssignment(int $sessioneId, int $domandaId, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            return;
        }

        @file_put_contents($this->assignmentFile($sessioneId, $domandaId), $json, LOCK_EX);
    }
}
