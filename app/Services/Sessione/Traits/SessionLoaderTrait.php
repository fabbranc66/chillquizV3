<?php

namespace App\Services\Sessione\Traits;

use RuntimeException;

trait SessionLoaderTrait
{
    // ✅ Metodo atteso dal codice (refactor/constructor)
    private function loadSessione(): void
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sessioni WHERE id = ?");
        $stmt->execute([$this->sessioneId]);

        $result = $stmt->fetch();

        if (!$result) {
            throw new RuntimeException("Sessione non trovata.");
        }

        $this->sessione = $result;
    }

    // ✅ Alias opzionale (se in futuro vuoi chiamarlo così)
    private function loadSessioneOrFail(int $sessioneId): void
    {
        // Manteniamo comportamento identico, ma ignoriamo il parametro
        // perché l'ID vero è $this->sessioneId (come nel codice originale).
        $this->loadSessione();
    }
}