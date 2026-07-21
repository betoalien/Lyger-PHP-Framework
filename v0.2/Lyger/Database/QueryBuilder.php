<?php

declare(strict_types=1);

namespace Lyger\Database;

use Lyger\Container\Container;
use Lyger\Foundation\Path;

/**
 * QueryBuilder - Fluent SQL query builder inspired by Laravel
 */
class QueryBuilder
{
    private ?\PDO $connection = null;
    private ?DatabaseDriver $driver = null;
    private string $table;
    private array $columns = ['*'];
    private array $wheres = [];
    private array $whereBindings = [];
    private ?array $orderBy = null;
    private ?int $limit = null;
    private ?int $offset = null;
    private array $joins = [];
    private array $updates = [];

    public function __construct(string $table, ?DatabaseDriver $driver = null)
    {
        $this->table = $table;
        $this->driver = $driver ?? DatabaseManager::make();
    }

    public static function table(string $table): self
    {
        return new self($table);
    }

    public function using(DatabaseDriver $driver): self
    {
        $this->driver = $driver;
        return $this;
    }

    public function select(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function where(string $column, $operator, $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'type' => 'basic',
        ];

        $this->whereBindings[] = $value;
        return $this;
    }

    public function orWhere(string $column, $operator, $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'type' => 'or',
        ];

        $this->whereBindings[] = $value;
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'column' => $column,
            'values' => $values,
            'type' => 'in',
        ];

        $this->whereBindings = array_merge($this->whereBindings, $values);
        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'column' => $column,
            'type' => 'null',
        ];
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'column' => $column,
            'type' => 'not_null',
        ];
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy = ['column' => $column, 'direction' => strtoupper($direction)];
        return $this;
    }

    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'DESC');
    }

    public function oldest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'ASC');
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $total = $this->count();

        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);

        $items = $this->get();

        return [
            'data' => $items,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage),
            'from' => ($page - 1) * $perPage + 1,
            'to' => min($page * $perPage, $total),
        ];
    }

    public function join(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => 'INNER',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => 'LEFT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];
        return $this;
    }

    public function get(): array
    {
        $sql = $this->buildSelect();
        if ($this->driver !== null) {
            return $this->driver->query($sql, $this->whereBindings)['rows'] ?? [];
        }
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($this->whereBindings);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    public function value(string $column): mixed
    {
        $result = $this->select([$column])->first();
        return $result[$column] ?? null;
    }

    public function count(): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $sql .= $this->buildWheres();

        if ($this->driver !== null) {
            $rows = $this->driver->query($sql, $this->whereBindings)['rows'] ?? [];
            return (int) ($rows[0]['count'] ?? 0);
        }
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($this->whereBindings);
        return (int) $stmt->fetch(\PDO::FETCH_ASSOC)['count'];
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function insert(array $data): bool
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";

        if ($this->driver !== null) {
            return (int) ($this->driver->query($sql, array_values($data))['affected_rows'] ?? 0) > 0;
        }
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute(array_values($data));
    }

    public function insertGetId(array $data): int|string
    {
        if ($this->driver !== null) {
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $result = $this->driver->query("INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})", array_values($data));
            return $result['last_insert_id'] ?? '';
        }
        $this->insert($data);
        return $this->getConnection()->lastInsertId();
    }

    public function update(array $data): int
    {
        $sets = [];
        foreach (array_keys($data) as $key) {
            $sets[] = "{$key} = ?";
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);
        $sql .= $this->buildWheres();

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->driver !== null) {
            return (int) ($this->driver->query($sql, array_merge(array_values($data), $this->whereBindings))['affected_rows'] ?? 0);
        }
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute(array_merge(array_values($data), $this->whereBindings));
        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}";
        $sql .= $this->buildWheres();

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->driver !== null) {
            return (int) ($this->driver->query($sql, $this->whereBindings)['affected_rows'] ?? 0);
        }
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($this->whereBindings);
        return $stmt->rowCount();
    }

    private function buildSelect(): string
    {
        $columns = implode(', ', $this->columns);
        $sql = "SELECT {$columns} FROM {$this->table}";

        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        $sql .= $this->buildWheres();

        if ($this->orderBy !== null) {
            $sql .= " ORDER BY {$this->orderBy['column']} {$this->orderBy['direction']}";
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }

    private function buildWheres(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $sql = ' WHERE ';
        $conditions = [];

        foreach ($this->wheres as $where) {
            switch ($where['type']) {
                case 'basic':
                    $conditions[] = "{$where['column']} {$where['operator']} ?";
                    break;
                case 'or':
                    $conditions[] = "OR {$where['column']} {$where['operator']} ?";
                    break;
                case 'in':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                    $conditions[] = "{$where['column']} IN ({$placeholders})";
                    break;
                case 'null':
                    $conditions[] = "{$where['column']} IS NULL";
                    break;
                case 'not_null':
                    $conditions[] = "{$where['column']} IS NOT NULL";
                    break;
            }
        }

        return $sql . implode(' ', $conditions);
    }

    private function getConnection(): \PDO
    {
        if ($this->connection === null) {
            $dbFile = Path::database('database.sqlite');
            $dbDir = Path::database();

            Path::ensureDirectory($dbDir);

            $this->connection = new \PDO("sqlite:{$dbFile}");
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }

        return $this->connection;
    }

    private function activeDriver(): DatabaseDriver
    {
        return $this->driver ??= DatabaseManager::make();
    }
}
