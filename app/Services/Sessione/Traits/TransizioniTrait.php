<?php

namespace App\Services\Sessione\Traits;

use App\Models\Partecipazione;
use RuntimeException;

trait TransizioniTrait
{
    public function avviaPuntata(): void
    {
        $this->assertNonConclusa();

        $stato = $this->sessione['stato'];

        if (!in_array($stato, ['attesa', 'risultati'], true)) {
            throw new RuntimeException(
                "Impossibile avviare puntata. Stato attuale: {$stato}"
            );
        }

        // Se la sessione è stata resettata (o è vuota), rigenera il pool domande.
        $this->generaDomandeSessione();

        $this->svuotaPuntateLive();
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
        $tipoDomanda = strtoupper(trim((string) ($domandaCorrente['tipo_domanda'] ?? 'CLASSIC')));
        $hasAudio = trim((string) ($domandaCorrente['media_audio_path'] ?? '')) !== '';

        if ($tipoDomanda === 'SARABANDA' && $hasAudio) {
            // In SARABANDA la domanda parte in "intro": timer non avviato finché
            // l'admin non lancia l'anteprima audio (che imposta inizio_domanda).
            $timestamp = null;
        }

        $stmt = $this->pdo->prepare("
            UPDATE sessioni
            SET stato = 'domanda',
                inizio_domanda = ?
            WHERE id = ?
        ");

        $stmt->execute([$timestamp, $this->sessioneId]);

        $this->sessione['stato'] = 'domanda';
        $this->sessione['inizio_domanda'] = $timestamp;

        $this->svuotaPuntateLive();
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
        $partecipazioneModel->ripristinaCapitaleEliminatiFineFase($this->sessioneId);

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

        $config = $this->loadUnifiedQuizConfig((int) $this->sessione['configurazione_id']);

        if (!$config) {
            throw new RuntimeException("Configurazione quiz non trovata.");
        }

        $numeroDomande = (int) $config['numero_domande'];
        $domandaCorrente = (int) $this->sessione['domanda_corrente'];

        if ($domandaCorrente < $numeroDomande) {

            $stmt = $this->pdo->prepare("
                UPDATE sessioni
                SET domanda_corrente = domanda_corrente + 1,
                    stato = 'puntata'
                WHERE id = ?
            ");

            $stmt->execute([$this->sessioneId]);

            $this->sessione['domanda_corrente']++;
            $this->sessione['stato'] = 'puntata';

        } else {
            $this->aggiornaStato('conclusa');
        }
    }
}

