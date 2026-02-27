<?php

namespace App\Modules\QuizConfigV2;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

class QuizConfigService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function initializeSchema(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS configurazioni_quiz_v2 (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome_quiz VARCHAR(120) NOT NULL UNIQUE,
                titolo VARCHAR(255) NOT NULL,
                modalita VARCHAR(32) NOT NULL DEFAULT 'mista',
                numero_domande INT NOT NULL DEFAULT 10,
                argomento_id INT NOT NULL DEFAULT 0,
                selezione_tipo VARCHAR(32) NOT NULL DEFAULT 'auto',
                attiva TINYINT(1) NOT NULL DEFAULT 1,
                creata_il INT NOT NULL,
                aggiornata_il INT NOT NULL,
                KEY idx_modalita_attiva (modalita, attiva),
                KEY idx_argomento (argomento_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS configurazioni_quiz_v2_domande (
                configurazione_id INT NOT NULL,
                domanda_id INT NOT NULL,
                posizione INT NOT NULL,
                PRIMARY KEY (configurazione_id, domanda_id),
                KEY idx_cfg_pos (configurazione_id, posizione),
                KEY idx_domanda (domanda_id),
                CONSTRAINT fk_cfg_v2_domande_config
                    FOREIGN KEY (configurazione_id)
                    REFERENCES configurazioni_quiz_v2(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Migrazione soft da vecchi valori/enum
        $this->pdo->exec("ALTER TABLE configurazioni_quiz_v2 MODIFY modalita VARCHAR(32) NOT NULL DEFAULT 'mista'");
        $this->pdo->exec("ALTER TABLE configurazioni_quiz_v2 MODIFY selezione_tipo VARCHAR(32) NOT NULL DEFAULT 'auto'");
        $this->pdo->exec("ALTER TABLE configurazioni_quiz_v2 MODIFY argomento_id INT NOT NULL DEFAULT 0");

        $this->pdo->exec("UPDATE configurazioni_quiz_v2 SET modalita='mista' WHERE modalita='auto_random_corrente'");
        $this->pdo->exec("UPDATE configurazioni_quiz_v2 SET modalita='auto' WHERE modalita='auto_pool_argomento_corrente'");
        $this->pdo->exec("UPDATE configurazioni_quiz_v2 SET modalita='manuale' WHERE modalita='manuale_argomento'");
        $this->pdo->exec("UPDATE configurazioni_quiz_v2 SET modalita='manuale' WHERE modalita='manuale_domande_argomento_corrente'");

        $this->pdo->exec("UPDATE configurazioni_quiz_v2 SET selezione_tipo='auto' WHERE selezione_tipo='random'");
        $this->pdo->exec("UPDATE configurazioni_quiz_v2 SET selezione_tipo='manuale' WHERE selezione_tipo='ordinata'");
    }

    public function listConfigurations(): array
    {
        $stmt = $this->pdo->query(
            "SELECT *
             FROM configurazioni_quiz_v2
             ORDER BY aggiornata_il DESC"
        );

        $configurazioni = $stmt->fetchAll();

        foreach ($configurazioni as &$cfg) {
            $cfg['domande_manuali'] = $this->getManualQuestions((int) $cfg['id']);
        }

        return $configurazioni;
    }

    public function getConfiguration(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM configurazioni_quiz_v2
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $id]);
        $config = $stmt->fetch();

        if (!$config) {
            return null;
        }

        $config['domande_manuali'] = $this->getManualQuestions($id);

        return $config;
    }

    public function listArgomenti(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, nome
             FROM argomenti
             ORDER BY nome ASC"
        );

        return $stmt->fetchAll();
    }

    public function listDomandeDisponibili(?int $argomentoId = null, string $query = ''): array
    {
        $sql = "SELECT d.id, d.testo, d.argomento_id, COALESCE(a.nome, '') AS argomento_nome
                FROM domande d
                LEFT JOIN argomenti a ON a.id = d.argomento_id
                WHERE d.attiva = 1";

        $params = [];

        if ($argomentoId !== null && $argomentoId > 0) {
            $sql .= " AND d.argomento_id = :argomento_id";
            $params['argomento_id'] = $argomentoId;
        }

        $query = trim($query);
        if ($query !== '') {
            $sql .= " AND d.testo LIKE :query";
            $params['query'] = '%' . $query . '%';
        }

        $sql .= " ORDER BY d.id ASC LIMIT 300";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function generateQuestions(array $payload, array $domandeManuali = []): array
    {
        $this->validatePayload($payload, $domandeManuali, false);

        $numero = (int) $payload['numero_domande'];
        $argomentoId = (int) ($payload['argomento_id'] ?? 0);
        $modalita = (string) $payload['modalita'];
        $selezione = (string) $payload['selezione_tipo'];

        if ($modalita === 'mista') {
            $argomentoId = 0;
        }

        if ($selezione === 'manuale') {
            $ids = $domandeManuali;

            if (empty($ids) && !empty($payload['id'])) {
                $ids = array_map(static fn($r) => (int) ($r['domanda_id'] ?? 0), $this->getManualQuestions((int) $payload['id']));
                $ids = array_values(array_filter($ids, static fn($v) => $v > 0));
            }

            if (count($ids) === 0) {
                throw new InvalidArgumentException('Nessuna domanda manuale selezionata');
            }

            if ($argomentoId > 0) {
                $filteredCount = $this->countActiveQuestionsByIdsAndTopic($ids, $argomentoId);
                if ($filteredCount !== count($ids)) {
                    throw new InvalidArgumentException('Le domande manuali devono appartenere all\'argomento selezionato');
                }
            }

            $rows = $this->loadQuestionsByOrderedIds($ids);
            return array_slice($rows, 0, $numero);
        }

        $sql = "SELECT d.id, d.testo, d.argomento_id, COALESCE(a.nome,'') AS argomento_nome
                FROM domande d
                LEFT JOIN argomenti a ON a.id = d.argomento_id
                WHERE d.attiva = 1";
        $params = [];

        if ($argomentoId > 0) {
            $sql .= " AND d.argomento_id = :argomento_id";
            $params['argomento_id'] = $argomentoId;
        }

        $sql .= " ORDER BY RAND() LIMIT " . $numero;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function saveConfiguration(array $payload, array $domandeManuali = []): int
    {
        $this->validatePayload($payload, $domandeManuali, true);

        $id = isset($payload['id']) && (int) $payload['id'] > 0 ? (int) $payload['id'] : null;
        $now = time();

        $modalita = (string) $payload['modalita'];
        $argomentoId = (int) ($payload['argomento_id'] ?? 0);
        if ($modalita === 'mista') {
            $argomentoId = 0;
        }

        $this->pdo->beginTransaction();

        try {
            if ($id === null) {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO configurazioni_quiz_v2
                    (nome_quiz, titolo, modalita, numero_domande, argomento_id, selezione_tipo, attiva, creata_il, aggiornata_il)
                    VALUES
                    (:nome_quiz, :titolo, :modalita, :numero_domande, :argomento_id, :selezione_tipo, :attiva, :creata_il, :aggiornata_il)"
                );

                $stmt->execute([
                    'nome_quiz' => $payload['nome_quiz'],
                    'titolo' => $payload['titolo'],
                    'modalita' => $modalita,
                    'numero_domande' => (int) $payload['numero_domande'],
                    'argomento_id' => $argomentoId,
                    'selezione_tipo' => $payload['selezione_tipo'],
                    'attiva' => $payload['attiva'] ? 1 : 0,
                    'creata_il' => $now,
                    'aggiornata_il' => $now,
                ]);

                $id = (int) $this->pdo->lastInsertId();
            } else {
                $existing = $this->getConfiguration($id);
                if (!$existing) {
                    throw new InvalidArgumentException('Configurazione non trovata per update');
                }

                $stmt = $this->pdo->prepare(
                    "UPDATE configurazioni_quiz_v2
                     SET nome_quiz = :nome_quiz,
                         titolo = :titolo,
                         modalita = :modalita,
                         numero_domande = :numero_domande,
                         argomento_id = :argomento_id,
                         selezione_tipo = :selezione_tipo,
                         attiva = :attiva,
                         aggiornata_il = :aggiornata_il
                     WHERE id = :id"
                );

                $stmt->execute([
                    'nome_quiz' => $payload['nome_quiz'],
                    'titolo' => $payload['titolo'],
                    'modalita' => $modalita,
                    'numero_domande' => (int) $payload['numero_domande'],
                    'argomento_id' => $argomentoId,
                    'selezione_tipo' => $payload['selezione_tipo'],
                    'attiva' => $payload['attiva'] ? 1 : 0,
                    'aggiornata_il' => $now,
                    'id' => $id,
                ]);
            }

            if (($payload['selezione_tipo'] ?? 'auto') === 'manuale' && count($domandeManuali) > 0) {
                $this->replaceManualQuestions($id, $domandeManuali);
            }

            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function saveGeneratedQuestions(int $configurazioneId, array $domandeIds): void
    {
        $cfg = $this->getConfiguration($configurazioneId);
        if (!$cfg) {
            throw new InvalidArgumentException('Configurazione non trovata');
        }

        if (count($domandeIds) === 0) {
            throw new InvalidArgumentException('Nessuna domanda da salvare');
        }

        $validCount = $this->countActiveQuestionsByIds($domandeIds);
        if ($validCount !== count($domandeIds)) {
            throw new InvalidArgumentException('Una o più domande non sono valide o attive');
        }

        $this->replaceManualQuestions($configurazioneId, $domandeIds);
    }

    private function replaceManualQuestions(int $configurazioneId, array $domandeManuali): void
    {
        $clearStmt = $this->pdo->prepare(
            "DELETE FROM configurazioni_quiz_v2_domande WHERE configurazione_id = :configurazione_id"
        );
        $clearStmt->execute(['configurazione_id' => $configurazioneId]);

        $this->saveManualQuestions($configurazioneId, $domandeManuali);
    }

    private function saveManualQuestions(int $configurazioneId, array $domandeManuali): void
    {
        $posizione = 1;

        foreach ($domandeManuali as $domandaId) {
            $insertStmt = $this->pdo->prepare(
                "INSERT INTO configurazioni_quiz_v2_domande
                 (configurazione_id, domanda_id, posizione)
                 VALUES
                 (:configurazione_id, :domanda_id, :posizione)"
            );

            $insertStmt->execute([
                'configurazione_id' => $configurazioneId,
                'domanda_id' => $domandaId,
                'posizione' => $posizione,
            ]);

            $posizione++;
        }
    }

    private function getManualQuestions(int $configurazioneId): array
    {
        $domandeStmt = $this->pdo->prepare(
            "SELECT domanda_id, posizione
             FROM configurazioni_quiz_v2_domande
             WHERE configurazione_id = :id
             ORDER BY posizione ASC"
        );

        $domandeStmt->execute(['id' => $configurazioneId]);

        return $domandeStmt->fetchAll();
    }

    private function validatePayload(array $payload, array $domandeManuali, bool $strictManual): void
    {
        $modalitaAmmesse = ['mista', 'auto', 'manuale'];

        if (($payload['nome_quiz'] ?? '') === '') {
            throw new InvalidArgumentException('nome_quiz obbligatorio');
        }

        if (($payload['titolo'] ?? '') === '') {
            throw new InvalidArgumentException('titolo obbligatorio');
        }

        if (!in_array($payload['modalita'] ?? '', $modalitaAmmesse, true)) {
            throw new InvalidArgumentException('modalita non valida');
        }

        if ((int) ($payload['numero_domande'] ?? 0) <= 0) {
            throw new InvalidArgumentException('numero_domande deve essere maggiore di 0');
        }

        if (($payload['selezione_tipo'] ?? '') !== 'auto' && ($payload['selezione_tipo'] ?? '') !== 'manuale') {
            throw new InvalidArgumentException('selezione_tipo non valido');
        }

        $argomentoId = (int) ($payload['argomento_id'] ?? 0);

        if (($payload['modalita'] ?? '') === 'mista') {
            if ($argomentoId !== 0) {
                throw new InvalidArgumentException('In modalita mista argomento_id deve essere 0');
            }
        } elseif ($argomentoId > 0 && !$this->argomentoExists($argomentoId)) {
            throw new InvalidArgumentException('argomento_id non valido');
        }

        if (($payload['selezione_tipo'] ?? '') === 'manuale' && $strictManual && count($domandeManuali) === 0 && empty($payload['id'])) {
            throw new InvalidArgumentException('Con selezione manuale inserisci almeno una domanda');
        }

        if (count($domandeManuali) > 0) {
            $validCount = $this->countActiveQuestionsByIds($domandeManuali);
            if ($validCount !== count($domandeManuali)) {
                throw new InvalidArgumentException('Una o più domande manuali non esistono o non sono attive');
            }

            if ($argomentoId > 0) {
                $filteredCount = $this->countActiveQuestionsByIdsAndTopic($domandeManuali, $argomentoId);
                if ($filteredCount !== count($domandeManuali)) {
                    throw new InvalidArgumentException('Le domande manuali devono appartenere all\'argomento selezionato');
                }
            }
        }
    }

    private function loadQuestionsByOrderedIds(array $ids): array
    {
        $rows = [];
        $stmt = $this->pdo->prepare(
            "SELECT d.id, d.testo, d.argomento_id, COALESCE(a.nome,'') AS argomento_nome
             FROM domande d
             LEFT JOIN argomenti a ON a.id = d.argomento_id
             WHERE d.id = :id AND d.attiva = 1
             LIMIT 1"
        );

        foreach ($ids as $id) {
            $stmt->execute(['id' => (int) $id]);
            $r = $stmt->fetch();
            if ($r) {
                $rows[] = $r;
            }
        }

        return $rows;
    }

    private function countActiveQuestionsByIds(array $domandeIds): int
    {
        $placeholders = implode(',', array_fill(0, count($domandeIds), '?'));

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS totale
             FROM domande
             WHERE attiva = 1
               AND id IN ($placeholders)"
        );

        $stmt->execute($domandeIds);
        $result = $stmt->fetch();

        return (int) ($result['totale'] ?? 0);
    }

    private function countActiveQuestionsByIdsAndTopic(array $domandeIds, int $argomentoId): int
    {
        $params = array_merge([$argomentoId], $domandeIds);
        $placeholders = implode(',', array_fill(0, count($domandeIds), '?'));

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS totale
             FROM domande
             WHERE attiva = 1
               AND argomento_id = ?
               AND id IN ($placeholders)"
        );

        $stmt->execute($params);
        $result = $stmt->fetch();

        return (int) ($result['totale'] ?? 0);
    }

    private function argomentoExists(int $argomentoId): bool
    {
        if ($argomentoId <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "SELECT id
             FROM argomenti
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $argomentoId]);

        return (bool) $stmt->fetch();
    }
}
