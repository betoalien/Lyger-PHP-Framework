<?php
declare(strict_types=1);

namespace Lyger\Database;

use Lyger\Core\Engine;

final class RustDatabaseDriver implements DatabaseDriver
{
    public function __construct(private string $dsn, private ?Engine $engine = null)
    {
        $this->engine ??= Engine::getInstance();
    }

    public function query(string $sql, array $bindings = []): array
    {
        return $this->engine->dbQueryJsonV2($this->dsn, $this->normalizePlaceholders($sql), $bindings);
    }

    public function transaction(array $statements): array
    {
        return $this->engine->dbTransaction($this->dsn, $statements);
    }

    public function name(): string
    {
        return 'rust';
    }

    private function normalizePlaceholders(string $sql): string
    {
        if (!str_starts_with($this->dsn, 'postgres')) {
            return $sql;
        }
        $index = 0;
        return preg_replace_callback('/\?/', static function () use (&$index): string {
            return '$' . (++$index);
        }, $sql);
    }
}
