<?php

namespace App\Services\Sessione\Traits;

trait ResetTrait
{
    public function resetTotale(): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE sessioni
            SET stato = 'attesa',
                domanda_corrente = 1,
                inizio_domanda = NULL
            WHERE id = ?
        ");
        $stmt->execute([$this->sessioneId]);

        $stmt = $this->pdo->prepare("
            DELETE FROM sessione_domande
            WHERE sessione_id = ?
        ");
        $stmt->execute([$this->sessioneId]);

        $stmt = $this->pdo->prepare("
            DELETE FROM partecipazioni
            WHERE sessione_id = ?
        ");
        $stmt->execute([$this->sessioneId]);

        $this->sessione['stato'] = 'attesa';
        $this->sessione['domanda_corrente'] = 1;
        $this->sessione['inizio_domanda'] = null;

        $this->svuotaPuntateLive();
        $this->generaDomandeSessione();
    }
}
