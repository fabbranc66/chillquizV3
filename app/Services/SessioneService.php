<?php

namespace App\Services;

use App\Core\Database;

class SessioneService
{
    use Sessione\Traits\SessionLoaderTrait;
    use Sessione\Traits\StatoTrait;
    use Sessione\Traits\TransizioniTrait;
    use Sessione\Traits\TimerTrait;

    use Sessione\Traits\ConfigQuizTrait;
    use Sessione\Traits\PoolDomandeTrait;
    use Sessione\Traits\PuntateLiveTrait;
    use Sessione\Traits\ClassificaTrait;

    protected \PDO $pdo;
    protected int $sessioneId;
    protected array $sessione;

    public function __construct(int $sessioneId)
    {
        $this->pdo = Database::getInstance();
        $this->sessioneId = $sessioneId;
        $this->loadSessione();
    }
}