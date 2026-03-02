<?php

namespace App\Models;

use App\Core\Database;
use App\Models\Sistema;
use PDO;

class Sessione
{
    private PDO $pdo;
    private ?string $sessionNameColumn = null;
    private bool $sessionNameColumnResolved = false;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function crea(int $configurazioneId, ?string $nome = null): int
    {
        $pin = $this->generaPin();
        $nomeSessione = trim((string) $nome);

        $nomeColumn = $this->resolveSessionNameColumn();

        if ($nomeSessione === '') {
            $nomeSessione = 'Sessione ' . date('Y-m-d H:i');
        }

        $params = [
            'configurazione_id' => $configurazioneId,
            'pin' => $pin,
            'creata_il' => time(),
        ];

        if ($nomeColumn !== null) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO sessioni
                 (configurazione_id, pin, {$nomeColumn}, stato, domanda_corrente, creata_il)
                 VALUES
                 (:configurazione_id, :pin, :nome_sessione, 'attesa', 1, :creata_il)"
            );

            $params['nome_sessione'] = $nomeSessione;
        } else {
            $stmt = $this->pdo->prepare(
                "INSERT INTO sessioni
                 (configurazione_id, pin, stato, domanda_corrente, creata_il)
                 VALUES
                 (:configurazione_id, :pin, 'attesa', 1, :creata_il)"
            );
        }

        $stmt->execute($params);

        $sessioneId = (int) $this->pdo->lastInsertId();

        $selezione = new \App\Models\SelezioneDomande();
        $selezione->genera($sessioneId, $configurazioneId);

        return $sessioneId;
    }

    private function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM {$table}");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return in_array($column, $columns, true);
    }

    private function resolveSessionNameColumn(): ?string
    {
        if ($this->sessionNameColumnResolved) {
            return $this->sessionNameColumn;
        }

        $candidates = ['nome_sessione', 'nome', 'titolo'];

        foreach ($candidates as $column) {
            if ($this->hasColumn('sessioni', $column)) {
                $this->sessionNameColumn = $column;
                $this->sessionNameColumnResolved = true;
                return $this->sessionNameColumn;
            }
        }

        $this->sessionNameColumn = null;
        $this->sessionNameColumnResolved = true;
        return null;
    }

    /**
     * Compat legacy: alcuni punti del codice chiamano ancora questo metodo.
     */
    public function hasNomeSessioneColumn(): bool
    {
        return $this->hasColumn('sessioni', 'nome_sessione');
    }

    /**
     * Compat legacy: in passato creava la colonna runtime.
     * Ora è un no-op per evitare alter table in produzione.
     */
    public function ensureNomeSessioneColumn(): void
    {
        // intentionally no-op
    }

    private function generaPin(): string
    {
        do {
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $stmt = $this->pdo->prepare(
                "SELECT id FROM sessioni WHERE pin = :pin LIMIT 1"
            );

            $stmt->execute(['pin' => $pin]);
            $esiste = $stmt->fetch();

        } while ($esiste);

        return $pin;
    }

    public function corrente(): ?array
    {
        $configuredId = (new Sistema())->get('sessione_corrente_id');

        if ($configuredId !== null && ctype_digit((string) $configuredId)) {
            $sessione = $this->trova((int) $configuredId);
            if ($sessione) {
                return $sessione;
            }
        }

        $stmt = $this->pdo->query(
            "SELECT * FROM sessioni ORDER BY id DESC LIMIT 1"
        );

        return $stmt->fetch() ?: null;
    }

    public function impostaCorrente(int $id): bool
    {
        if ($id <= 0 || !$this->trova($id)) {
            return false;
        }

        return (new Sistema())->set('sessione_corrente_id', (string) $id);
    }

    public function disponibili(int $limit = 100): array
    {
        $safeLimit = max(1, min(500, $limit));

        $nomeColumn = $this->resolveSessionNameColumn();
        $select = $nomeColumn !== null
            ? "SELECT id, pin, {$nomeColumn} AS nome_sessione, stato, domanda_corrente, creata_il FROM sessioni ORDER BY id DESC LIMIT :lim"
            : "SELECT id, pin, stato, domanda_corrente, creata_il FROM sessioni ORDER BY id DESC LIMIT :lim";

        $stmt = $this->pdo->prepare($select);
        $stmt->bindValue(':lim', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        if ($nomeColumn === null) {
            foreach ($rows as &$row) {
                $timestamp = isset($row['creata_il']) ? (int) $row['creata_il'] : 0;
                $row['nome_sessione'] = $timestamp > 0
                    ? 'Sessione ' . date('Y-m-d H:i', $timestamp)
                    : '';
            }
            unset($row);
        }

        return $rows;
    }

    public function lista(int $limit = 100): array
    {
        return $this->disponibili($limit);
    }

    public function tutte(int $limit = 100): array
    {
        return $this->disponibili($limit);
    }

    public function trova(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM sessioni WHERE id = :id LIMIT 1"
        );

        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function cambiaStato(int $id, string $stato): bool
    {
        if ($stato === 'domanda') {

            $stmt = $this->pdo->prepare(
                "UPDATE sessioni
                 SET stato = :stato,
                     inizio_domanda = :inizio
                 WHERE id = :id"
            );

            return $stmt->execute([
                'stato' => $stato,
                'inizio' => time(),
                'id' => $id
            ]);
        }

        $stmt = $this->pdo->prepare(
            "UPDATE sessioni
             SET stato = :stato
             WHERE id = :id"
        );

        return $stmt->execute([
            'stato' => $stato,
            'id' => $id
        ]);
    }

    public function avanzaDomanda(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.domanda_corrente, c.numero_domande
             FROM sessioni s
             JOIN configurazioni_quiz c ON c.id = s.configurazione_id
             WHERE s.id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        if (!$data) {
            return;
        }

        $corrente = (int) $data['domanda_corrente'];
        $totale = (int) $data['numero_domande'];

        if ($corrente >= $totale) {

            $update = $this->pdo->prepare(
                "UPDATE sessioni
                 SET stato = 'conclusa'
                 WHERE id = :id"
            );

            $update->execute(['id' => $id]);

        } else {

            $update = $this->pdo->prepare(
                "UPDATE sessioni
                 SET domanda_corrente = domanda_corrente + 1,
                     stato = 'puntata',
                     inizio_domanda = NULL
                 WHERE id = :id"
            );

            $update->execute(['id' => $id]);
        }
    }
}