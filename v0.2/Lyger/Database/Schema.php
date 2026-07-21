<?php

declare(strict_types=1);

namespace Lyger\Database;

/**
 * Schema Builder - Inspired by Laravel's Schema builder
 */
class Schema
{
    private ?string $table = null;
    private array $columns = [];
    private array $indexes = [];
    private array $foreignKeys = [];
    private string $driver;

    public function __construct(?string $driver = null)
    {
        $this->driver = $driver ?? $this->detectDriver();
    }

    private function detectDriver(): string
    {
        if (extension_loaded('pdo_sqlite')) {
            return 'sqlite';
        }
        if (extension_loaded('pdo_mysql')) {
            return 'mysql';
        }
        if (extension_loaded('pdo_pgsql')) {
            return 'pgsql';
        }
        return 'sqlite';
    }

    public function create(string $table, callable $callback): void
    {
        $this->table = $table;
        $this->columns = [];
        $this->indexes = [];
        $this->foreignKeys = [];

        $blueprint = new Blueprint($table, $this->driver);
        $callback($blueprint);

        $this->columns = $blueprint->getColumns();
        $this->indexes = $blueprint->getIndexes();
        $this->foreignKeys = $blueprint->getForeignKeys();

        $this->executeCreate();
    }

    public function table(string $table, callable $callback): void
    {
        $this->table = $table;
        $this->columns = [];
        $this->indexes = [];
        $this->foreignKeys = [];

        $blueprint = new Blueprint($table, $this->driver);
        $callback($blueprint);

        $this->columns = $blueprint->getColumns();
        $this->executeAlter();
    }

    public function drop(string $table): void
    {
        $this->table = $table;
        $sql = "DROP TABLE {$table}";
        $this->execute($sql);
    }

    public function dropIfExists(string $table): void
    {
        $this->table = $table;
        $sql = "DROP TABLE IF EXISTS {$table}";
        $this->execute($sql);
    }

    private function executeCreate(): void
    {
        $sql = $this->buildCreateSql();
        $this->execute($sql);
    }

    private function executeAlter(): void
    {
        $sqls = [];
        foreach ($this->columns as $column) {
            if ($column['action'] === 'add') {
                $sqls[] = "ALTER TABLE {$this->table} ADD COLUMN " . $this->buildColumnSql($column);
            }
        }
        if (!empty($sqls)) {
            $this->execute(implode(";\n", $sqls));
        }
    }

    private function buildCreateSql(): string
    {
        $columnsSql = [];
        foreach ($this->columns as $column) {
            $columnsSql[] = '  ' . $this->buildColumnSql($column);
        }

        $sql = "CREATE TABLE {$this->table} (\n";
        $sql .= implode(",\n", $columnsSql);
        $sql .= "\n)";

        return $sql;
    }

    private function buildColumnSql(array $column): string
    {
        $sql = "{$column['name']} {$column['type']}";

        if (!empty($column['length'])) {
            $sql .= "({$column['length']})";
        }

        if ($column['unsigned'] ?? false) {
            $sql .= ' UNSIGNED';
        }

        if (($column['nullable'] ?? true) === false) {
            $sql .= ' NOT NULL';
        }

        // SQLite AUTOINCREMENT must come AFTER PRIMARY KEY
        if ($column['autoincrement'] ?? false) {
            // For SQLite, INTEGER PRIMARY KEY AUTOINCREMENT is the correct syntax
            $sql = "{$column['name']} INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT";
            return $sql;
        }

        if ($column['primary'] ?? false) {
            $sql .= ' PRIMARY KEY';
        }

        if ($column['default'] !== null) {
            $default = is_string($column['default']) ? "'{$column['default']}'" : $column['default'];
            $sql .= " DEFAULT {$default}";
        }

        return $sql;
    }

    private function execute(string $sql): void
    {
        $pdo = $this->getConnection();
        $pdo->exec($sql);
    }

    private function fetchAll(string $sql): array
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getConnection(): \PDO
    {
        static $pdo = null;

        if ($pdo === null) {
            $dbFile = dirname(__DIR__, 2) . '/database/database.sqlite';
            $dbDir = dirname(__DIR__, 2) . '/database';

            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }

            $pdo = new \PDO("sqlite:{$dbFile}");
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }

        return $pdo;
    }
}

/**
 * Blueprint - Fluent interface for defining table schema
 */
