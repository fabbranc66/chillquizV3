<?php

namespace App\Services\Sessione\Traits;

use App\Models\Sistema;
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
            throw new RuntimeException('La sessione e conclusa. Operazione non consentita.');
        }
    }

    public function puoPuntare(): bool
    {
        return $this->sessione['stato'] === 'puntata';
    }

    public function puoRispondere(): bool
    {
        return $this->motivoBloccoRisposta() === null;
    }

    public function motivoBloccoRisposta(): ?string
    {
        if ($this->sessione['stato'] !== 'domanda') {
            return 'non_in_domanda';
        }

        $inizio = (float) ($this->sessione['inizio_domanda'] ?? 0);
        if ($inizio <= 0) {
            return 'domanda_non_attiva';
        }

        $now = round(microtime(true), 3);
        if ($now < $inizio) {
            return 'domanda_non_iniziata';
        }

        $revealUntil = (float) ($this->sessione['mostra_corretta_fino'] ?? 0);
        if ($revealUntil > $now) {
            return 'reveal_attivo';
        }

        $durata = (int) ((new Sistema())->get('durata_domanda') ?? 0);
        if ($durata > 0 && $now >= ($inizio + $durata)) {
            return 'tempo_scaduto';
        }

        return null;
    }

    private function aggiornaStato(string $nuovoStato): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE sessioni
            SET stato = ?
            WHERE id = ?
        ');

        $stmt->execute([$nuovoStato, $this->sessioneId]);
        $this->sessione['stato'] = $nuovoStato;
    }
}
