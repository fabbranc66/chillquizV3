<?php

namespace App\Controllers\Api\Traits;

use App\Models\JoinRichiesta;
use App\Models\Partecipazione;
use App\Models\ScreenMedia;
use App\Models\Utente;
use App\Services\SessioneService;
use Throwable;

trait PlayerActionsTrait
{
    /* ======================
       PUBLIC READ
    ====================== */

    public function stato($sessioneId): void
    {
        $sessioneId = (int)$sessioneId;

        try {
            $service = new SessioneService($sessioneId);
            $service->verificaTimer();

            $stmt = $this->pdo()->prepare("SELECT * FROM sessioni WHERE id = ?");
            $stmt->execute([$sessioneId]);

            $this->jsonOk([
                'sessione' => $stmt->fetch()
            ]);
        } catch (Throwable $e) {
            $this->jsonFail($e->getMessage());
        }
    }

    public function domanda($sessioneId): void
    {
        $sessioneId = (int)$sessioneId;

        try {
            $service = new SessioneService($sessioneId);
            $this->jsonOk(['domanda' => $service->domandaCorrente()]);
        } catch (Throwable $e) {
            $this->jsonFail($e->getMessage());
        }
    }

    public function classifica($sessioneId): void
    {
        $sessioneId = (int)$sessioneId;

        try {
            $service = new SessioneService($sessioneId);
            $this->jsonOk(['classifica' => $service->classifica()]);
        } catch (Throwable $e) {
            $this->jsonFail($e->getMessage());
        }
    }

    /* ======================
       JOIN
    ====================== */

    public function join($sessioneId): void
    {
        $sessioneId = (int)$sessioneId;

        if (!$this->requirePost()) return;

        $nome = trim((string)($_POST['nome'] ?? ''));

        if ($nome === '') {
            $this->jsonFail('Nome non valido');
            return;
        }

        try {

            $check = $this->pdo()->prepare("
                SELECT p.id
                FROM partecipazioni p
                JOIN utenti u ON u.id = p.utente_id
                WHERE p.sessione_id = :sessione_id
                  AND LOWER(u.nome) = LOWER(:nome)
                LIMIT 1
            ");

            $check->execute([
                'sessione_id' => $sessioneId,
                'nome' => $nome
            ]);

            $esistente = $check->fetch();

            if ($esistente) {
                $joinRichiesta = new JoinRichiesta();
                $richiesta = $joinRichiesta->creaORiprendiPending(
                    $sessioneId,
                    $nome,
                    (int)$esistente['id']
                );

                $this->json([
                    'success' => false,
                    'requires_approval' => true,
                    'request_id' => (int)$richiesta['id'],
                    'error' => 'Nome già utilizzato: richiesta inviata alla regia'
                ]);
                return;
            }

            $utenteId = (new Utente())->creaTemporaneo($nome);

            $partecipazioneModel = new Partecipazione();
            $partecipazioneId = $partecipazioneModel->entra($sessioneId, $utenteId);
            $partecipazione = $partecipazioneModel->trova($partecipazioneId);

            $this->jsonOk([
                'utente_id' => $utenteId,
                'partecipazione_id' => $partecipazioneId,
                'capitale' => $partecipazione['capitale_attuale'] ?? 0
            ]);

        } catch (Throwable $e) {
            $this->jsonFail($e->getMessage());
        }
    }

    public function mediaAttiva(): void
    {
        try {
            $media = (new ScreenMedia())->mediaAttivaRandom();
            $this->jsonOk(['media' => $media]);
        } catch (Throwable $e) {
            $this->jsonFail($e->getMessage());
        }
    }
}