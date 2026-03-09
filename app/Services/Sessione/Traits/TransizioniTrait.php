<?php

namespace App\Services\Sessione\Traits;

use App\Models\Partecipazione;
use App\Services\Question\ImpostoreModeService;
use App\Services\Question\MemeModeService;
use App\Services\Question\QuestionRuntimeModeService;
use RuntimeException;

trait TransizioniTrait
{
    private function clearRuntimeModes(): void
    {
        (new ImpostoreModeService())->clearRuntimeState($this->sessioneId);
        (new MemeModeService())->clearRuntimeState($this->sessioneId);
    }

    private function resolveCurrentQuestionModeMeta(array $domandaCorrente): array
    {
        $domandaId = (int) ($domandaCorrente['id'] ?? 0);
        return (new QuestionRuntimeModeService())->resolveFromRow($this->sessioneId, $domandaId, $domandaCorrente);
    }

    private function prepareRuntimeQuestionModes(array $domandaCorrente, array $modeMeta): void
    {
        $domandaId = (int) ($domandaCorrente['id'] ?? 0);
        $tipoDomanda = strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? 'CLASSIC')));

        if ($tipoDomanda === 'IMPOSTORE') {
            (new ImpostoreModeService())->assignForQuestion($this->sessioneId, $domandaId);
        }

        if ($tipoDomanda === 'MEME') {
            (new MemeModeService())->prepareForQuestion($this->sessioneId, $domandaId);
        }
    }

    private function resolveQuestionStartTimestamp(array $domandaCorrente, array $modeMeta): ?float
    {
        $tipoDomanda = strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? 'CLASSIC')));
        $hasAudio = trim((string) ($domandaCorrente['media_audio_path'] ?? '')) !== '';

        if ($tipoDomanda === 'SARABANDA' && $hasAudio) {
            return null;
        }

        return round(microtime(true), 3);
    }

    private function persistQuestionStartState(?float $timestamp): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE sessioni SET stato = 'domanda', inizio_domanda = ?, mostra_corretta_fino = NULL WHERE id = ?"
        );

        $stmt->execute([$timestamp, $this->sessioneId]);

        $this->sessione['stato'] = 'domanda';
        $this->sessione['inizio_domanda'] = $timestamp;
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
        $this->clearRuntimeModes();
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
        $modeMeta = $this->resolveCurrentQuestionModeMeta(is_array($domandaCorrente) ? $domandaCorrente : []);
        $this->prepareRuntimeQuestionModes(is_array($domandaCorrente) ? $domandaCorrente : [], $modeMeta);
        $timestamp = $this->resolveQuestionStartTimestamp(
            is_array($domandaCorrente) ? $domandaCorrente : [],
            $modeMeta
        );
        $this->persistQuestionStartState($timestamp);

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
            $this->clearRuntimeModes();
        } else {
            $this->resetRevealCorretta();
            $this->aggiornaStato('conclusa');
            $this->clearRuntimeModes();
        }
    }
}
