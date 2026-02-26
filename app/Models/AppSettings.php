<?php

namespace App\Models;

class AppSettings
{
    private const KEY_SHOW_MODULE_TAGS = 'show_module_tags';

    private Sistema $sistema;

    public function __construct(?Sistema $sistema = null)
    {
        $this->sistema = $sistema ?: new Sistema();
    }

    public function all(): array
    {
        $rows = $this->sistema->tutteConfigurazioni();
        $config = [];

        foreach ($rows as $row) {
            $chiave = (string) ($row['chiave'] ?? '');
            if ($chiave === '') {
                continue;
            }

            $config[$chiave] = (string) ($row['valore'] ?? '');
        }

        $moduleTagsValue = $config[self::KEY_SHOW_MODULE_TAGS] ?? getenv('SHOW_MODULE_TAGS') ?? '1';

        return [
            'show_module_tags' => $this->normalizeBool($moduleTagsValue),
            'configurazioni_sistema' => $config,
        ];
    }

    public function setShowModuleTags(bool $enabled): void
    {
        $this->sistema->set(self::KEY_SHOW_MODULE_TAGS, $enabled ? '1' : '0');
    }

    public function saveConfigurazioni(array $configurazioni): void
    {
        foreach ($configurazioni as $chiave => $valore) {
            $key = trim((string) $chiave);
            if ($key === '') {
                continue;
            }

            $this->sistema->set($key, (string) $valore);
        }
    }

    private function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        $string = strtolower(trim((string) $value));

        if (in_array($string, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($string, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return true;
    }
}
