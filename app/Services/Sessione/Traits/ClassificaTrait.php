<?php

namespace App\Services\Sessione\Traits;

use App\Models\Sistema;

trait ClassificaTrait
{
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
}