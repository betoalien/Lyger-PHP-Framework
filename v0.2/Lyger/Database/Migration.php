<?php

declare(strict_types=1);

namespace Lyger\Database;

/**
 * Migration - Base class for database migrations
 */
abstract class Migration
{
    abstract public function up(): void;
    abstract public function down(): void;

    public function getConnection(): \PDO
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

    public function getSchema(): Schema
    {
        return new Schema();
    }
}

/**
 * Migrator - Manages database migrations
 */
class Migrator
{
    private string $migrationsPath;
    private array $migrations = [];

    public function __construct(?string $migrationsPath = null)
    {
        $this->migrationsPath = $migrationsPath ?? dirname(__DIR__, 2) . '/database/migrations';

        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }
    }

    public function path(string $path): self
    {
        $this->migrationsPath = $path;
        return $this;
    }

    public function getMigrationsPath(): string
    {
        return $this->migrationsPath;
    }

    public function getMigrations(): array
    {
        return $this->migrations;
    }

    public function loadMigrations(): void
    {
        $files = glob($this->migrationsPath . '/*.php');

        foreach ($files as $file) {
            require_once $file;
            $class = basename($file, '.php');
            $this->migrations[$class] = $file;
        }
    }

    public function run(): void
    {
        $this->createMigrationsTable();
        $this->loadMigrations();

        $ran = $this->getRanMigrations();

        foreach ($this->migrations as $name => $file) {
            if (!in_array($name, $ran)) {
                echo "Running migration: {$name}\n";

                $class = new $name();
                $class->up();

                $this->recordMigration($name);
                echo "Migrated:  {$name}\n";
            }
        }
    }

    public function rollback(): void
    {
        $this->loadMigrations();
        $ran = $this->getRanMigrations();

        foreach (array_reverse($ran) as $name) {
            if (isset($this->migrations[$name])) {
                echo "Rolling back migration: {$name}\n";

                $class = new $name();
                $class->down();

                $this->removeMigration($name);
                echo "Rolled back:  {$name}\n";
            }
        }
    }

    public function reset(): void
    {
        $this->loadMigrations();

        $ran = $this->getRanMigrations();

        foreach (array_reverse($ran) as $name) {
            if (isset($this->migrations[$name])) {
                $class = new $name();
                $class->down();
            }
        }

        $this->getConnection()->exec('DELETE FROM migrations');
    }

    public function status(): void
    {
        $this->loadMigrations();
        $ran = $this->getRanMigrations();

        echo "\n| Migration |\n|----------|\n";

        foreach ($this->migrations as $name => $file) {
            $status = in_array($name, $ran) ? 'Ran' : 'Pending';
            echo "| {$name} | {$status} |\n";
        }

        echo "\n";
    }

    private function createMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration VARCHAR(255) NOT NULL,
            batch INTEGER NOT NULL
        )";

        $this->getConnection()->exec($sql);
    }

    private function getRanMigrations(): array
    {
        $stmt = $this->getConnection()->query('SELECT migration FROM migrations ORDER BY batch DESC');
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function recordMigration(string $name): void
    {
        $batch = $this->getNextBatchNumber();
        $stmt = $this->getConnection()->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)');
        $stmt->execute([$name, $batch]);
    }

    private function removeMigration(string $name): void
    {
        $stmt = $this->getConnection()->prepare('DELETE FROM migrations WHERE migration = ?');
        $stmt->execute([$name]);
    }

    private function getNextBatchNumber(): int
    {
        $stmt = $this->getConnection()->query('SELECT MAX(batch) as batch FROM migrations');
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return ($result['batch'] ?? 0) + 1;
    }

    public function make(string $name): void
    {
        $timestamp = date('Y_m_d_His');
        $filename = $timestamp . '_' . $name . '.php';
        $filepath = $this->migrationsPath . '/' . $filename;

        $className = $this->generateClassName($name);

        $content = <<<PHP
<?php

declare(strict_types=1);

use Lyger\Database\Migration;

class {$className} extends Migration
{
    public function up(): void
    {
        \$this->getSchema()->create('{$this->tableName($name)}', function (\$table) {
            \$table->id();
            // Add your columns here
            // \$table->string('name');
            // \$table->timestamps();
        });
    }

    public function down(): void
    {
        \$this->getSchema()->drop('{$this->tableName($name)}');
    }
}
PHP;

        file_put_contents($filepath, $content);
        echo "Created migration: {$filename}\n";
    }

    private function generateClassName(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
    }

    private function tableName(string $name): string
    {
        return strtolower(str_replace(['-', '_'], '', $name));
    }
}
