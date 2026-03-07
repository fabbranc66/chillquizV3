<?php

namespace App\Services\Sessione\Traits;

use RuntimeException;

trait StatoTrait
{
    public function stato(): string
    {
        return $this->sessione['stato'];
    }

    public function assertStato(string $atteso): void
    {
        if ($this->sessione['stato'] !== $atteso) {
            throw new RuntimeException(
                "Operazione non consentita. Stato attuale: {$this->sessione['stato']}"
            );
        }
    }

    private function assertNonConclusa(): void
    {
        if ($this->sessione['stato'] === 'conclusa') {
            throw new RuntimeException("La sessione è conclusa. Operazione non consentita.");
        }
    }

    public function puoPuntare(): bool
    {
        return $this->sessione['stato'] === 'puntata';
    }

    public function puoRispondere(): bool
    {
        if ($this->sessione['stato'] !== 'domanda') {
            return false;
        }

        $inizio = (int) ($this->sessione['inizio_domanda'] ?? 0);
        if ($inizio <= 0) {
            return false;
        }

        if (time() < $inizio) {
            return false;
        }

        return true;
    }

    private function aggiornaStato(string $nuovoStato): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE sessioni
            SET stato = ?
            WHERE id = ?
        ");

        $stmt->execute([$nuovoStato, $this->sessioneId]);
        $this->sessione['stato'] = $nuovoStato;
    }
}
