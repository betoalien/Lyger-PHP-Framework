---
layout: default
title: Query Builder
nav_order: 8
---

# Query Builder

---

## Overview

The `Lyger\Database\QueryBuilder` provides a fluent, chainable interface for building SQL queries. It works independently of the Model layer and can be used for complex queries that go beyond simple ORM operations.

---

## Creating a Query

```php
use Lyger\Database\QueryBuilder;

// Static factory
$query = QueryBuilder::table('users');

// From a Model (most common)
$query = User::query();
```

---

## Selecting Columns

```php
// All columns (default)
$users = QueryBuilder::table('users')->get();

// Specific columns
$users = QueryBuilder::table('users')
    ->select(['id', 'name', 'email'])
    ->get();
```

---

## WHERE Clauses

### Basic Where

The `where()` method supports a range of comparison operators:

```php
// Simple equality (default operator is '=')
->where('active', true)
->where('status', '=', 'published')

// Comparison operators
->where('age', '>', 18)
->where('price', '<=', 99.99)
->where('count', '!=', 0)
->where('score', '>=', 70)
->where('rating', '<', 5)
```

### Multiple WHERE (AND)

Chaining `where()` adds `AND` conditions:

```php
$users = User::query()
    ->where('active', true)
    ->where('age', '>', 18)
    ->where('role', 'admin')
    ->get();
```

### OR WHERE

```php
$users = User::query()
    ->where('role', 'admin')
    ->orWhere('role', 'moderator')
    ->get();
```

### WHERE IN

```php
$posts = Post::query()
    ->whereIn('status', ['published', 'featured'])
    ->get();
```

### WHERE NULL / NOT NULL

```php
->whereNull('deleted_at')       // Soft-delete guard
->whereNotNull('verified_at')   // Only verified users
```

---

## Ordering

```php
->orderBy('created_at', 'DESC')
->orderBy('name', 'ASC')

// Shortcuts
->latest()              // ORDER BY created_at DESC
->latest('updated_at')  // Custom column
->oldest()              // ORDER BY created_at ASC
```

---

## Limiting and Offsetting

```php
->limit(10)
->offset(20)

// Equivalent to: LIMIT 10 OFFSET 20 (page 3 of 10)
```

---

## Pagination

```php
$result = Post::query()
    ->where('published', true)
    ->orderBy('created_at', 'DESC')
    ->paginate(perPage: 15, page: 1);

// Returns:
// [
//   'data'         => [...],   // Array of rows
//   'current_page' => 1,
//   'total'        => 120,
//   'last_page'    => 8,
//   'from'         => 1,
//   'to'           => 15,
// ]
```

---

## Joins

### Inner Join

```php
$results = QueryBuilder::table('posts')
    ->select(['posts.id', 'posts.title', 'users.name as author_name'])
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->where('posts.published', true)
    ->get();
```

### Left Join

```php
$results = QueryBuilder::table('users')
    ->select(['users.*', 'profiles.bio'])
    ->leftJoin('profiles', 'users.id', '=', 'profiles.user_id')
    ->get();
```

---

## Retrieving Results

### Get All Rows

```php
$rows = Post::query()->where('published', true)->get();
// Returns: array of associative arrays
```

### Get First Row

```php
$user = User::query()->where('email', $email)->first();
// Returns: array|null
```

### Get Single Value

```php
$count = User::query()->where('active', true)->value('COUNT(*)');
// Returns: mixed
```

### Count

```php
$total = Post::query()->where('user_id', 5)->count();
// Returns: int
```

### Check Existence

```php
$exists = User::query()->where('email', 'alice@example.com')->exists();
// Returns: bool
```

---

## Inserting Data

```php
$success = QueryBuilder::table('logs')->insert([
    'user_id'    => 1,
    'action'     => 'login',
    'created_at' => date('Y-m-d H:i:s'),
]);
// Returns: bool
```

---

## Updating Data

```php
$affected = QueryBuilder::table('users')
    ->where('id', 1)
    ->update(['name' => 'Bob', 'updated_at' => date('Y-m-d H:i:s')]);
// Returns: int (number of affected rows)
```

---

## Deleting Data

```php
$affected = QueryBuilder::table('sessions')
    ->where('expires_at', '<', date('Y-m-d H:i:s'))
    ->delete();
// Returns: int (number of deleted rows)
```

---

## Database Connection

The QueryBuilder lazily connects to SQLite at `database/database.sqlite`:

```php
// Internal — called automatically on first query:
$pdo = $this->getConnection();
// → new PDO('sqlite:' . path('database/database.sqlite'))
```

For MySQL or PostgreSQL in production, configure the connection DSN in your `.env` and use the Rust Zero-Copy driver via `Engine::dbQuery()`.

---

## Method Reference

| Method | Returns | Description |
|--------|---------|-------------|
| `table(string $table): self` | static | Start a query on a table |
| `select(array $cols): self` | self | Columns to select |
| `where(col, op, val): self` | self | AND WHERE condition |
| `orWhere(col, op, val): self` | self | OR WHERE condition |
| `whereIn(col, array $vals): self` | self | WHERE col IN (...) |
| `whereNull(col): self` | self | WHERE col IS NULL |
| `whereNotNull(col): self` | self | WHERE col IS NOT NULL |
| `orderBy(col, dir): self` | self | ORDER BY |
| `latest(col?): self` | self | ORDER BY col DESC |
| `oldest(col?): self` | self | ORDER BY col ASC |
| `limit(int): self` | self | LIMIT clause |
| `offset(int): self` | self | OFFSET clause |
| `join(table, first, op, second): self` | self | INNER JOIN |
| `leftJoin(table, first, op, second): self` | self | LEFT JOIN |
| `get(): array` | array | Execute SELECT, all rows |
| `first(): ?array` | ?array | Execute SELECT, first row |
| `value(col): mixed` | mixed | Single column value |
| `count(): int` | int | COUNT(*) |
| `exists(): bool` | bool | Any rows exist? |
| `insert(array $data): bool` | bool | INSERT row |
| `update(array $data): int` | int | UPDATE rows, return affected count |
| `delete(): int` | int | DELETE rows, return affected count |
| `paginate(perPage, page): array` | array | Paginated result set |
| `getConnection(): PDO` | PDO | Lazy SQLite connection |

---

## Complete Example

```php
// Complex query: paginated, filtered, joined
$result = QueryBuilder::table('posts')
    ->select(['posts.id', 'posts.title', 'posts.created_at', 'users.name as author'])
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->where('posts.published', true)
    ->whereNotNull('posts.verified_at')
    ->whereIn('posts.category', ['tech', 'science'])
    ->orderBy('posts.created_at', 'DESC')
    ->paginate(perPage: 20, page: $page);

return Response::json($result);
```
