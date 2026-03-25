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
        $this->ensureMaxPerArgomentoColumn();
    }

    public function crea(int $configurazioneId, ?string $nome = null, array $sessionConfig = []): int
    {
        $pin = $this->generaPin();
        $nomeSessione = trim((string) $nome);
        $configSnapshot = $this->buildSessionConfigSnapshot($configurazioneId, $sessionConfig);

        $nomeColumn = $this->resolveSessionNameColumn();

        if ($nomeSessione === '') {
            $nomeSessione = 'Sessione ' . date('Y-m-d H:i');
        }

        $params = [
            'numero_domande' => $configSnapshot['numero_domande'],
            'pool_tipo' => $configSnapshot['pool_tipo'],
            'argomento_id' => $configSnapshot['argomento_id'],
            'selezione_tipo' => $configSnapshot['selezione_tipo'],
            'max_per_argomento' => $configSnapshot['max_per_argomento'],
            'pin' => $pin,
            'creata_il' => time(),
        ];

        if ($nomeColumn !== null) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO sessioni
                 (numero_domande, pool_tipo, argomento_id, selezione_tipo, max_per_argomento, pin, {$nomeColumn}, stato, domanda_corrente, creata_il)
                 VALUES
                 (:numero_domande, :pool_tipo, :argomento_id, :selezione_tipo, :max_per_argomento, :pin, :nome_sessione, 'attesa', 1, :creata_il)"
            );

            $params['nome_sessione'] = $nomeSessione;
        } else {
            $stmt = $this->pdo->prepare(
                "INSERT INTO sessioni
                 (numero_domande, pool_tipo, argomento_id, selezione_tipo, max_per_argomento, pin, stato, domanda_corrente, creata_il)
                 VALUES
                 (:numero_domande, :pool_tipo, :argomento_id, :selezione_tipo, :max_per_argomento, :pin, 'attesa', 1, :creata_il)"
            );
        }

        $stmt->execute($params);

        $sessioneId = (int) $this->pdo->lastInsertId();

        $selezione = new \App\Models\SelezioneDomande();
        $selezione->genera($sessioneId, $configurazioneId);

        return $sessioneId;
    }

    private function buildSessionConfigSnapshot(int $configurazioneId, array $input): array
    {
        $base = $this->loadConfigSnapshot($configurazioneId);

        $numeroDomande = isset($input['numero_domande']) ? (int) $input['numero_domande'] : $base['numero_domande'];
        if ($numeroDomande <= 0) {
            $numeroDomande = $base['numero_domande'];
        }

        $poolRaw = trim((string) ($input['pool_tipo'] ?? $base['pool_tipo']));
        if ($poolRaw === 'sarabanda') {
            $poolTipo = 'sarabanda';
        } else {
            $poolTipo = in_array($poolRaw, ['mono', 'fisso'], true) ? 'mono' : 'tutti';
        }

        $argomentoRaw = $input['argomento_id'] ?? $base['argomento_id'];
        $argomentoId = null;
        if ($argomentoRaw !== '' && $argomentoRaw !== null) {
            $argomentoInt = (int) $argomentoRaw;
            if ($argomentoInt > 0) {
                $argomentoId = $argomentoInt;
            }
        }

        if ($poolTipo !== 'mono') {
            $argomentoId = null;
        }

        $selezioneRaw = trim((string) ($input['selezione_tipo'] ?? $base['selezione_tipo']));
        $selezioneTipo = $selezioneRaw === 'manuale' ? 'manuale' : 'random';
        $maxPerArgomentoRaw = $input['max_per_argomento'] ?? $base['max_per_argomento'] ?? null;
        $maxPerArgomento = null;
        if ($maxPerArgomentoRaw !== '' && $maxPerArgomentoRaw !== null) {
            $maxPerArgomentoInt = (int) $maxPerArgomentoRaw;
            if ($maxPerArgomentoInt > 0) {
                $maxPerArgomento = $maxPerArgomentoInt;
            }
        }

        if ($poolTipo !== 'tutti' || $selezioneTipo !== 'random') {
            $maxPerArgomento = null;
        }

        return [
            'numero_domande' => $numeroDomande,
            'pool_tipo' => $poolTipo,
            'argomento_id' => $argomentoId,
            'selezione_tipo' => $selezioneTipo,
            'max_per_argomento' => $maxPerArgomento,
        ];
    }

    private function loadConfigSnapshot(int $configurazioneId): array
    {
        if (!$this->hasTable('configurazioni_quiz_v2')) {
            return [
                'numero_domande' => 10,
                'pool_tipo' => 'tutti',
                'argomento_id' => null,
                'selezione_tipo' => 'random',
            ];
        }

        $stmt = $this->pdo->prepare(
            "SELECT numero_domande, modalita, argomento_id, selezione_tipo
             FROM configurazioni_quiz_v2
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $configurazioneId]);
        $v2 = $stmt->fetch();

        if ($v2) {
            $modalita = (string) ($v2['modalita'] ?? 'mista');
            $poolTipo = $modalita === 'mista' ? 'tutti' : 'mono';
            $selezione = (string) ($v2['selezione_tipo'] ?? 'auto');

            return [
                'numero_domande' => (int) ($v2['numero_domande'] ?? 10),
                'pool_tipo' => $poolTipo,
                'argomento_id' => $v2['argomento_id'] !== null ? (int) $v2['argomento_id'] : null,
                'selezione_tipo' => $selezione === 'manuale' ? 'manuale' : 'random',
                'max_per_argomento' => null,
            ];
        }

        return [
            'numero_domande' => 10,
            'pool_tipo' => 'tutti',
            'argomento_id' => null,
            'selezione_tipo' => 'random',
            'max_per_argomento' => null,
        ];
    }

    private function hasTable(string $table): bool
    {
        $quoted = $this->pdo->quote($table);
        $stmt = $this->pdo->query("SHOW TABLES LIKE {$quoted}");

        return (bool) $stmt->fetchColumn();
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

    public function hasNomeSessioneColumn(): bool
    {
        return $this->hasColumn('sessioni', 'nome_sessione');
    }

    public function ensureNomeSessioneColumn(): void
    {
        // intentionally no-op
    }

    private function ensureMaxPerArgomentoColumn(): void
    {
        if ($this->hasColumn('sessioni', 'max_per_argomento')) {
            return;
        }

        $this->pdo->exec("ALTER TABLE sessioni ADD COLUMN max_per_argomento INT NULL AFTER selezione_tipo");
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
            ? "SELECT id, pin, {$nomeColumn} AS nome_sessione, stato, domanda_corrente, numero_domande, pool_tipo, argomento_id, selezione_tipo, max_per_argomento, creata_il FROM sessioni ORDER BY id DESC LIMIT :lim"
            : "SELECT id, pin, stato, domanda_corrente, numero_domande, pool_tipo, argomento_id, selezione_tipo, max_per_argomento, creata_il FROM sessioni ORDER BY id DESC LIMIT :lim";

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

    public function aggiornaSnapshot(int $id, array $input): bool
    {
        if ($id <= 0 || !$this->trova($id)) {
            return false;
        }

        $numeroDomande = (int) ($input['numero_domande'] ?? 0);
        if ($numeroDomande <= 0) {
            $numeroDomande = 10;
        }

        $poolRaw = trim((string) ($input['pool_tipo'] ?? 'tutti'));
        if ($poolRaw === 'sarabanda') {
            $poolTipo = 'sarabanda';
        } else {
            $poolTipo = in_array($poolRaw, ['mono', 'fisso'], true) ? 'mono' : 'tutti';
        }

        $argomentoId = null;
        if ($poolTipo === 'mono') {
            $argomentoRaw = $input['argomento_id'] ?? null;
            if ($argomentoRaw !== null && $argomentoRaw !== '') {
                $argomentoInt = (int) $argomentoRaw;
                if ($argomentoInt > 0) {
                    $argomentoId = $argomentoInt;
                }
            }
        }

        $selezioneRaw = trim((string) ($input['selezione_tipo'] ?? 'random'));
        $selezioneTipo = $selezioneRaw === 'manuale' ? 'manuale' : 'random';
        $maxPerArgomento = null;
        if ($poolTipo === 'tutti' && $selezioneTipo === 'random') {
            $maxRaw = $input['max_per_argomento'] ?? null;
            if ($maxRaw !== null && $maxRaw !== '') {
                $maxInt = (int) $maxRaw;
                if ($maxInt > 0) {
                    $maxPerArgomento = $maxInt;
                }
            }
        }

        $nomeColumn = $this->resolveSessionNameColumn();
        $nomeSessione = trim((string) ($input['nome_sessione'] ?? ''));

        if ($nomeColumn !== null) {
            $stmt = $this->pdo->prepare(
                "UPDATE sessioni
                 SET {$nomeColumn} = :nome_sessione,
                     numero_domande = :numero_domande,
                     pool_tipo = :pool_tipo,
                     argomento_id = :argomento_id,
                     selezione_tipo = :selezione_tipo,
                     max_per_argomento = :max_per_argomento
                 WHERE id = :id"
            );

            return $stmt->execute([
                'nome_sessione' => $nomeSessione,
                'numero_domande' => $numeroDomande,
                'pool_tipo' => $poolTipo,
                'argomento_id' => $argomentoId,
                'selezione_tipo' => $selezioneTipo,
                'max_per_argomento' => $maxPerArgomento,
                'id' => $id,
            ]);
        }

        $stmt = $this->pdo->prepare(
            "UPDATE sessioni
             SET numero_domande = :numero_domande,
                 pool_tipo = :pool_tipo,
                 argomento_id = :argomento_id,
                 selezione_tipo = :selezione_tipo,
                 max_per_argomento = :max_per_argomento
             WHERE id = :id"
        );

        return $stmt->execute([
            'numero_domande' => $numeroDomande,
            'pool_tipo' => $poolTipo,
            'argomento_id' => $argomentoId,
            'selezione_tipo' => $selezioneTipo,
            'max_per_argomento' => $maxPerArgomento,
            'id' => $id,
        ]);
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
                'inizio' => round(microtime(true), 3),
                'id' => $id,
            ]);
        }

        $stmt = $this->pdo->prepare(
            "UPDATE sessioni
             SET stato = :stato
             WHERE id = :id"
        );

        return $stmt->execute([
            'stato' => $stato,
            'id' => $id,
        ]);
    }

    public function avanzaDomanda(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.domanda_corrente,
                    COALESCE(MAX(sd.posizione), 0) AS numero_domande
             FROM sessioni s
             LEFT JOIN sessione_domande sd ON sd.sessione_id = s.id
             WHERE s.id = :id
             GROUP BY s.id, s.domanda_corrente
             LIMIT 1"
        );

        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        if (!$data) {
            return;
        }

        $corrente = (int) $data['domanda_corrente'];
        $totale = (int) $data['numero_domande'];

        if ($totale <= 0) {
            return;
        }

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
