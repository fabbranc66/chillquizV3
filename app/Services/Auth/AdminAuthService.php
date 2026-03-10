<?php

namespace App\Services\Auth;

use App\Core\Database;
use PDO;

final class AdminAuthService
{
    private const SESSION_AUTH_KEY = 'chillquiz_admin_authenticated';
    private const SESSION_USER_KEY = 'chillquiz_admin_username';
    private const SESSION_API_TOKEN_KEY = 'chillquiz_admin_api_token';
    private const TABLE_NAME = 'admin_users';
    private const USERS_FILE = STORAGE_PATH . '/auth/admin_users.json';
    private const DEFAULT_USERNAME = 'admin';
    private const DEFAULT_PASSWORD_HASH = '$2y$10$EZjh2Z.ZhJF3QmCVmgfqhuQaM3NGklkWjXgXfu44gS258dFkfVc1C'; // ChillQuiz!2026

    private static bool $schemaReady = false;

    private function pdo(): PDO
    {
        return Database::getInstance();
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        $this->pdo()->exec(
            "CREATE TABLE IF NOT EXISTS " . self::TABLE_NAME . " (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                attivo TINYINT(1) NOT NULL DEFAULT 1,
                creato_il TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                aggiornato_il TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY ux_admin_users_username (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$schemaReady = true;
        $this->migrateUsersFileIfNeeded();
    }

    private function ensureUsersStorage(): void
    {
        $dir = dirname(self::USERS_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private function loadUsersFromFile(): array
    {
        $this->ensureUsersStorage();

        if (!is_file(self::USERS_FILE)) {
            return [];
        }

        $raw = @file_get_contents(self::USERS_FILE);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $users = [];
        foreach ($decoded as $row) {
            $username = trim((string) ($row['username'] ?? ''));
            $passwordHash = trim((string) ($row['password_hash'] ?? ''));
            if ($username === '' || $passwordHash === '') {
                continue;
            }
            $users[$username] = [
                'username' => $username,
                'password_hash' => $passwordHash,
            ];
        }

        return $users;
    }

    private function loadUsersFromDb(): array
    {
        $this->ensureSchema();
        $stmt = $this->pdo()->query(
            "SELECT username, password_hash
             FROM " . self::TABLE_NAME . "
             WHERE attivo = 1
             ORDER BY username ASC"
        );
        $rows = $stmt ? ($stmt->fetchAll() ?: []) : [];

        $users = [];
        foreach ($rows as $row) {
            $username = trim((string) ($row['username'] ?? ''));
            $passwordHash = trim((string) ($row['password_hash'] ?? ''));
            if ($username === '' || $passwordHash === '') {
                continue;
            }
            $users[$username] = [
                'username' => $username,
                'password_hash' => $passwordHash,
            ];
        }

        return $users;
    }

    private function migrateUsersFileIfNeeded(): void
    {
        $fileUsers = $this->loadUsersFromFile();
        if ($fileUsers === []) {
            return;
        }

        $stmt = $this->pdo()->query("SELECT COUNT(*) AS c FROM " . self::TABLE_NAME);
        $count = (int) (($stmt->fetch()['c'] ?? 0));
        if ($count > 0) {
            return;
        }

        $insert = $this->pdo()->prepare(
            "INSERT INTO " . self::TABLE_NAME . " (username, password_hash, attivo)
             VALUES (:username, :password_hash, 1)
             ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), attivo = 1"
        );

        foreach ($fileUsers as $user) {
            $insert->execute([
                'username' => (string) $user['username'],
                'password_hash' => (string) $user['password_hash'],
            ]);
        }
    }

    public function listUsers(): array
    {
        return array_values($this->loadUsersFromDb());
    }

    public function upsertUser(string $username, string $plainPassword): bool
    {
        $username = trim($username);
        if ($username === '' || $plainPassword === '') {
            return false;
        }

        $this->ensureSchema();
        $stmt = $this->pdo()->prepare(
            "INSERT INTO " . self::TABLE_NAME . " (username, password_hash, attivo)
             VALUES (:username, :password_hash, 1)
             ON DUPLICATE KEY UPDATE
                password_hash = VALUES(password_hash),
                attivo = 1"
        );

        return $stmt->execute([
            'username' => $username,
            'password_hash' => password_hash($plainPassword, PASSWORD_DEFAULT),
        ]);
    }

    public function isAuthenticated(): bool
    {
        return !empty($_SESSION[self::SESSION_AUTH_KEY]);
    }

    public function getAuthenticatedUsername(): string
    {
        return (string) ($_SESSION[self::SESSION_USER_KEY] ?? '');
    }

    public function getLoginUsername(): string
    {
        $users = $this->loadUsersFromDb();
        if ($users !== []) {
            $first = array_key_first($users);
            return is_string($first) && $first !== '' ? $first : self::DEFAULT_USERNAME;
        }

        $username = getenv('ADMIN_USERNAME');
        $username = is_string($username) ? trim($username) : '';
        return $username !== '' ? $username : self::DEFAULT_USERNAME;
    }

    public function verifyCredentials(string $username, string $password): bool
    {
        $username = trim($username);
        $users = $this->loadUsersFromDb();

        if ($users !== []) {
            $user = $users[$username] ?? null;
            if (!is_array($user)) {
                return false;
            }

            return password_verify($password, (string) ($user['password_hash'] ?? ''));
        }

        if (!hash_equals($this->getLoginUsername(), $username)) {
            return false;
        }
        $plainPassword = getenv('ADMIN_PASSWORD');
        if (is_string($plainPassword) && $plainPassword !== '') {
            return hash_equals($plainPassword, $password);
        }

        $hash = getenv('ADMIN_PASSWORD_HASH');
        $hash = is_string($hash) && trim($hash) !== '' ? trim($hash) : self::DEFAULT_PASSWORD_HASH;
        return password_verify($password, $hash);
    }

    public function login(string $username): void
    {
        $_SESSION[self::SESSION_AUTH_KEY] = true;
        $_SESSION[self::SESSION_USER_KEY] = trim($username);
        $_SESSION[self::SESSION_API_TOKEN_KEY] = bin2hex(random_bytes(32));
        session_regenerate_id(true);
    }

    public function logout(): void
    {
        unset(
            $_SESSION[self::SESSION_AUTH_KEY],
            $_SESSION[self::SESSION_USER_KEY],
            $_SESSION[self::SESSION_API_TOKEN_KEY]
        );
        session_regenerate_id(true);
    }

    public function getApiToken(): string
    {
        if (!$this->isAuthenticated()) {
            return '';
        }

        $token = (string) ($_SESSION[self::SESSION_API_TOKEN_KEY] ?? '');
        if ($token !== '') {
            return $token;
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_API_TOKEN_KEY] = $token;
        return $token;
    }

    public function isApiAuthorized(?string $incomingHeaderToken = null, ?string $incomingQueryToken = null): bool
    {
        if ($this->isAuthenticated()) {
            return true;
        }

        $configuredToken = getenv('ADMIN_TOKEN');
        $configuredToken = is_string($configuredToken) ? trim($configuredToken) : '';
        if ($configuredToken === '') {
            return false;
        }

        foreach ([$incomingHeaderToken, $incomingQueryToken] as $incoming) {
            $candidate = is_string($incoming) ? trim($incoming) : '';
            if ($candidate !== '' && hash_equals($configuredToken, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
