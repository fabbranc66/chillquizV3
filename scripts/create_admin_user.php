<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use App\Services\Auth\AdminAuthService;

$username = trim((string) ($argv[1] ?? ''));
$password = (string) ($argv[2] ?? '');

if ($username === '' || $password === '') {
    fwrite(STDERR, "Uso: php scripts/create_admin_user.php <username> <password>\n");
    exit(1);
}

$auth = new AdminAuthService();
$ok = $auth->upsertUser($username, $password);

if (!$ok) {
    fwrite(STDERR, "Impossibile salvare l'utente admin.\n");
    exit(1);
}

fwrite(STDOUT, "Utente admin salvato: {$username}\n");
exit(0);
