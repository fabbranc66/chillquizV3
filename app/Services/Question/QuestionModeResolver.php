<?php

namespace App\Services\Question;

final class QuestionModeResolver
{
    public function resolveFromRow(array $row): array
    {
        $config = $this->decodeConfig($row['config_json'] ?? null);

        $mediaImagePath = $this->toNullableString($row['media_image_path'] ?? ($config['media_image_path'] ?? null));
        $mediaAudioPath = $this->toNullableString($row['media_audio_path'] ?? ($config['media_audio_path'] ?? null));

        $rawType = $this->toNullableString($row['tipo_domanda'] ?? ($config['tipo_domanda'] ?? null));
        if ($rawType === null && ($mediaImagePath !== null || $mediaAudioPath !== null)) {
            $rawType = QuestionMode::MEDIA;
        }

        $tipoDomanda = QuestionMode::normalize($rawType);

        return [
            'tipo_domanda' => $tipoDomanda,
            'modalita_party' => $this->toNullableString($row['modalita_party'] ?? ($config['modalita_party'] ?? null)),
            'fase_domanda' => $this->normalizeFaseDomanda($row['fase_domanda'] ?? ($config['fase_domanda'] ?? null)),
            'media_image_path' => $mediaImagePath,
            'media_audio_path' => $mediaAudioPath,
            'media_audio_preview_sec' => $this->toNullableInt($row['media_audio_preview_sec'] ?? ($config['media_audio_preview_sec'] ?? null)),
            'media_caption' => $this->toNullableString($row['media_caption'] ?? ($config['media_caption'] ?? null)),
            'config' => $config,
        ];
    }

    private function decodeConfig($raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeFaseDomanda($value): string
    {
        $phase = strtolower(trim((string) $value));
        return $phase === 'intro' ? 'intro' : 'domanda';
    }

    private function toNullableString($value): ?string
    {
        $str = trim((string) $value);
        return $str === '' ? null : $str;
    }

    private function toNullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
