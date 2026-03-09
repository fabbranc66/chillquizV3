<?php

namespace App\Services\Question;

final class QuestionMode
{
    public const CLASSIC = 'CLASSIC';
    public const MEDIA = 'MEDIA';
    public const SARABANDA = 'SARABANDA';
    public const IMPOSTORE = 'IMPOSTORE';
    public const MEME = 'MEME';
    public const MAJORITY = 'MAJORITY';
    public const RANDOM_BONUS = 'RANDOM_BONUS';
    public const BOMB = 'BOMB';
    public const CHAOS = 'CHAOS';
    public const AUDIO_PARTY = 'AUDIO_PARTY';
    public const IMAGE_PARTY = 'IMAGE_PARTY';
    public const FADE = 'FADE';

    public static function normalize(?string $value): string
    {
        $candidate = strtoupper(trim((string) $value));

        if ($candidate === '') {
            return self::CLASSIC;
        }

        $allowed = [
            self::CLASSIC,
            self::MEDIA,
            self::SARABANDA,
            self::IMPOSTORE,
            self::MEME,
            self::MAJORITY,
            self::RANDOM_BONUS,
            self::BOMB,
            self::CHAOS,
            self::AUDIO_PARTY,
            self::IMAGE_PARTY,
            self::FADE,
        ];

        return in_array($candidate, $allowed, true) ? $candidate : self::CLASSIC;
    }
}
