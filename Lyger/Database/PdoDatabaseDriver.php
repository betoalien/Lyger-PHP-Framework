<?php
declare(strict_types=1);

namespace Lyger\Database;

final class PdoDatabaseDriver implements DatabaseDriver
{
    public function __construct(private \PDO $connection)
    {
    }

    public function query(string $sql, array $bindings = []): array
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute($bindings);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function transaction(array $statements): array
    {
        $this->connection->beginTransaction();
        try {
            $last = [];
            foreach ($statements as $statement) {
                $last = $this->query($statement['sql'], $statement['bindings'] ?? []);
            }
            $this->connection->commit();
            return $last;
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        }
    }

    public function name(): string
    {
        return 'pdo-fallback';
    }
}
