<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Partecipazione;
use App\Models\Sistema;
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

    /* ===============================
       RESET TOTALE SESSIONE
    =============================== */

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

        $config = $this->loadUnifiedQuizConfig((int) $this->sessione['configurazione_id']);

        if (!$config) {
            throw new RuntimeException("Configurazione quiz non trovata.");
        }

        $numeroDomande = (int) $config['numero_domande'];

        if (($config['source'] ?? '') === 'v2' && ($config['modalita'] ?? '') === 'manuale_domande_argomento_corrente') {
            $domande = $this->loadManualV2Questions((int) $this->sessione['configurazione_id']);

            if (count($domande) < $numeroDomande) {
                throw new RuntimeException("Domande manuali insufficienti per generare la sessione.");
            }

            $domande = array_slice($domande, 0, $numeroDomande);
            $this->persistSessionQuestions($domande);
            return;
        }

        $poolTipo = $config['pool_tipo'] ?? 'tutti';
        $argomentoId = $config['argomento_id'] ?? null;
        $selezione = $config['selezione_tipo'] ?? 'random';

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

        $this->persistSessionQuestions($domande);
    }

    private function persistSessionQuestions(array $domande): void
    {
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

    private function loadUnifiedQuizConfig(int $configurazioneId): ?array
    {
        // Le sessioni correnti nascono dalla tabella legacy `configurazioni_quiz`.
        // Quindi priorità legacy per non rompere il flusso esistente quando ci sono ID uguali tra legacy e v2.
        $stmt = $this->pdo->prepare("
            SELECT numero_domande, pool_tipo, argomento_id, selezione_tipo
            FROM configurazioni_quiz
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$configurazioneId]);
        $legacy = $stmt->fetch();

        if ($legacy) {
            return [
                'source' => 'legacy',
                'numero_domande' => (int) $legacy['numero_domande'],
                'pool_tipo' => $legacy['pool_tipo'] ?? 'tutti',
                'argomento_id' => $legacy['argomento_id'] !== null ? (int) $legacy['argomento_id'] : null,
                'selezione_tipo' => $legacy['selezione_tipo'] ?? 'random',
                'modalita' => null,
            ];
        }

        // Fallback v2: utile se in futuro verranno create sessioni che puntano direttamente a configurazioni_v2.
        $stmt = $this->pdo->prepare("
            SELECT id, numero_domande, modalita, argomento_id, selezione_tipo
            FROM configurazioni_quiz_v2
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$configurazioneId]);
        $v2 = $stmt->fetch();

        if (!$v2) {
            return null;
        }

        $poolTipo = 'tutti';
        if (
            ($v2['modalita'] ?? '') === 'manuale_argomento'
            || ($v2['modalita'] ?? '') === 'auto_pool_argomento_corrente'
            || ($v2['modalita'] ?? '') === 'manuale_domande_argomento_corrente'
            || ($v2['modalita'] ?? '') === 'auto'
            || ($v2['modalita'] ?? '') === 'manuale'
        ) {
            $poolTipo = 'mono';
        }

        return [
            'source' => 'v2',
            'numero_domande' => (int) $v2['numero_domande'],
            'pool_tipo' => $poolTipo,
            'argomento_id' => $v2['argomento_id'] !== null ? (int) $v2['argomento_id'] : null,
            'selezione_tipo' => $v2['selezione_tipo'] ?? 'random',
            'modalita' => $v2['modalita'] ?? 'mista',
        ];
    }

    private function loadManualV2Questions(int $configurazioneId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.id
            FROM configurazioni_quiz_v2_domande qd
            JOIN domande d ON d.id = qd.domanda_id
            WHERE qd.configurazione_id = :configurazione_id
              AND d.attiva = 1
            ORDER BY qd.posizione ASC
        ");

        $stmt->execute(['configurazione_id' => $configurazioneId]);
        return $stmt->fetchAll();
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

    public function salvaPuntataLive(int $partecipazioneId, int $importo): void
    {
        $this->ensurePuntateLiveTable();

        $stmt = $this->pdo->prepare("
            INSERT INTO puntate_live (sessione_id, partecipazione_id, importo, aggiornato_il)
            VALUES (:sessione_id, :partecipazione_id, :importo, :aggiornato_il)
            ON DUPLICATE KEY UPDATE
                importo = VALUES(importo),
                aggiornato_il = VALUES(aggiornato_il)
        ");

        $stmt->execute([
            'sessione_id' => $this->sessioneId,
            'partecipazione_id' => $partecipazioneId,
            'importo' => $importo,
            'aggiornato_il' => time(),
        ]);
    }

    public function rimuoviPuntataLive(int $partecipazioneId): void
    {
        $this->ensurePuntateLiveTable();

        $stmt = $this->pdo->prepare("
            DELETE FROM puntate_live
            WHERE sessione_id = :sessione_id
              AND partecipazione_id = :partecipazione_id
        ");

        $stmt->execute([
            'sessione_id' => $this->sessioneId,
            'partecipazione_id' => $partecipazioneId,
        ]);
    }

    public function classifica(): array
    {
        $this->ensurePuntateLiveTable();
        $domandaId = $this->domandaCorrenteId();
        $durataDomanda = (int) ((new Sistema())->get('durata_domanda') ?? 0);

        $stmt = $this->pdo->prepare("
            SELECT
                p.id AS partecipazione_id,
                p.utente_id,
                u.nome,
                p.capitale_attuale,
                COALESCE(pl.importo, r_corrente.puntata, 0) AS ultima_puntata,
                r_corrente.corretta AS esito_corretta,
                r_corrente.tempo_risposta,
                r_corrente.punti AS vincita_domanda,
                d_corrente.difficolta AS difficolta_domanda,
                primo.partecipazione_id AS primo_partecipazione_id
            FROM partecipazioni p
            JOIN utenti u ON u.id = p.utente_id
            LEFT JOIN puntate_live pl
                ON pl.sessione_id = p.sessione_id
               AND pl.partecipazione_id = p.id
            LEFT JOIN (
                SELECT r1.partecipazione_id, r1.puntata, r1.corretta, r1.tempo_risposta, r1.punti
                FROM risposte r1
                INNER JOIN (
                    SELECT partecipazione_id, MAX(id) AS max_id
                    FROM risposte
                    WHERE domanda_id = :domanda_id_risposte
                    GROUP BY partecipazione_id
                ) r2 ON r2.max_id = r1.id
            ) r_corrente ON r_corrente.partecipazione_id = p.id
            LEFT JOIN domande d_corrente ON d_corrente.id = :domanda_id_domanda
            LEFT JOIN (
                SELECT r0.partecipazione_id
                FROM risposte r0
                WHERE r0.domanda_id = :domanda_id_primo
                ORDER BY r0.tempo_risposta ASC, r0.id ASC
                LIMIT 1
            ) primo ON 1 = 1
            WHERE p.sessione_id = :sessione_id
            ORDER BY
                CASE WHEN r_corrente.tempo_risposta IS NULL THEN 1 ELSE 0 END ASC,
                r_corrente.tempo_risposta ASC,
                p.capitale_attuale DESC
        ");

        $stmt->execute([
            'domanda_id_risposte' => $domandaId,
            'domanda_id_domanda' => $domandaId,
            'domanda_id_primo' => $domandaId,
            'sessione_id' => $this->sessioneId,
        ]);

        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['ultima_puntata'] = (int) ($row['ultima_puntata'] ?? 0);
            $row['esito'] = ($row['esito_corretta'] === null)
                ? null
                : ((int) $row['esito_corretta'] === 1 ? 'corretta' : 'errata');
            $row['tempo_risposta'] = $row['tempo_risposta'] === null ? null : (int) $row['tempo_risposta'];
            $row['vincita_domanda'] = $row['vincita_domanda'] === null ? null : (int) $row['vincita_domanda'];
            $row['difficolta_domanda'] = $row['difficolta_domanda'] === null ? null : (float) $row['difficolta_domanda'];
            $row['durata_domanda'] = $durataDomanda;
            $row['primo_a_rispondere'] = isset($row['primo_partecipazione_id'])
                ? ((int) $row['primo_partecipazione_id'] === (int) $row['partecipazione_id'])
                : false;
            unset($row['esito_corretta']);
            unset($row['primo_partecipazione_id']);
        }
        unset($row);

        return $rows;
    }

    private function domandaCorrenteId(): int
    {
        $stmt = $this->pdo->prepare("
            SELECT sd.domanda_id
            FROM sessione_domande sd
            WHERE sd.sessione_id = ?
              AND sd.posizione = ?
            LIMIT 1
        ");

        $stmt->execute([
            $this->sessioneId,
            $this->sessione['domanda_corrente'],
        ]);

        $row = $stmt->fetch();

        return (int) ($row['domanda_id'] ?? 0);
    }

    private function svuotaPuntateLive(): void
    {
        $this->ensurePuntateLiveTable();

        $stmt = $this->pdo->prepare("
            DELETE FROM puntate_live
            WHERE sessione_id = ?
        ");

        $stmt->execute([$this->sessioneId]);
    }

    private function ensurePuntateLiveTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS puntate_live (
                sessione_id INT NOT NULL,
                partecipazione_id INT NOT NULL,
                importo INT NOT NULL,
                aggiornato_il INT NOT NULL,
                PRIMARY KEY (sessione_id, partecipazione_id),
                KEY idx_sessione (sessione_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
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
