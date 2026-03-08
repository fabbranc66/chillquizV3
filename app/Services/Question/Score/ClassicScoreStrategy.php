<?php

namespace App\Services\Question\Score;

final class ClassicScoreStrategy implements ScoreStrategyInterface
{
    public function calculate(array $context): ScoreCalculation
    {
        $puntata = (int) ($context['puntata'] ?? 0);
        $corretta = (bool) ($context['corretta'] ?? false);

        if (!$corretta) {
            return new ScoreCalculation(-$puntata, 0, 0, 0);
        }

        $difficolta = (float) ($context['difficolta'] ?? 1.0);
        $fattoreVelocita = (float) ($context['fattore_velocita'] ?? 0.0);
        $bonusPrimo = (int) ($context['bonus_primo'] ?? 0);
        $bonusImpostore = (int) ($context['bonus_impostore'] ?? 0);

        $vincitaDifficolta = (int) round($puntata * $difficolta);
        $vincitaVelocita = (int) round($puntata * $fattoreVelocita);
        $punti = $vincitaDifficolta + $vincitaVelocita + $bonusPrimo + $bonusImpostore;

        return new ScoreCalculation($punti, $vincitaDifficolta, $vincitaVelocita, $bonusPrimo, $bonusImpostore);
    }
}
