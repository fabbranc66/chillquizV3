<?php

namespace App\Services\Question\Score;

final class ScoreCalculation
{
    public int $punti;
    public int $vincitaDifficolta;
    public int $vincitaVelocita;
    public int $bonusPrimo;
    public int $bonusImpostore;

    public function __construct(int $punti, int $vincitaDifficolta, int $vincitaVelocita, int $bonusPrimo, int $bonusImpostore = 0)
    {
        $this->punti = $punti;
        $this->vincitaDifficolta = $vincitaDifficolta;
        $this->vincitaVelocita = $vincitaVelocita;
        $this->bonusPrimo = $bonusPrimo;
        $this->bonusImpostore = $bonusImpostore;
    }
}
