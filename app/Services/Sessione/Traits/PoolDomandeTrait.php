<?php

namespace App\Services\Sessione\Traits;

use App\Services\Question\ImpostoreModeService;
use App\Services\Question\MemeModeService;
use App\Services\Question\QuestionRuntimeModeService;
use RuntimeException;

trait PoolDomandeTrait
{
    private function loadEligibleQuestionRows(string $poolTipo, ?int $argomentoId, string $selezione): array
    {
        $query = 'SELECT id, argomento_id FROM domande WHERE attiva = 1';
        $params = [];

        if ($poolTipo === 'mono' && $argomentoId) {
            $query .= ' AND argomento_id = ?';
            $params[] = $argomentoId;
        }

        if ($poolTipo === 'sarabanda') {
            $query .= " AND UPPER(COALESCE(tipo_domanda, 'CLASSIC')) = ?";
            $params[] = 'SARABANDA';
        }

        $query .= ($selezione === 'random')
            ? ' ORDER BY RAND()'
            : ' ORDER BY id ASC';

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    private function applyTopicLimitToQuestions(array $domande, int $numeroDomande, ?int $maxPerArgomento): array
    {
        if ($maxPerArgomento === null || $maxPerArgomento <= 0) {
            return array_slice($domande, 0, $numeroDomande);
        }

        $selected = [];
        $countsByTopic = [];

        foreach ($domande as $domanda) {
            $topicId = (int) ($domanda['argomento_id'] ?? 0);
            $currentCount = (int) ($countsByTopic[$topicId] ?? 0);
            if ($currentCount >= $maxPerArgomento) {
                continue;
            }

            $selected[] = ['id' => (int) $domanda['id']];
            $countsByTopic[$topicId] = $currentCount + 1;

            if (count($selected) >= $numeroDomande) {
                break;
            }
        }

        return $selected;
    }

    public function generaDomandeSessione(): void
    {
        $stmt = $this->pdo->prepare("\n            SELECT COUNT(*) as totale\n            FROM sessione_domande\n            WHERE sessione_id = ?\n        ");
        $stmt->execute([$this->sessioneId]);
        $check = $stmt->fetch();

        if ($check['totale'] > 0) {
            return;
        }

        $config = $this->loadUnifiedQuizConfig((int) ($this->sessione['configurazione_id'] ?? 0));

        if (!$config) {
            throw new RuntimeException('Configurazione quiz non trovata.');
        }

        $numeroDomande = (int) $config['numero_domande'];

        if (($config['source'] ?? '') === 'v2' && ($config['modalita'] ?? '') === 'manuale_domande_argomento_corrente') {
            $domande = $this->loadManualV2Questions((int) ($this->sessione['configurazione_id'] ?? 0));

            if (count($domande) < $numeroDomande) {
                throw new RuntimeException('Domande manuali insufficienti per generare la sessione.');
            }

            $domande = array_slice($domande, 0, $numeroDomande);
            $this->persistSessionQuestions($domande);
            return;
        }

        $poolTipo = $config['pool_tipo'] ?? 'tutti';
        $argomentoId = $config['argomento_id'] ?? null;
        $selezione = $config['selezione_tipo'] ?? 'random';
        $maxPerArgomento = isset($config['max_per_argomento']) ? (int) $config['max_per_argomento'] : 0;
        $eligibleQuestions = $this->loadEligibleQuestionRows($poolTipo, $argomentoId, $selezione);
        $domande = ($poolTipo === 'tutti' && $selezione === 'random' && $maxPerArgomento > 0)
            ? $this->applyTopicLimitToQuestions($eligibleQuestions, $numeroDomande, $maxPerArgomento)
            : array_slice(array_map(static fn (array $row): array => ['id' => (int) $row['id']], $eligibleQuestions), 0, $numeroDomande);

        if (count($domande) < $numeroDomande) {
            throw new RuntimeException(
                $poolTipo === 'tutti' && $selezione === 'random' && $maxPerArgomento > 0
                    ? 'Domande insufficienti per generare la sessione con il limite per argomento impostato.'
                    : 'Domande insufficienti per generare la sessione.'
            );
        }

        $this->persistSessionQuestions($domande);
    }

    private function persistSessionQuestions(array $domande): void
    {
        $posizione = 1;

        foreach ($domande as $d) {
            $stmt = $this->pdo->prepare("\n                INSERT INTO sessione_domande\n                (sessione_id, domanda_id, posizione)\n                VALUES (?, ?, ?)\n            ");

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
        $stmt = $this->pdo->prepare("\n            SELECT d.*, COALESCE(a.nome, '') AS argomento_nome\n            FROM sessione_domande sd\n            JOIN domande d ON d.id = sd.domanda_id\n            LEFT JOIN argomenti a ON a.id = d.argomento_id\n            WHERE sd.sessione_id = ?\n            AND sd.posizione = ?\n            LIMIT 1\n        ");

        $stmt->execute([
            $this->sessioneId,
            $this->sessione['domanda_corrente']
        ]);

        $domanda = $stmt->fetch();

        if (!$domanda) {
            return null;
        }

        $now = round(microtime(true), 3);
        $revealUntil = (float) ($this->sessione['mostra_corretta_fino'] ?? 0);
        $showCorrect = $revealUntil > $now;

        if ($showCorrect) {
            $stmt = $this->pdo->prepare("\n                SELECT id, testo, corretta\n                FROM opzioni\n                WHERE domanda_id = ?\n                ORDER BY id ASC\n            ");
        } else {
            $stmt = $this->pdo->prepare("\n                SELECT id, testo\n                FROM opzioni\n                WHERE domanda_id = ?\n                ORDER BY id ASC\n            ");
        }

        $stmt->execute([$domanda['id']]);
        $domanda['opzioni'] = $stmt->fetchAll();
        $domanda['opzioni'] = $this->shuffleQuestionOptions($domanda['opzioni'], (int) $domanda['id']);

        $modeMeta = (new QuestionRuntimeModeService())->resolveFromRow(
            $this->sessioneId,
            (int) ($domanda['id'] ?? 0),
            $domanda
        );

        $domanda['tipo_domanda'] = $modeMeta['tipo_domanda'];
        $domanda['modalita_party'] = $modeMeta['modalita_party'];
        $domanda['fase_domanda'] = $modeMeta['fase_domanda'];
        $domanda['media_image_path'] = $modeMeta['media_image_path'];
        $domanda['media_audio_path'] = $modeMeta['media_audio_path'];
        $domanda['media_audio_preview_sec'] = $modeMeta['media_audio_preview_sec'];
        $domanda['media_caption'] = $modeMeta['media_caption'];
        $domanda['config_domanda'] = $modeMeta['config'];
        $domanda['show_correct'] = $showCorrect;
        $domanda['reveal_until'] = $showCorrect ? $revealUntil : null;
        $domanda['correct_option_id'] = null;

        if ($showCorrect) {
            foreach ($domanda['opzioni'] as $opzione) {
                if ((int) ($opzione['corretta'] ?? 0) === 1) {
                    $domanda['correct_option_id'] = (int) $opzione['id'];
                    break;
                }
            }
        }

        $viewer = strtolower(trim((string) ($_GET['viewer'] ?? 'generic')));
        $partecipazioneIdRaw = (int) ($_GET['partecipazione_id'] ?? 0);
        $partecipazioneId = $partecipazioneIdRaw > 0 ? $partecipazioneIdRaw : null;
        $domanda = (new ImpostoreModeService())->decorateQuestionForViewer(
            $this->sessioneId,
            $domanda,
            $viewer,
            $partecipazioneId
        );
        $domanda = (new MemeModeService())->decorateQuestion($domanda, $this->sessioneId);

        return $domanda;
    }

    private function shuffleQuestionOptions(array $options, int $domandaId): array
    {
        usort($options, function (array $left, array $right) use ($domandaId): int {
            $leftKey = hash('sha256', $this->sessioneId . '|' . $domandaId . '|' . ($left['id'] ?? 0));
            $rightKey = hash('sha256', $this->sessioneId . '|' . $domandaId . '|' . ($right['id'] ?? 0));

            return $leftKey <=> $rightKey;
        });

        return $options;
    }

    private function loadManualV2Questions(int $configurazioneId): array
    {
        $stmt = $this->pdo->prepare("\n            SELECT d.id\n            FROM configurazioni_quiz_v2_domande qd\n            JOIN domande d ON d.id = qd.domanda_id\n            WHERE qd.configurazione_id = :configurazione_id\n              AND d.attiva = 1\n            ORDER BY qd.posizione ASC\n        ");

        $stmt->execute(['configurazione_id' => $configurazioneId]);
        return $stmt->fetchAll();
    }

    private function domandaCorrenteId(): int
    {
        $stmt = $this->pdo->prepare("\n            SELECT sd.domanda_id\n            FROM sessione_domande sd\n            WHERE sd.sessione_id = ?\n              AND sd.posizione = ?\n            LIMIT 1\n        ");

        $stmt->execute([
            $this->sessioneId,
            $this->sessione['domanda_corrente'],
        ]);

        $row = $stmt->fetch();

        return (int) ($row['domanda_id'] ?? 0);
    }
}
