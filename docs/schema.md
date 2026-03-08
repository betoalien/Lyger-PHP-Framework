---
layout: default
title: Schema & Migrations
nav_order: 9
---

# Schema & Migrations

---

## Schema Builder

The `Lyger\Database\Schema` class provides a fluent interface for creating and modifying database tables.

---

## Creating a Table

```php
use Lyger\Database\Schema;
use Lyger\Database\Blueprint;

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->boolean('active')->default(true);
    $table->timestamps();
});
```

---

## Modifying a Table

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('phone')->nullable();
    $table->string('avatar')->nullable();
});
```

---

## Dropping a Table

```php
Schema::drop('users');
Schema::dropIfExists('old_sessions');
```

---

## Column Types

### Identifiers

| Method | SQL Type | Description |
|--------|----------|-------------|
| `id(string $col = 'id')` | `INTEGER PRIMARY KEY AUTOINCREMENT` | Auto-increment primary key |
| `bigId(string $col = 'id')` | `INTEGER PRIMARY KEY AUTOINCREMENT` | Same as id() |
| `uuid(string $col = 'uuid')` | `VARCHAR(36)` | UUID column |

### Strings

| Method | SQL Type | Description |
|--------|----------|-------------|
| `string(string $col, int $len = 255)` | `VARCHAR(N)` | Variable-length string |
| `text(string $col)` | `TEXT` | Long text |

### Numerics

| Method | SQL Type | Description |
|--------|----------|-------------|
| `integer(string $col, bool $unsigned = false)` | `INTEGER` | Integer |
| `bigInteger(string $col, bool $unsigned = false)` | `INTEGER` | Big integer |
| `decimal(string $col, int $precision = 8, int $scale = 2)` | `REAL` | Decimal number |
| `float(string $col)` | `REAL` | Floating point |

### Boolean

| Method | SQL Type | Description |
|--------|----------|-------------|
| `boolean(string $col)` | `INTEGER` | Stored as 0 or 1 |

### Date & Time

| Method | SQL Type | Description |
|--------|----------|-------------|
| `date(string $col)` | `TEXT` | Date string |
| `datetime(string $col)` | `TEXT` | Datetime string |
| `timestamp(string $col)` | `TEXT` | Timestamp string |
| `timestamps()` | Two `TEXT` columns | Adds `created_at` and `updated_at` |
| `softDeletes()` | `TEXT` | Adds `deleted_at` (nullable) |

### JSON & Special

| Method | SQL Type | Description |
|--------|----------|-------------|
| `json(string $col)` | `TEXT` | JSON stored as string |

---

## Column Modifiers

Modifiers are chained on the last column definition:

```php
$table->string('bio')->nullable();
$table->integer('score')->default(0);
$table->string('email')->unique();
$table->integer('age')->unsigned();
```

| Modifier | Description |
|----------|-------------|
| `nullable()` | Allow NULL values |
| `default(mixed $value)` | Set default value |
| `unsigned()` | Mark column as unsigned |
| `primary()` | Add as primary key |
| `unique()` | Add UNIQUE constraint |
| `index(array $cols = [])` | Add index (on column or multiple cols) |

---

## Full Table Example

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->integer('user_id')->unsigned();
    $table->string('title', 512);
    $table->string('slug')->unique();
    $table->text('content')->nullable();
    $table->string('excerpt')->nullable();
    $table->json('meta')->nullable();
    $table->boolean('published')->default(false);
    $table->integer('view_count')->default(0)->unsigned();
    $table->datetime('published_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

---

## Migrations

Migrations provide version control for your database schema.

### Creating a Migration

```bash
php rawr make:migration create_posts_table
php rawr make:migration add_avatar_to_users
```

This generates a timestamped file in `database/migrations/`:

```php
<?php

use Lyger\Database\Migration;
use Lyger\Database\Schema;
use Lyger\Database\Blueprint;

class CreatePostsTable extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content')->nullable();
            $table->boolean('published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
}
```

### Running Migrations

```bash
php rawr migrate
```

Lyger creates a `migrations` table to track which migrations have run. Only pending (not-yet-run) migrations execute.

### Rolling Back

```bash
php rawr migrate:rollback    # Revert the last batch
```

### Migration Status

```bash
php rawr migrate:status      # Show pending and ran migrations
```

---

## Migration Internals

The `Migrator` class:

1. Creates a `migrations` table if it doesn't exist (columns: `id`, `migration`, `batch`)
2. Scans `database/migrations/` for `.php` files
3. Compares against `migrations` table to find pending files
4. Executes `up()` on each pending migration
5. Records them in `migrations` table with current batch number

Rolling back:
1. Finds highest batch number in `migrations` table
2. Calls `down()` on all migrations in that batch
3. Removes their records from `migrations` table

---

## Using the Schema from a Migration

The `Migration` base class provides helpers:

```php
class AddAvatarToUsers extends Migration
{
    public function up(): void
    {
        $schema = $this->getSchema();   // Schema instance
        $pdo    = $this->getConnection(); // PDO connection

        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable();
        });
    }

    public function down(): void
    {
        // Reverse the operation if needed
    }
}
```

---

## Migration Best Practices

- Always implement `down()` to make rollbacks possible
- Use `dropIfExists` in `down()` for safety
- Keep each migration focused on a single change
- Name migrations descriptively: `create_orders_table`, `add_stripe_id_to_customers`
