<?php
declare(strict_types=1);

namespace Lyger\Database;

interface DatabaseDriver
{
    public function query(string $sql, array $bindings = []): array;
    public function transaction(array $statements): array;
    public function name(): string;
}
