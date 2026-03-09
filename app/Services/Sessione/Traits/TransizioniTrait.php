<?php

namespace App\Services\Sessione\Traits;

use App\Models\Partecipazione;
use App\Services\Question\ImpostoreModeService;
use App\Services\Question\MemeModeService;
use App\Services\Question\QuestionModeResolver;
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
        $modeMeta = (new QuestionModeResolver())->resolveFromRow($domandaCorrente);
        $modeMeta = (new ImpostoreModeService())->applyRuntimeOverride($this->sessioneId, $domandaId, $modeMeta);
        return (new MemeModeService())->applyRuntimeOverride($this->sessioneId, $domandaId, $modeMeta);
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

        $timestamp = round(microtime(true), 3);
        $domandaCorrente = $this->domandaCorrente();
        $modeMeta = $this->resolveCurrentQuestionModeMeta(is_array($domandaCorrente) ? $domandaCorrente : []);
        $tipoDomanda = strtoupper(trim((string) ($modeMeta['tipo_domanda'] ?? 'CLASSIC')));
        $hasAudio = trim((string) ($domandaCorrente['media_audio_path'] ?? '')) !== '';
        $this->prepareRuntimeQuestionModes(is_array($domandaCorrente) ? $domandaCorrente : [], $modeMeta);

        if ($tipoDomanda === 'SARABANDA' && $hasAudio) {
            $timestamp = null;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE sessioni SET stato = 'domanda', inizio_domanda = ?, mostra_corretta_fino = NULL WHERE id = ?"
        );

        $stmt->execute([$timestamp, $this->sessioneId]);

        $this->sessione['stato'] = 'domanda';
        $this->sessione['inizio_domanda'] = $timestamp;
        $this->sessione['mostra_corretta_fino'] = null;

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
