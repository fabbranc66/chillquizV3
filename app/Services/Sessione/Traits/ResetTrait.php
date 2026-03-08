<?php

namespace App\Services\Sessione\Traits;

use App\Services\Question\ImpostoreModeService;
use App\Services\Question\MemeModeService;

trait ResetTrait
{
    public function resetTotale(): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE sessioni SET stato = 'attesa', domanda_corrente = 1, inizio_domanda = NULL, mostra_corretta_fino = NULL WHERE id = ?"
        );
        $stmt->execute([$this->sessioneId]);

        $stmt = $this->pdo->prepare('DELETE FROM sessione_domande WHERE sessione_id = ?');
        $stmt->execute([$this->sessioneId]);

        $stmt = $this->pdo->prepare('DELETE FROM partecipazioni WHERE sessione_id = ?');
        $stmt->execute([$this->sessioneId]);

        $this->sessione['stato'] = 'attesa';
        $this->sessione['domanda_corrente'] = 1;
        $this->sessione['inizio_domanda'] = null;
        $this->sessione['mostra_corretta_fino'] = null;

        (new ImpostoreModeService())->clearRuntimeState($this->sessioneId);
        (new MemeModeService())->clearRuntimeState($this->sessioneId);
        $this->svuotaPuntateLive();
        $this->generaDomandeSessione();
    }
}
