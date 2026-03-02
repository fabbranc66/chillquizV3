<?php

namespace App\Services\Sessione\Traits;

trait ConfigQuizTrait
{
    private function loadUnifiedQuizConfig(int $configurazioneId): ?array
    {
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
}