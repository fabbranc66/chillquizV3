<?php

namespace App\Services\Sessione\Traits;

trait TimerTrait
{
    private function revealDurationSeconds(): int
    {
        return 5;
    }

    public function verificaTimer(): void
    {
        if (($this->sessione['stato'] ?? '') !== 'domanda') {
            return;
        }

        $stmt = $this->pdo->query(
            "SELECT valore FROM configurazioni_sistema WHERE chiave = 'durata_domanda' LIMIT 1"
        );

        $config = $stmt->fetch();
        $durata = (int) ($config['valore'] ?? 0);
        $inizio = (float) ($this->sessione['inizio_domanda'] ?? 0);

        if ($inizio <= 0 || $durata <= 0) {
            return;
        }

        $now = round(microtime(true), 3);
        $revealUntil = (float) ($this->sessione['mostra_corretta_fino'] ?? 0);

        if ($revealUntil > 0) {
            if ($now >= $revealUntil) {
                $this->chiudiDomanda();
            }
            return;
        }

        if ($now >= ($inizio + $durata)) {
            $this->avviaRevealCorretta($this->revealDurationSeconds());
        }
    }
}
