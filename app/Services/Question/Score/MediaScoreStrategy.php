<?php

namespace App\Services\Question\Score;

final class MediaScoreStrategy implements ScoreStrategyInterface
{
    private ClassicScoreStrategy $classic;

    public function __construct()
    {
        $this->classic = new ClassicScoreStrategy();
    }

    public function calculate(array $context): ScoreCalculation
    {
        return $this->classic->calculate($context);
    }
}
