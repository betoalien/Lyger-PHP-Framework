---
layout: default
title: Helpers & Utilities
nav_order: 17
---

# Helpers & Utilities

---

## Env — Environment Variables

The `Lyger\Foundation\Env` class loads and manages environment variables from `.env` files.

### Loading

```php
use Lyger\Foundation\Env;

Env::load(__DIR__ . '/../.env');
```

### Reading Values

Type coercion is automatic — `"true"` becomes `true`, `"false"` becomes `false`, `"null"` becomes `null`:

```php
$debug = Env::get('APP_DEBUG', false);   // bool
$port  = Env::get('APP_PORT', 8000);
$name  = Env::get('APP_NAME', 'Lyger');

if (Env::has('REDIS_HOST')) {
    // ...
}
```

### Writing Values

```php
Env::set('FEATURE_FLAG', true);
Env::forget('TEMP_TOKEN');
```

### Method Reference

| Method | Description |
|--------|-------------|
| `load(string $path): void` | Parse and load a `.env` file |
| `get(string $key, mixed $default = null): mixed` | Get value (with type coercion) |
| `has(string $key): bool` | Check if key exists |
| `set(string $key, mixed $value): void` | Set a runtime environment value |
| `forget(string $key): void` | Remove a key |

---

## Path — File System Paths

The `Lyger\Foundation\Path` class provides cross-platform path resolution anchored at the project root.

```php
use Lyger\Foundation\Path;

// Absolute path to project root
$root = Path::getBasePath();

// Build paths
$dbPath  = Path::database('database.sqlite');  // /app/database/database.sqlite
$storage = Path::storage('logs', 'app.log');   // /app/storage/logs/app.log
$config  = Path::config('database.php');       // /app/config/database.php
$public  = Path::public('assets', 'app.js');   // /app/public/assets/app.js
$app     = Path::app('Models', 'User.php');    // /app/App/Models/User.php

// Arbitrary resolution
$custom  = Path::resolve('custom', 'dir', 'file.txt');

// Ensure a directory exists (creates recursively)
Path::ensureDirectory(Path::storage('uploads'));
```

### Method Reference

| Method | Description |
|--------|-------------|
| `getBasePath(): string` | Project root (auto-detected via composer.json) |
| `resolve(string ...$segments): string` | Build absolute path from segments |
| `database(string ...$segments): string` | `{root}/database/...` |
| `storage(string ...$segments): string` | `{root}/storage/...` |
| `config(string ...$segments): string` | `{root}/config/...` |
| `resource(string ...$segments): string` | `{root}/resources/...` |
| `public(string ...$segments): string` | `{root}/public/...` |
| `app(string ...$segments): string` | `{root}/App/...` |
| `ensureDirectory(string $path): bool` | `mkdir -p` equivalent |

---

## Config — Application Configuration

The `Lyger\Foundation\Config` class stores and retrieves configuration values using dot-notation.

```php
use Lyger\Foundation\Config;

// Load a config file (expects a PHP file returning an array)
Config::load('database');  // Loads config/database.php

// Get with dot-notation
$driver = Config::get('database.connection', 'sqlite');
$host   = Config::get('database.host', 'localhost');

// Set at runtime
Config::set('app.debug', true);

// Get everything
$all = Config::all();
```

**Example `config/database.php`**:
```php
<?php
return [
    'connection' => env('DB_CONNECTION', 'sqlite'),
    'host'       => env('DB_HOST', '127.0.0.1'),
    'port'       => env('DB_PORT', 5432),
    'database'   => env('DB_DATABASE', 'lyger'),
];
```

---

## Str — String Utilities

The `Lyger\Foundation\Str` class provides common string transformations:

