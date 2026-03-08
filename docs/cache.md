---
layout: default
title: Cache
nav_order: 11
---

# Cache

---

## Overview

Lyger's `Cache` is a high-performance, in-memory key-value store with TTL support. It operates as a singleton — data persists **across requests** in the Always-Alive worker, giving you Redis-like performance without an external dependency.

Because PHP stays alive in memory between requests, cached values survive for the lifetime of the server process.

---

## Basic Usage

```php
use Lyger\Cache\Cache;

$cache = Cache::getInstance();

// Store a value for 60 seconds
$cache->put('user:1', ['name' => 'Alice', 'email' => 'alice@test.com'], 60);

// Retrieve
$user = $cache->get('user:1');

// Check existence
if ($cache->has('user:1')) {
    // ...
}

// Remove
$cache->forget('user:1');

// Clear everything
$cache->flush();
```

---

## Setting Values

```php
// With default TTL (3600 seconds)
$cache->put('key', 'value');

// With custom TTL (seconds)
$cache->put('session:abc', $sessionData, 1800);    // 30 minutes
$cache->put('rate:192.168.1.1', 1, 60);            // 1 minute
```

---

## Getting Values

```php
// Returns null if missing or expired
$value = $cache->get('key');

// With a default fallback
$value = $cache->get('key', 'default');
$value = $cache->get('config', []);
```

---

## Remember Pattern

The `remember()` method retrieves a cached value or executes a callback to compute and cache it:

```php
// Cached for 300 seconds
$users = $cache->remember('all_users', function () {
    return User::all()->toArray();
}, 300);

// Cached forever (no TTL)
$config = $cache->rememberForever('app_config', function () {
    return Config::all();
});
```

This pattern eliminates redundant database queries:

```php
Route::get('/api/stats', function () use ($cache) {
    return Response::json(
        $cache->remember('dashboard:stats', function () {
            return [
                'total_users'  => User::query()->count(),
                'total_orders' => Order::query()->count(),
                'revenue'      => Order::query()->value('SUM(amount)'),
            ];
        }, 120)  // Recompute every 2 minutes
    );
});
```

---

## Counters

Atomic increment and decrement for rate limiting or counters:

```php
// Increment
$hits = $cache->increment('page:home:views');
$hits = $cache->increment('page:home:views', 5);    // Increment by 5

// Decrement
$remaining = $cache->decrement('api:rate:user:42');
```

---

## Batch Operations

```php
// Get multiple keys
$values = $cache->getMultiple(['user:1', 'user:2', 'user:3'], null);
// Returns: ['user:1' => [...], 'user:2' => null, 'user:3' => [...]]

// Set multiple keys
$cache->putMultiple([
    'config:app'  => $appConfig,
    'config:mail' => $mailConfig,
], 3600);
```

---

## Distributed Lock

Prevent race conditions in concurrent environments:

```php
$result = $cache->lock('process:payments', function () {
    // Only one request can execute this at a time
    return processPaymentQueue();
}, 10);  // Lock expires after 10 seconds
```

---

## Inspecting Cache

```php
$count = $cache->count();       // Number of non-expired items
$all   = $cache->all();         // All non-expired items as array
```

---

## Default TTL

```php
$cache->setTtl(7200);   // Set default to 2 hours (returns $this for chaining)
```

---

## Method Reference

| Method | Description |
|--------|-------------|
| `getInstance(): Cache` | Get singleton instance |
| `setTtl(int $ttl): self` | Set default TTL in seconds |
| `put(string $key, mixed $value, ?int $ttl = null): void` | Store value |
| `get(string $key, mixed $default = null): mixed` | Retrieve value |
| `has(string $key): bool` | Key exists and not expired |
| `forget(string $key): void` | Delete a key |
| `flush(): void` | Clear all cache |
| `increment(string $key, int $by = 1): int` | Increment numeric value |
| `decrement(string $key, int $by = 1): int` | Decrement numeric value |
| `remember(string $key, callable $cb, ?int $ttl = null): mixed` | Get or compute |
| `rememberForever(string $key, callable $cb): mixed` | Get or compute (no TTL) |
| `getMultiple(array $keys, mixed $default): array` | Batch get |
| `putMultiple(array $values, ?int $ttl): void` | Batch set |
| `lock(string $key, callable $cb, int $seconds = 10): mixed` | Distributed lock |
| `all(): array` | All non-expired items |
| `count(): int` | Count non-expired items |

---

## Cache vs Redis

Because Lyger's cache lives in the Always-Alive PHP worker's memory and also has a mirror cache backed by Rust's `thread_local!` storage (accessible via the FFI cache functions), it covers most Redis use cases without the operational overhead:

| Feature | Lyger Cache | Redis |
|---------|-------------|-------|
| Speed | In-process, nanosecond | Network hop, microsecond |
| Persistence | Per-process lifetime | Configurable |
| Multi-server | Single process | Shared across servers |
| Max memory | PHP worker's RAM | Configurable |
| Dependencies | None | Redis server |

For multi-server deployments, use Lyger's Rust cache functions via `Engine::cacheSet/Get` which can be backed by an external store in future versions.

---

## Rust-Backed FFI Cache

The cache is also available through the FFI Engine for use in zero-copy contexts:

```php
use Lyger\Core\Engine;

$engine = Engine::getInstance();

// Store in Rust thread-local cache
$engine->cacheSet('key', json_encode($data));

// Retrieve
$raw  = $engine->cacheGet('key');
$data = json_decode($raw, true);

// Metadata
$size = $engine->cacheSize();
$engine->cacheDelete('key');
$engine->cacheClear();
```
