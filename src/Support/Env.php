<?php

declare(strict_types=1);

namespace WatchScraper\Support;

final class Env
{
    public static function string(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        return is_string($value) && $value !== '' ? $value : $default;
    }

    public static function nullableString(string $key): ?string
    {
        $value = self::string($key);
        return $value === '' ? null : $value;
    }

    public static function int(string $key, int $default): int
    {
        $value = self::string($key);
        return $value === '' ? $default : (int) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = strtolower(self::string($key, $default ? 'true' : 'false'));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}
