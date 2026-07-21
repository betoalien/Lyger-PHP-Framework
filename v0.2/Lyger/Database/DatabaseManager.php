<?php
declare(strict_types=1);

namespace Lyger\Database;

use Lyger\Core\Engine;
use Lyger\Foundation\Env;

final class DatabaseManager
{
    public static function make(?string $dsn = null): DatabaseDriver
    {
        $dsn ??= (string) Env::get('DB_DSN', 'sqlite:' . dirname(__DIR__, 2) . '/database/database.sqlite');
        $driver = strtolower((string) Env::get('DB_DRIVER', 'rust'));
        if ($driver === 'rust') {
            return new RustDatabaseDriver($dsn);
        }
        if ($driver !== 'pdo') {
            throw new \InvalidArgumentException("Unsupported database driver: {$driver}");
        }
        if (str_starts_with($dsn, 'sqlite:')) {
            $pdo = new \PDO($dsn);
        } else {
            throw new \InvalidArgumentException('PDO fallback currently supports sqlite DSNs only');
        }
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return new PdoDatabaseDriver($pdo);
    }

    public static function health(?string $dsn = null): bool
    {
        $dsn ??= (string) Env::get('DB_DSN', 'sqlite:' . dirname(__DIR__, 2) . '/database/database.sqlite');
        if (strtolower((string) Env::get('DB_DRIVER', 'rust')) === 'rust') {
            return Engine::getInstance()->dbHealth($dsn);
        }
        try {
            self::make($dsn)->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function healthWithRetry(?string $dsn = null, int $attempts = 3, int $backoffMs = 100): bool
    {
        $dsn ??= (string) Env::get('DB_DSN', 'sqlite:' . dirname(__DIR__, 2) . '/database/database.sqlite');
        if (strtolower((string) Env::get('DB_DRIVER', 'rust')) === 'rust') {
            return Engine::getInstance()->dbHealthWithRetry($dsn, $attempts, $backoffMs);
        }
        return self::health($dsn);
    }
}
