<?php

declare(strict_types=1);

namespace Lyger\Foundation;

use Exception;

/**
 * Env - Environment configuration loader
 * Inspired by Laravel's Dotenv
 */
class Env
{
    private static ?array $cache = null;

    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            $key = trim($parts[0]);
            $value = isset($parts[1]) ? trim($parts[1]) : '';

            if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                $value = substr($value, 1, -1);
            }

            if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }

        self::$cache = $_ENV;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$cache === null) {
            $envPath = dirname(__DIR__, 2) . '/.env';
            self::load($envPath);
        }

        $value = $_ENV[$key] ?? getenv($key) ?: $default;

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        if ($value === 'null') {
            return null;
        }

        return $value;
    }

    public static function has(string $key): bool
    {
        if (self::$cache === null) {
            $envPath = dirname(__DIR__, 2) . '/.env';
            self::load($envPath);
        }

        return isset($_ENV[$key]) || getenv($key) !== false;
    }

    public static function set(string $key, mixed $value): void
    {
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }

    public static function forget(string $key): void
    {
        unset($_ENV[$key]);
        putenv($key);
    }
}
