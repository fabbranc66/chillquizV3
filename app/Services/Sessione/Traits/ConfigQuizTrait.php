<?php

namespace App\Services\Sessione\Traits;

trait ConfigQuizTrait
{
    private function loadUnifiedQuizConfig(int $configurazioneId): ?array
    {
        $numero = isset($this->sessione['numero_domande']) ? (int) $this->sessione['numero_domande'] : 0;

        if ($numero > 0) {
            $poolRaw = (string) ($this->sessione['pool_tipo'] ?? 'tutti');
            $poolTipo = $poolRaw === 'sarabanda'
                ? 'sarabanda'
                : (($poolRaw === 'mono') ? 'mono' : 'tutti');

            return [
                'source' => 'sessione',
                'numero_domande' => $numero,
                'pool_tipo' => $poolTipo,
                'argomento_id' => $this->sessione['argomento_id'] !== null ? (int) $this->sessione['argomento_id'] : null,
                'selezione_tipo' => ($this->sessione['selezione_tipo'] ?? 'random') === 'manuale' ? 'manuale' : 'random',
                'modalita' => null,
            ];
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, numero_domande, modalita, argomento_id, selezione_tipo
             FROM configurazioni_quiz_v2
             WHERE id = ?
             LIMIT 1"
        );
        $stmt->execute([$configurazioneId]);
        $v2 = $stmt->fetch();

        if (!$v2) {
            return null;
        }

        $poolTipo = 'tutti';
        if (($v2['modalita'] ?? '') !== 'mista') {
            $poolTipo = 'mono';
        }

        return [
            'source' => 'v2',
            'numero_domande' => (int) $v2['numero_domande'],
            'pool_tipo' => $poolTipo,
            'argomento_id' => $v2['argomento_id'] !== null ? (int) $v2['argomento_id'] : null,
            'selezione_tipo' => ($v2['selezione_tipo'] ?? 'auto') === 'manuale' ? 'manuale' : 'random',
            'modalita' => $v2['modalita'] ?? 'mista',
        ];
    }
}