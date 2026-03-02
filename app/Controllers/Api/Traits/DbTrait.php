<?php

namespace App\Controllers\Api\Traits;

trait DbTrait
{
    private function pdo(): \PDO
    {
        return \App\Core\Database::getInstance();
    }
}