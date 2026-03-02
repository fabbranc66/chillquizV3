<?php

namespace App\Controllers\Api\Traits;

trait AdminAuthTrait
{
    private function getAdminToken(): string
    {
        $token = getenv('ADMIN_TOKEN');
        return is_string($token) && $token !== '' ? $token : 'SUPERSEGRETO123';
    }

    private function isAdminAuthorized(): bool
    {
        $incoming = $this->getRequestHeader('X-ADMIN-TOKEN');

        if ($incoming === null || $incoming === '') {
            return false;
        }

        return hash_equals($this->getAdminToken(), $incoming);
    }
}