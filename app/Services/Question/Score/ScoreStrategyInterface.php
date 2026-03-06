<?php

namespace App\Services\Question\Score;

interface ScoreStrategyInterface
{
    public function calculate(array $context): ScoreCalculation;
}
