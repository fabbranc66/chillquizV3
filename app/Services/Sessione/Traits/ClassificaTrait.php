<?php

namespace App\Services\Sessione\Traits;

use App\Models\Sistema;

trait ClassificaTrait
{
    public function classifica(): array
    {
        $this->ensurePuntateLiveTable();

        $sistema = new Sistema();

        $domandaId = $this->domandaCorrenteId();
        $durataDomanda = (int) ($sistema->get('durata_domanda') ?? 0);
        $fattoreVelocitaMax = (float) ($sistema->get('fattore_velocita_max') ?? 1);
        $bonusPrimoAttivo = (int) ($sistema->get('bonus_primo_attivo') ?? 0) === 1;
        $coefficienteBonusPrimo = (float) ($sistema->get('coefficiente_bonus_primo') ?? 0);

        $stmt = $this->pdo->prepare(
            "SELECT
                p.id AS partecipazione_id,
                p.utente_id,
                u.nome,
                p.capitale_attuale,
                COALESCE(pl.importo, r_corrente.puntata, 0) AS ultima_puntata,
                r_corrente.id AS risposta_id,
                r_corrente.corretta AS esito_corretta,
                r_corrente.tempo_risposta,
                r_corrente.punti AS vincita_domanda_raw,
                d_corrente.difficolta AS difficolta_domanda,
                primo.partecipazione_id AS primo_partecipazione_id
             FROM partecipazioni p
             JOIN utenti u ON u.id = p.utente_id
             LEFT JOIN puntate_live pl
                ON pl.sessione_id = p.sessione_id
               AND pl.partecipazione_id = p.id
             LEFT JOIN (
                SELECT r1.id, r1.partecipazione_id, r1.puntata, r1.corretta, r1.tempo_risposta, r1.punti
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
                p.capitale_attuale DESC"
        );

        $stmt->execute([
            'domanda_id_risposte' => $domandaId,
            'domanda_id_domanda' => $domandaId,
            'domanda_id_primo' => $domandaId,
            'sessione_id' => $this->sessioneId,
        ]);

        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['ultima_puntata'] = (int) ($row['ultima_puntata'] ?? 0);
            $row['capitale_attuale'] = (int) ($row['capitale_attuale'] ?? 0);
            $row['esito'] = ($row['esito_corretta'] === null)
                ? null
                : ((int) $row['esito_corretta'] === 1 ? 'corretta' : 'errata');
            $row['tempo_risposta'] = $row['tempo_risposta'] === null ? null : round((float) $row['tempo_risposta'], 3);
            $row['difficolta_domanda'] = $row['difficolta_domanda'] === null ? null : (float) $row['difficolta_domanda'];
            $row['durata_domanda'] = $durataDomanda;
            $row['primo_a_rispondere'] = isset($row['primo_partecipazione_id'])
                ? ((int) $row['primo_partecipazione_id'] === (int) $row['partecipazione_id'])
                : false;

            $puntata = (int) $row['ultima_puntata'];
            $difficolta = (float) ($row['difficolta_domanda'] ?? 1.0);
            $tempoRisposta = $row['tempo_risposta'] === null ? null : (float) $row['tempo_risposta'];
            $vincitaDomandaRaw = $row['vincita_domanda_raw'] === null ? null : (int) $row['vincita_domanda_raw'];

            $fattoreVelocita = 0.0;
            if ($tempoRisposta !== null && $durataDomanda > 0) {
                $tempoRimanente = max(0, $durataDomanda - $tempoRisposta);
                $fattoreVelocita = round(($tempoRimanente / $durataDomanda) * $fattoreVelocitaMax, 2);
            }

            $vincitaDifficolta = 0;
            $vincitaVelocita = 0;
            $bonusPrimo = 0;
            $vincitaDomandaCalcolata = null;

            if ($row['esito'] === 'corretta') {
                $vincitaDifficolta = (int) round($puntata * $difficolta);
                $vincitaVelocita = (int) round($puntata * $fattoreVelocita);

                if ($bonusPrimoAttivo && !empty($row['primo_a_rispondere'])) {
                    $bonusPrimo = (int) round($puntata * $coefficienteBonusPrimo);
                }

                $vincitaDomandaCalcolata = $vincitaDifficolta + $vincitaVelocita + $bonusPrimo;
            } elseif ($row['esito'] === 'errata') {
                $vincitaDomandaCalcolata = -$puntata;
            }

            if (
                $vincitaDomandaCalcolata !== null
                && $vincitaDomandaRaw !== null
                && $vincitaDomandaCalcolata !== $vincitaDomandaRaw
            ) {
                $delta = $vincitaDomandaCalcolata - $vincitaDomandaRaw;
                $this->allineaPunteggioPersistito(
                    (int) ($row['risposta_id'] ?? 0),
                    (int) ($row['partecipazione_id'] ?? 0),
                    $vincitaDomandaCalcolata,
                    $delta
                );
                $row['capitale_attuale'] += $delta;
            }

            $row['vincita_domanda'] = $vincitaDomandaCalcolata;
            $row['fattore_velocita'] = $fattoreVelocita;
            $row['vincita_difficolta'] = $vincitaDifficolta;
            $row['vincita_velocita'] = $vincitaVelocita;
            $row['bonus_primo'] = $bonusPrimo;

            unset($row['esito_corretta']);
            unset($row['primo_partecipazione_id']);
            unset($row['vincita_domanda_raw']);
            unset($row['risposta_id']);
        }
        unset($row);

        return $rows;
    }

    private function allineaPunteggioPersistito(
        int $rispostaId,
        int $partecipazioneId,
        int $puntiCorretti,
        int $delta
    ): void {
        if ($rispostaId <= 0 || $partecipazioneId <= 0 || $delta === 0) {
            return;
        }

        $started = false;

        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $started = true;
            }

            $updateRisposta = $this->pdo->prepare(
                "UPDATE risposte
                 SET punti = :punti
                 WHERE id = :id"
            );

            $updateRisposta->execute([
                'punti' => $puntiCorretti,
                'id' => $rispostaId,
            ]);

            $updatePartecipazione = $this->pdo->prepare(
                "UPDATE partecipazioni
                 SET capitale_attuale = capitale_attuale + :delta
                 WHERE id = :id"
            );

            $updatePartecipazione->execute([
                'delta' => $delta,
                'id' => $partecipazioneId,
            ]);

            if ($started) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
    }
}
