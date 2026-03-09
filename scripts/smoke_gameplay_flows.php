<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use App\Core\Database;
use App\Models\Partecipazione;
use App\Models\Sessione;
use App\Models\Utente;
use App\Services\Question\ImpostoreModeService;
use App\Services\Question\MemeModeService;
use App\Services\SessioneService;

final class SmokeGameplayFlows
{
    private \PDO $pdo;
    private array $createdSessionIds = [];
    private array $createdUserIds = [];
    private array $results = [];

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function run(): int
    {
        try {
            $questionIds = $this->loadEligibleQuestionIds(4);
            $sessioneId = $this->createSessionWithQuestions($questionIds);

            [$partecipazioneA, $partecipazioneB] = $this->createParticipants($sessioneId);

            $this->testClassicCorrectAnswer($sessioneId, $partecipazioneA);
            $this->testMissingAnswerPenalty($sessioneId, $partecipazioneA);
            $this->testImpostoreBonus($sessioneId, $partecipazioneA, $partecipazioneB);
            $this->testMemeRuntime($sessioneId, $partecipazioneA);

            $summary = [
                'ok' => true,
                'tests' => $this->results,
            ];

            echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            return 0;
        } catch (\Throwable $e) {
            $summary = [
                'ok' => false,
                'tests' => $this->results,
                'error' => $e->getMessage(),
            ];

            fwrite(STDERR, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
            return 1;
        } finally {
            $this->cleanup();
        }
    }

    private function assertTrue(bool $condition, string $message, array $meta = []): void
    {
        if (!$condition) {
            throw new RuntimeException($message . ($meta ? ' ' . json_encode($meta, JSON_UNESCAPED_UNICODE) : ''));
        }
    }

    private function addResult(string $test, bool $ok, array $meta = []): void
    {
        $this->results[] = [
            'test' => $test,
            'ok' => $ok,
            'meta' => $meta,
        ];
    }

    private function loadEligibleQuestionIds(int $count): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT d.id
             FROM domande d
             WHERE d.attiva = 1
               AND UPPER(COALESCE(d.tipo_domanda, 'CLASSIC')) <> 'SARABANDA'
               AND (SELECT COUNT(*) FROM opzioni o WHERE o.domanda_id = d.id) >= 4
               AND EXISTS (
                   SELECT 1
                   FROM opzioni ok
                   WHERE ok.domanda_id = d.id
                     AND ok.corretta = 1
               )
             ORDER BY d.id ASC
             LIMIT {$count}"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $ids = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $rows);
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));

        $this->assertTrue(count($ids) === $count, 'Domande eleggibili insufficienti per lo smoke test.', [
            'required' => $count,
            'found' => count($ids),
        ]);

        return $ids;
    }

    private function createSessionWithQuestions(array $questionIds): int
    {
        $sessioneModel = new Sessione();
        $sessioneId = $sessioneModel->crea(0, 'Smoke Gameplay ' . date('Y-m-d H:i:s'), [
            'numero_domande' => count($questionIds),
            'pool_tipo' => 'tutti',
            'selezione_tipo' => 'random',
        ]);

        $this->createdSessionIds[] = $sessioneId;

        $delete = $this->pdo->prepare('DELETE FROM sessione_domande WHERE sessione_id = :sessione_id');
        $delete->execute(['sessione_id' => $sessioneId]);

        $insert = $this->pdo->prepare(
            "INSERT INTO sessione_domande (sessione_id, domanda_id, posizione)
             VALUES (:sessione_id, :domanda_id, :posizione)"
        );

        foreach (array_values($questionIds) as $index => $questionId) {
            $insert->execute([
                'sessione_id' => $sessioneId,
                'domanda_id' => $questionId,
                'posizione' => $index + 1,
            ]);
        }

        return $sessioneId;
    }

    private function createParticipants(int $sessioneId): array
    {
        $utenteModel = new Utente();
        $partecipazioneModel = new Partecipazione();

        $utenteA = $utenteModel->creaTemporaneo('Smoke A ' . time());
        $utenteB = $utenteModel->creaTemporaneo('Smoke B ' . time());
        $this->createdUserIds[] = $utenteA;
        $this->createdUserIds[] = $utenteB;

        return [
            $partecipazioneModel->entra($sessioneId, $utenteA),
            $partecipazioneModel->entra($sessioneId, $utenteB),
        ];
    }

    private function currentQuestionId(int $sessioneId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT sd.domanda_id
             FROM sessioni s
             JOIN sessione_domande sd
               ON sd.sessione_id = s.id
              AND sd.posizione = s.domanda_corrente
             WHERE s.id = :sessione_id
             LIMIT 1"
        );
        $stmt->execute(['sessione_id' => $sessioneId]);
        return (int) (($stmt->fetch()['domanda_id'] ?? 0));
    }

    private function loadCorrectOptionId(int $domandaId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT id
             FROM opzioni
             WHERE domanda_id = :domanda_id
               AND corretta = 1
             ORDER BY id ASC
             LIMIT 1"
        );
        $stmt->execute(['domanda_id' => $domandaId]);
        return (int) (($stmt->fetch()['id'] ?? 0));
    }

    private function loadLatestAnswerRow(int $partecipazioneId, int $domandaId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM risposte
             WHERE partecipazione_id = :partecipazione_id
               AND domanda_id = :domanda_id
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([
            'partecipazione_id' => $partecipazioneId,
            'domanda_id' => $domandaId,
        ]);
        return $stmt->fetch() ?: null;
    }

    private function loadCapitale(int $partecipazioneId): int
    {
        $stmt = $this->pdo->prepare('SELECT capitale_attuale FROM partecipazioni WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $partecipazioneId]);
        return (int) (($stmt->fetch()['capitale_attuale'] ?? 0));
    }

    private function saveLiveBet(int $sessioneId, int $partecipazioneId, int $puntata): void
    {
        $service = new SessioneService($sessioneId);
        $partecipazioneModel = new Partecipazione();

        $this->assertTrue($service->puoPuntare(), 'La sessione non e in stato puntata.');
        $this->assertTrue($partecipazioneModel->registraPuntata($partecipazioneId, $puntata), 'Registrazione puntata fallita.', [
            'partecipazione_id' => $partecipazioneId,
            'puntata' => $puntata,
        ]);
        $service->salvaPuntataLive($partecipazioneId, $puntata);
    }

    private function testClassicCorrectAnswer(int $sessioneId, int $partecipazioneId): void
    {
        $service = new SessioneService($sessioneId);
        $service->avviaPuntata();
        $this->saveLiveBet($sessioneId, $partecipazioneId, 100);

        $service = new SessioneService($sessioneId);
        $service->avviaDomanda();

        $domandaId = $this->currentQuestionId($sessioneId);
        $correctOptionId = $this->loadCorrectOptionId($domandaId);
        $this->assertTrue($correctOptionId > 0, 'Opzione corretta non trovata.', ['domanda_id' => $domandaId]);

        usleep(200000);
        $result = (new Partecipazione())->registraRisposta($partecipazioneId, $domandaId, $correctOptionId, 0.2);
        $this->assertTrue(is_array($result), 'Risposta classica non registrata.');
        $this->assertTrue(!empty($result['corretta']), 'La risposta corretta non e stata valutata come corretta.', $result);
        $this->assertTrue((int) ($result['punti'] ?? 0) > 0, 'La risposta corretta non ha assegnato punti.', $result);

        $service = new SessioneService($sessioneId);
        $service->chiudiDomanda();

        $sessione = (new Sessione())->trova($sessioneId);
        $this->assertTrue((string) ($sessione['stato'] ?? '') === 'risultati', 'La sessione non e passata a risultati.');

        $this->addResult('classic_correct_answer', true, [
            'domanda_id' => $domandaId,
            'punti' => (int) ($result['punti'] ?? 0),
        ]);
    }

    private function testMissingAnswerPenalty(int $sessioneId, int $partecipazioneId): void
    {
        $service = new SessioneService($sessioneId);
        $service->prossimaFase();

        $capitaleBefore = $this->loadCapitale($partecipazioneId);
        $puntata = 120;
        $this->saveLiveBet($sessioneId, $partecipazioneId, $puntata);

        $service = new SessioneService($sessioneId);
        $service->avviaDomanda();
        $domandaId = $this->currentQuestionId($sessioneId);

        $service = new SessioneService($sessioneId);
        $service->chiudiDomanda();

        $capitaleAfter = $this->loadCapitale($partecipazioneId);
        $answerRow = $this->loadLatestAnswerRow($partecipazioneId, $domandaId);

        $this->assertTrue(($capitaleBefore - $capitaleAfter) === $puntata, 'La mancata risposta non ha sottratto la puntata corretta.', [
            'before' => $capitaleBefore,
            'after' => $capitaleAfter,
            'puntata' => $puntata,
        ]);
        $this->assertTrue(is_array($answerRow), 'Risposta automatica di assenza non trovata.');
        $this->assertTrue((int) ($answerRow['corretta'] ?? 1) === 0, 'La risposta di assenza non risulta errata.', $answerRow);
        $this->assertTrue($answerRow['opzione_id'] === null, 'La risposta di assenza non dovrebbe avere opzione_id.', $answerRow);

        $this->addResult('missing_answer_penalty', true, [
            'domanda_id' => $domandaId,
            'puntata' => $puntata,
        ]);
    }

    private function testImpostoreBonus(int $sessioneId, int $partecipazioneA, int $partecipazioneB): void
    {
        $service = new SessioneService($sessioneId);
        $service->prossimaFase();

        $domandaId = $this->currentQuestionId($sessioneId);
        (new ImpostoreModeService())->setEnabledForQuestion($sessioneId, $domandaId, true);

        $this->saveLiveBet($sessioneId, $partecipazioneA, 100);
        $this->saveLiveBet($sessioneId, $partecipazioneB, 100);

        $service = new SessioneService($sessioneId);
        $service->avviaDomanda();

        $assignment = (new ImpostoreModeService())->getAssignment($sessioneId, $domandaId);
        $impostorePartecipazioneId = (int) ($assignment['impostore_partecipazione_id'] ?? 0);
        $this->assertTrue(in_array($impostorePartecipazioneId, [$partecipazioneA, $partecipazioneB], true), 'Assegnazione impostore non valida.', [
            'assignment' => $assignment,
        ]);

        $correctOptionId = $this->loadCorrectOptionId($domandaId);
        usleep(200000);
        $result = (new Partecipazione())->registraRisposta($impostorePartecipazioneId, $domandaId, $correctOptionId, 0.2);

        $this->assertTrue(is_array($result), 'Risposta impostore non registrata.');
        $this->assertTrue(!empty($result['corretta']), 'L impostore non risulta corretto pur avendo scelto la risposta corretta.', $result);
        $this->assertTrue(!empty($result['is_impostore']), 'Il risultato non identifica il player come impostore.', $result);
        $this->assertTrue((int) ($result['bonus_impostore'] ?? 0) > 0, 'Bonus impostore non assegnato.', $result);

        $service = new SessioneService($sessioneId);
        $service->chiudiDomanda();

        $this->addResult('impostore_bonus', true, [
            'domanda_id' => $domandaId,
            'impostore_partecipazione_id' => $impostorePartecipazioneId,
            'bonus_impostore' => (int) ($result['bonus_impostore'] ?? 0),
        ]);
    }

    private function testMemeRuntime(int $sessioneId, int $partecipazioneId): void
    {
        $service = new SessioneService($sessioneId);
        $service->prossimaFase();

        $domandaId = $this->currentQuestionId($sessioneId);
        $memeText = 'Risposta assurda di test';
        (new MemeModeService())->setEnabledForQuestion($sessioneId, $domandaId, true, $memeText);

        $this->saveLiveBet($sessioneId, $partecipazioneId, 90);

        $service = new SessioneService($sessioneId);
        $service->avviaDomanda();

        $domanda = $service->domandaCorrente();
        $memeState = (new MemeModeService())->getRuntimeState($sessioneId) ?? [];
        $wrongOptionId = (int) ($memeState['display_wrong_option_id'] ?? 0);

        $this->assertTrue(!empty($domanda['meme_mode']), 'La domanda non risulta decorata come MEME.');
        $this->assertTrue($wrongOptionId > 0, 'MEME non ha selezionato una risposta assurda.');

        usleep(200000);
        $result = (new Partecipazione())->registraRisposta($partecipazioneId, $domandaId, $wrongOptionId, 0.2);
        $this->assertTrue(is_array($result), 'Risposta MEME non registrata.');
        $this->assertTrue(empty($result['corretta']), 'La scelta meme non dovrebbe risultare corretta.', $result);
        $this->assertTrue((string) ($result['risposta_data_testo'] ?? '') === $memeText, 'Il testo risposta MEME non e stato decorato correttamente.', $result);

        $service = new SessioneService($sessioneId);
        $service->chiudiDomanda();

        $this->addResult('meme_runtime', true, [
            'domanda_id' => $domandaId,
            'display_wrong_option_id' => $wrongOptionId,
        ]);
    }

    private function cleanup(): void
    {
        foreach ($this->createdSessionIds as $sessioneId) {
            try {
                $this->pdo->prepare('DELETE FROM puntate_live WHERE sessione_id = :sessione_id')
                    ->execute(['sessione_id' => $sessioneId]);
                $this->pdo->prepare(
                    'DELETE r FROM risposte r
                     JOIN partecipazioni p ON p.id = r.partecipazione_id
                     WHERE p.sessione_id = :sessione_id'
                )->execute(['sessione_id' => $sessioneId]);
                $this->pdo->prepare('DELETE FROM partecipazioni WHERE sessione_id = :sessione_id')
                    ->execute(['sessione_id' => $sessioneId]);
                $this->pdo->prepare('DELETE FROM sessione_domande WHERE sessione_id = :sessione_id')
                    ->execute(['sessione_id' => $sessioneId]);
                $this->pdo->prepare('DELETE FROM sessioni WHERE id = :id')
                    ->execute(['id' => $sessioneId]);

                (new MemeModeService())->clearRuntimeState($sessioneId);
                (new ImpostoreModeService())->clearRuntimeState($sessioneId);

                foreach (glob(STORAGE_PATH . '/runtime/impostore/session_' . $sessioneId . '_domanda_*.json') ?: [] as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            } catch (\Throwable $e) {
                // best-effort cleanup
            }
        }

        if ($this->createdUserIds !== []) {
            try {
                $placeholders = implode(',', array_fill(0, count($this->createdUserIds), '?'));
                $stmt = $this->pdo->prepare("DELETE FROM utenti WHERE id IN ({$placeholders})");
                $stmt->execute(array_values($this->createdUserIds));
            } catch (\Throwable $e) {
                // best-effort cleanup
            }
        }
    }
}

$runner = new SmokeGameplayFlows();
exit($runner->run());