class Blueprint
{
    private string $table;
    private string $driver;
    private array $columns = [];
    private array $indexes = [];
    private array $foreignKeys = [];

    public function __construct(string $table, string $driver = 'sqlite')
    {
        $this->table = $table;
        $this->driver = $driver;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getIndexes(): array
    {
        return $this->indexes;
    }

    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    public function id(string $column = 'id'): self
    {
        return $this->addColumn($column, 'INTEGER', [
            'autoincrement' => true,
            'primary' => true,
            'nullable' => false,
        ]);
    }

    public function bigId(string $column = 'id'): self
    {
        return $this->addColumn($column, 'INTEGER', [
            'autoincrement' => true,
            'primary' => true,
            'nullable' => false,
        ]);
    }

    public function string(string $column, int $length = 255): self
    {
        return $this->addColumn($column, 'VARCHAR', [
            'length' => $length,
            'nullable' => false,
        ]);
    }

    public function text(string $column): self
    {
        return $this->addColumn($column, 'TEXT', [
            'nullable' => true,
        ]);
    }

    public function integer(string $column, bool $unsigned = false): self
    {
        return $this->addColumn($column, 'INTEGER', [
            'unsigned' => $unsigned,
            'nullable' => false,
        ]);
    }

    public function bigInteger(string $column, bool $unsigned = false): self
    {
        return $this->addColumn($column, 'INTEGER', [
            'unsigned' => $unsigned,
            'nullable' => false,
        ]);
    }

    public function decimal(string $column, int $precision = 8, int $scale = 2): self
    {
        return $this->addColumn($column, 'REAL', [
            'nullable' => false,
        ]);
    }

    public function float(string $column): self
    {
        return $this->addColumn($column, 'REAL', [
            'nullable' => false,
        ]);
    }

    public function boolean(string $column): self
    {
        return $this->addColumn($column, 'INTEGER', [
            'default' => 0,
            'nullable' => false,
        ]);
    }

    public function date(string $column): self
    {
        return $this->addColumn($column, 'TEXT', [
            'nullable' => true,
        ]);
    }

    public function datetime(string $column): self
    {
        return $this->addColumn($column, 'TEXT', [
            'nullable' => true,
        ]);
    }

    public function timestamp(string $column): self
    {
        return $this->addColumn($column, 'TEXT', [
            'nullable' => true,
        ]);
    }

    public function timestamps(): self
    {
        return $this->timestamp('created_at')->timestamp('updated_at');
    }

    public function softDeletes(): self
    {
        return $this->timestamp('deleted_at')->nullable();
    }

    public function json(string $column): self
    {
        return $this->addColumn($column, 'TEXT', [
            'nullable' => true,
        ]);
    }

    public function uuid(string $column = 'uuid'): self
    {
        return $this->string($column, 36);
    }

    public function nullable(): self
    {
        $index = count($this->columns) - 1;
        if ($index >= 0) {
            $this->columns[$index]['nullable'] = true;
        }
        return $this;
    }

    public function default(mixed $value): self
    {
        $index = count($this->columns) - 1;
        if ($index >= 0) {
            $this->columns[$index]['default'] = $value;
        }
        return $this;
    }

    public function unsigned(): self
    {
        $index = count($this->columns) - 1;
        if ($index >= 0) {
            $this->columns[$index]['unsigned'] = true;
        }
        return $this;
    }

    public function primary(): self
    {
        $index = count($this->columns) - 1;
        if ($index >= 0) {
            $this->columns[$index]['primary'] = true;
        }
        return $this;
    }

    public function unique(): self
    {
        $index = count($this->columns) - 1;
        if ($index >= 0) {
            $this->indexes[] = [
                'type' => 'UNIQUE',
                'columns' => [$this->columns[$index]['name']],
            ];
        }
        return $this;
    }

    public function index(array $columns = []): self
    {
        if (empty($columns)) {
            $index = count($this->columns) - 1;
            if ($index >= 0) {
                $columns = [$this->columns[$index]['name']];
            }
        }
        $this->indexes[] = [
            'type' => 'INDEX',
            'columns' => $columns,
        ];
        return $this;
    }

    public function addColumn(string $name, string $type, array $options = []): self
    {
        $this->columns[] = array_merge([
            'name' => $name,
            'type' => $type,
            'length' => null,
            'unsigned' => false,
            'nullable' => true,
            'autoincrement' => false,
            'default' => null,
            'primary' => false,
            'action' => 'add',
        ], $options);

        return $this;
    }
}
