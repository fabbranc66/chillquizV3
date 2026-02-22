<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;

class SessioneService
{
    private $pdo;
    private int $sessioneId;
    private array $sessione;

    public function __construct(int $sessioneId)
    {
        $this->pdo = Database::getInstance();
        $this->sessioneId = $sessioneId;

        $stmt = $this->pdo->prepare("SELECT * FROM sessioni WHERE id = ?");
        $stmt->execute([$sessioneId]);

        $result = $stmt->fetch();

        if (!$result) {
            throw new RuntimeException("Sessione non trovata.");
        }

        $this->sessione = $result;
    }

    /* ===============================
       STATO
    =============================== */

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
        return $this->sessione['stato'] === 'domanda';
    }

    /* ===============================
       TRANSIZIONI
    =============================== */

    public function avviaPuntata(): void
    {
        $this->assertNonConclusa();

        $stato = $this->sessione['stato'];

        if (!in_array($stato, ['attesa', 'risultati'], true)) {
            throw new RuntimeException(
                "Impossibile avviare puntata. Stato attuale: {$stato}"
            );
        }

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

        $timestamp = time();

        $stmt = $this->pdo->prepare("
            UPDATE sessioni
            SET stato = 'domanda',
                inizio_domanda = ?
            WHERE id = ?
        ");

        $stmt->execute([$timestamp, $this->sessioneId]);

        $this->sessione['stato'] = 'domanda';
        $this->sessione['inizio_domanda'] = $timestamp;
    }

    public function chiudiDomanda(): void
    {
        $this->assertNonConclusa();

        if ($this->sessione['stato'] !== 'domanda') {
            throw new RuntimeException(
                "Impossibile chiudere domanda. Stato attuale: {$this->sessione['stato']}"
            );
        }

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

        $stmt = $this->pdo->prepare("
            SELECT numero_domande 
            FROM configurazioni_quiz 
            WHERE id = ?
        ");

        $stmt->execute([$this->sessione['configurazione_id']]);
        $config = $stmt->fetch();

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

    /* ===============================
       RESET TOTALE SESSIONE
    =============================== */

    public function resetTotale(): void
    {
        // Reset sessione
        $stmt = $this->pdo->prepare("
            UPDATE sessioni
            SET stato = 'attesa',
                domanda_corrente = 1,
                inizio_domanda = NULL
            WHERE id = ?
        ");
        $stmt->execute([$this->sessioneId]);

        // Svuota pool domande
        $stmt = $this->pdo->prepare("
            DELETE FROM sessione_domande
            WHERE sessione_id = ?
        ");
        $stmt->execute([$this->sessioneId]);

        // Svuota partecipazioni
        $stmt = $this->pdo->prepare("
            DELETE FROM partecipazioni
            WHERE sessione_id = ?
        ");
        $stmt->execute([$this->sessioneId]);

        // Aggiorna stato interno
        $this->sessione['stato'] = 'attesa';
        $this->sessione['domanda_corrente'] = 1;
        $this->sessione['inizio_domanda'] = null;
    }

    /* ===============================
       TIMER
    =============================== */

    public function verificaTimer(): void
    {
        if ($this->sessione['stato'] !== 'domanda') {
            return;
        }

        $stmt = $this->pdo->query("
            SELECT valore 
            FROM configurazioni_sistema
            WHERE chiave = 'durata_domanda'
            LIMIT 1
        ");

        $config = $stmt->fetch();
        $durata = (int) $config['valore'];

        $inizio = (int) $this->sessione['inizio_domanda'];

        if (!$inizio) {
            return;
        }

        if (time() >= ($inizio + $durata)) {
            $this->chiudiDomanda();
        }
    }

    /* ===============================
       POOL DOMANDE
    =============================== */

    public function generaDomandeSessione(): void
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as totale
            FROM sessione_domande
            WHERE sessione_id = ?
        ");
        $stmt->execute([$this->sessioneId]);
        $check = $stmt->fetch();

        if ($check['totale'] > 0) {
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT numero_domande, pool_tipo, argomento_id, selezione_tipo
            FROM configurazioni_quiz
            WHERE id = ?
        ");

        $stmt->execute([$this->sessione['configurazione_id']]);
        $config = $stmt->fetch();

        if (!$config) {
            throw new RuntimeException("Configurazione quiz non trovata.");
        }

        $numeroDomande = (int)$config['numero_domande'];
        $poolTipo = $config['pool_tipo'];
        $argomentoId = $config['argomento_id'];
        $selezione = $config['selezione_tipo'];

        $query = "SELECT id FROM domande WHERE attiva = 1";
        $params = [];

        if ($poolTipo === 'mono' && $argomentoId) {
            $query .= " AND argomento_id = ?";
            $params[] = $argomentoId;
        }

        $query .= ($selezione === 'random')
            ? " ORDER BY RAND()"
            : " ORDER BY id ASC";

        $query .= " LIMIT " . $numeroDomande;

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $domande = $stmt->fetchAll();

        if (count($domande) < $numeroDomande) {
            throw new RuntimeException("Domande insufficienti per generare la sessione.");
        }

        $posizione = 1;

        foreach ($domande as $d) {
            $stmt = $this->pdo->prepare("
                INSERT INTO sessione_domande
                (sessione_id, domanda_id, posizione)
                VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $this->sessioneId,
                $d['id'],
                $posizione
            ]);

            $posizione++;
        }
    }

    public function domandaCorrente(): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.testo, d.difficolta
            FROM sessione_domande sd
            JOIN domande d ON d.id = sd.domanda_id
            WHERE sd.sessione_id = ?
            AND sd.posizione = ?
            LIMIT 1
        ");

        $stmt->execute([
            $this->sessioneId,
            $this->sessione['domanda_corrente']
        ]);

        $domanda = $stmt->fetch();

        if (!$domanda) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT id, testo
            FROM opzioni
            WHERE domanda_id = ?
            ORDER BY id ASC
        ");

        $stmt->execute([$domanda['id']]);
        $domanda['opzioni'] = $stmt->fetchAll();

        return $domanda;
    }

    public function classifica(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.utente_id,
                u.nome,
                p.capitale_attuale
            FROM partecipazioni p
            JOIN utenti u ON u.id = p.utente_id
            WHERE p.sessione_id = ?
            ORDER BY p.capitale_attuale DESC
        ");

        $stmt->execute([$this->sessioneId]);

        return $stmt->fetchAll();
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