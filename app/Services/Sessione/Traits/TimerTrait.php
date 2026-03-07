<?php

namespace App\Services\Sessione\Traits;

trait TimerTrait
{
    public function verificaTimer(): void
    {
        if ($this->sessione['stato'] !== 'domanda') {
            return;
        }

        $stmt = $this->pdo->query("
            SELECT valore
            FROM configurazioni_sistema
            WHERE chiave = 'durata_domanda'
            LIMIT 1
        ");

        $config = $stmt->fetch();
        $durata = (int) $config['valore'];

        $inizio = (float) $this->sessione['inizio_domanda'];

        if (!$inizio) {
            return;
        }

        if (microtime(true) >= ($inizio + $durata)) {
            $this->chiudiDomanda();
        }
    }
}