```php
use Lyger\Foundation\Str;

Str::camel('hello_world');       // 'helloWorld'
Str::studly('hello_world');      // 'HelloWorld'
Str::snake('HelloWorld');        // 'hello_world'
Str::snake('helloWorld', '-');   // 'hello-world'
Str::kebab('HelloWorld');        // 'hello-world'
Str::slug('Hello World! 2026');  // 'hello-world-2026'
Str::ascii('Héllo');             // 'Hllo' (removes non-ASCII)

Str::limit('This is a long string', 10);  // 'This is a ...'
Str::random(32);                           // 'k3mFxY8...' (32 chars)

Str::contains('hello world', 'world');     // true
Str::startsWith('hello world', 'hello');   // true
Str::endsWith('hello world', 'world');     // true
```

### Method Reference

| Method | Description |
|--------|-------------|
| `camel(string $val): string` | Convert to camelCase |
| `studly(string $val): string` | Convert to StudlyCase |
| `snake(string $val, string $del = '_'): string` | Convert to snake_case |
| `kebab(string $val): string` | Convert to kebab-case |
| `slug(string $val): string` | URL-safe slug |
| `ascii(string $val): string` | Remove non-ASCII characters |
| `limit(string $val, int $limit, string $end = '...'): string` | Truncate |
| `random(int $length = 16): string` | Random alphanumeric string |
| `contains(string $haystack, string $needle): bool` | Substring check |
| `startsWith(string $haystack, string $needle): bool` | Prefix check |
| `endsWith(string $haystack, string $needle): bool` | Suffix check |

---

## Arr — Array Utilities

The `Lyger\Foundation\Arr` class provides dot-notation access and common array operations:

```php
use Lyger\Foundation\Arr;

$data = ['user' => ['name' => 'Alice', 'address' => ['city' => 'NYC']]];

// Dot-notation get
Arr::get($data, 'user.address.city');          // 'NYC'
Arr::get($data, 'user.phone', 'N/A');          // 'N/A' (default)

// Dot-notation set
Arr::set($data, 'user.email', 'alice@test.com');

// Check existence
Arr::has($data, 'user.address.city');          // true

// Remove
Arr::forget($data, 'user.address');

// Filter
Arr::only($data['user'], ['name', 'email']);   // ['name' => 'Alice', 'email' => '...']
Arr::except($data['user'], ['password']);      // All except 'password'

// Extract column
$users = [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']];
Arr::pluck($users, 'name');         // ['Alice', 'Bob']
Arr::pluck($users, 'name', 'id');   // [1 => 'Alice', 2 => 'Bob']

// Flatten
Arr::flatten([[1, 2], [3, [4, 5]]], 1);  // [1, 2, 3, [4, 5]]
Arr::flatten([[1, 2], [3, [4, 5]]]);     // [1, 2, 3, 4, 5]
```

### Method Reference

| Method | Description |
|--------|-------------|
| `get(array $arr, string\|int $key, $default = null): mixed` | Dot-notation get |
| `set(array &$arr, string\|int $key, $value): void` | Dot-notation set |
| `forget(array &$arr, string\|int $key): void` | Dot-notation delete |
| `has(array $arr, string\|int $key): bool` | Dot-notation existence check |
| `only(array $arr, array $keys): array` | Keep only specified keys |
| `except(array $arr, array $keys): array` | Remove specified keys |
| `pluck(array $arr, string $val, ?string $key = null): array` | Extract column |
| `flatten(array $arr, int $depth = INF): array` | Flatten nested array |

---

## Platform — OS Detection

The `Lyger\Foundation\Platform` class detects the current operating system:

```php
use Lyger\Foundation\Platform;

Platform::isWindows();  // bool
Platform::isMac();      // bool
Platform::isLinux();    // bool

Platform::getOS();               // 'Darwin', 'Linux', 'WINNT', etc.
Platform::getExtensionSuffix();  // 'dylib', 'so', or 'dll'
Platform::getLibPrefix();        // 'lib' (macOS/Linux) or '' (Windows)
```

Used internally by `Engine::findLibrary()` to locate the correct compiled Rust library.
