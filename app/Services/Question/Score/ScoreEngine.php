<?php

namespace App\Services\Question\Score;

use App\Services\Question\QuestionMode;

final class ScoreEngine
{
    private ClassicScoreStrategy $classic;
    private MediaScoreStrategy $media;

    public function __construct()
    {
        $this->classic = new ClassicScoreStrategy();
        $this->media = new MediaScoreStrategy();
    }

    public function calculate(string $tipoDomanda, array $context): ScoreCalculation
    {
        switch (QuestionMode::normalize($tipoDomanda)) {
            case QuestionMode::MEDIA:
                return $this->media->calculate($context);
            case QuestionMode::CLASSIC:
            case QuestionMode::SARABANDA:
            case QuestionMode::IMPOSTORE:
            case QuestionMode::MEME:
            case QuestionMode::MAJORITY:
            case QuestionMode::RANDOM_BONUS:
            case QuestionMode::BOMB:
            case QuestionMode::CHAOS:
            case QuestionMode::AUDIO_PARTY:
            case QuestionMode::IMAGE_PARTY:
            case QuestionMode::FADE:
            default:
                // Fallback compatibile: tutte le nuove modalita partono dal punteggio classico.
                return $this->classic->calculate($context);
        }
    }
}
