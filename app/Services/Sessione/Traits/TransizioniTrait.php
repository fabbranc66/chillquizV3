<?php

namespace App\Services\Sessione\Traits;

use App\Models\Partecipazione;
use App\Services\Question\QuestionGameplayRuntimeService;
use RuntimeException;

trait TransizioniTrait
{
    private function transizioniGameplayRuntime(): QuestionGameplayRuntimeService
    {
        return new QuestionGameplayRuntimeService();
    }

    private function persistQuestionStartState(string $state, ?float $timestamp): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE sessioni SET stato = ?, inizio_domanda = ?, mostra_corretta_fino = NULL WHERE id = ?"
        );

        $stmt->execute([$state, $timestamp, $this->sessioneId]);

        $this->sessione['stato'] = $state;
        $this->sessione['inizio_domanda'] = $timestamp;
        $this->sessione['mostra_corretta_fino'] = null;
    }

    private function activateQuestionFromPreview(?float $timestamp = null): void
    {
        $startAt = $timestamp !== null ? $timestamp : (float) ($this->sessione['inizio_domanda'] ?? 0);
        $stmt = $this->pdo->prepare(
            "UPDATE sessioni SET stato = 'domanda', inizio_domanda = ?, mostra_corretta_fino = NULL WHERE id = ?"
        );
        $stmt->execute([$startAt, $this->sessioneId]);

        $this->sessione['stato'] = 'domanda';
        $this->sessione['inizio_domanda'] = $startAt;
        $this->sessione['mostra_corretta_fino'] = null;
    }

    private function resetRevealCorretta(): void
    {
        $stmt = $this->pdo->prepare('UPDATE sessioni SET mostra_corretta_fino = NULL WHERE id = ?');
        $stmt->execute([$this->sessioneId]);
        $this->sessione['mostra_corretta_fino'] = null;
    }

    private function avviaRevealCorretta(int $durataSecondi = 5): void
    {
        $until = round(microtime(true) + max(1, $durataSecondi), 3);

        $stmt = $this->pdo->prepare('UPDATE sessioni SET mostra_corretta_fino = ? WHERE id = ?');
        $stmt->execute([$until, $this->sessioneId]);
        $this->sessione['mostra_corretta_fino'] = $until;
    }

    public function avviaPuntata(): void
    {
        $this->assertNonConclusa();

        $stato = $this->sessione['stato'];

        if (!in_array($stato, ['attesa', 'risultati'], true)) {
            throw new RuntimeException(
                "Impossibile avviare puntata. Stato attuale: {$stato}"
            );
        }

        $this->generaDomandeSessione();
        $this->svuotaPuntateLive();
        $this->resetRevealCorretta();
        $this->aggiornaStato('puntata');
    }

    public function avviaDomanda(): void
    {
        $this->assertNonConclusa();

        if ($this->sessione['stato'] !== 'puntata') {
            throw new RuntimeException(
                "Impossibile avviare domanda. Stato attuale: {$this->sessione['stato']}"
            );
        }

        $domandaCorrente = $this->domandaCorrente();
        $domandaCorrente = is_array($domandaCorrente) ? $domandaCorrente : [];
        $runtime = $this->transizioniGameplayRuntime();
        $modeMeta = $runtime->resolveModeMeta($this->sessioneId, $domandaCorrente);
        $runtime->prepareRuntimeQuestionModes($this->sessioneId, $domandaCorrente, $modeMeta);
        $initialState = $runtime->resolveQuestionInitialState($domandaCorrente, $modeMeta);
        $timestamp = $runtime->resolveQuestionStartTimestamp($domandaCorrente, $modeMeta);
        $this->persistQuestionStartState($initialState, $timestamp);

    }

    public function chiudiDomanda(): void
    {
        $this->assertNonConclusa();

        if ($this->sessione['stato'] !== 'domanda') {
            throw new RuntimeException(
                "Impossibile chiudere domanda. Stato attuale: {$this->sessione['stato']}"
            );
        }

        $partecipazioneModel = new Partecipazione();
        $partecipazioneModel->registraAssenzeRisposta($this->sessioneId, (int) ($this->domandaCorrenteId() ?? 0));
        $partecipazioneModel->ripristinaCapitaleEliminatiFineFase($this->sessioneId);
        $this->svuotaPuntateLive();
        $this->resetRevealCorretta();
        $this->transizioniGameplayRuntime()->clearRuntimeModes($this->sessioneId);
        $this->aggiornaStato('risultati');
    }

    public function prossimaFase(): void
    {
        $this->assertNonConclusa();

        if ($this->sessione['stato'] !== 'risultati') {
            throw new RuntimeException(
                "Impossibile avanzare fase. Stato attuale: {$this->sessione['stato']}"
            );
        }

        $config = $this->loadUnifiedQuizConfig((int) ($this->sessione['configurazione_id'] ?? 0));

        if (!$config) {
            throw new RuntimeException('Configurazione quiz non trovata.');
        }

        $numeroDomande = (int) $config['numero_domande'];
        $domandaCorrente = (int) $this->sessione['domanda_corrente'];

        if ($domandaCorrente < $numeroDomande) {
            $stmt = $this->pdo->prepare(
                "UPDATE sessioni SET domanda_corrente = domanda_corrente + 1, stato = 'puntata', inizio_domanda = NULL, mostra_corretta_fino = NULL WHERE id = ?"
            );

            $stmt->execute([$this->sessioneId]);

            $this->sessione['domanda_corrente']++;
            $this->sessione['stato'] = 'puntata';
            $this->sessione['inizio_domanda'] = null;
            $this->sessione['mostra_corretta_fino'] = null;
            $this->transizioniGameplayRuntime()->clearRuntimeModes($this->sessioneId);
        } else {
            $this->resetRevealCorretta();
            $this->aggiornaStato('conclusa');
            $this->transizioniGameplayRuntime()->clearRuntimeModes($this->sessioneId);
        }
    }
}
