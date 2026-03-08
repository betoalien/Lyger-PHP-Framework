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
    private string $table;
    private array $columns = ['*'];
    private array $wheres = [];
    private array $bindings = [];
    private ?array $orderBy = null;
    private ?int $limit = null;
    private ?int $offset = null;
    private array $joins = [];
    private array $updates = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public static function table(string $table): self
    {
        return new self($table);
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

        $this->bindings[] = $value;
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

        $this->bindings[] = $value;
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'column' => $column,
            'values' => $values,
            'type' => 'in',
        ];

        $this->bindings = array_merge($this->bindings, $values);
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
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($this->bindings);
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

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($this->bindings);
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

        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute(array_values($data));
    }

    public function update(array $data): int
    {
        $sets = [];
        foreach (array_keys($data) as $key) {
            $sets[] = "{$key} = ?";
            $this->bindings[] = $data[$key];
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);
        $sql .= $this->buildWheres();

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}";
        $sql .= $this->buildWheres();

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($this->bindings);
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
}
