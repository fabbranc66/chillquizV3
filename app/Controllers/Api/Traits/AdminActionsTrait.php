<?php

namespace App\Controllers\Api\Traits;

use App\Models\AppSettings;
use App\Models\JoinRichiesta;
use App\Models\ScreenMedia;
use App\Models\Sessione;
use App\Services\SessioneService;
use Throwable;

trait AdminActionsTrait
{
    public function admin($action, $sessioneId): void
    {
        $sessioneId = (int)$sessioneId;

        if (!$this->requirePost()) return;

        if (!$this->isAdminAuthorized()) {
            http_response_code(403);
            $this->jsonFail('Token admin non valido');
            return;
        }

        try {
            switch ($action) {

                case 'avvia-puntata':
                    (new SessioneService($sessioneId))->avviaPuntata();
                    break;

                case 'avvia-domanda':
                    (new SessioneService($sessioneId))->avviaDomanda();
                    break;

                case 'riavvia':
                    (new SessioneService($sessioneId))->resetTotale();
                    break;

                case 'media-list':
                    $this->jsonOk([
                        'media' => (new ScreenMedia())->lista()
                    ]);
                    return;

                default:
                    $this->jsonFail('Azione non valida');
                    return;
            }

            $this->jsonOk(['action' => $action]);

        } catch (Throwable $e) {
            $this->jsonFail($e->getMessage());
        }
    }
}