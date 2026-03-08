---
layout: default
title: Rust FFI Integration
nav_order: 20
---

# Rust FFI Integration

---

## Overview

Lyger's performance backbone is a Rust library compiled as a native shared library (`.dylib` / `.so` / `.dll`). PHP communicates with it via PHP's `FFI` extension — a zero-overhead bridge that lets PHP call native C-ABI functions directly in the same process.

---

## Requirements

- PHP 8.0+ with `ffi` extension (`ffi.enable = 1`)
- Pre-compiled Rust library in `libraries/` directory
- OR Rust toolchain to build from source

---

## Building the Rust Library

```bash
cd lyger_framework_rust-/
cargo build --release
```

Copy the compiled output to `v0.1/libraries/`:

```bash
# macOS (ARM64)
cp target/release/liblyger.dylib ../v0.1/libraries/lyger_Darwin_arm64.dylib

# macOS (Intel)
cp target/release/liblyger.dylib ../v0.1/libraries/lyger_Darwin_x86_64.dylib

# Linux
cp target/release/liblyger.so ../v0.1/libraries/lyger_Linux_x86_64.so
```

Cargo.toml is configured for maximum performance:

```toml
[profile.release]
opt-level     = 3     # Maximum optimization
lto           = true  # Link-time optimization
codegen-units = 1     # Single codegen unit for better inlining
strip         = true  # Strip debug symbols
```

---

## PHP Interface (Engine.php)

The `Engine` class is the only PHP interface to the Rust library. It manages library loading, defines the FFI header, and exposes methods for each Rust function.

### Library Loading

```php
// Engine auto-detects platform and loads the correct library:
private function findLibrary(): ?string
{
    $arch = $this->detectArchitecture(); // 'arm64' or 'x86_64'

    $candidates = [
        "libraries/lyger_Darwin_{$arch}.dylib",   // macOS
        "libraries/lyger_Linux_{$arch}.so",        // Linux
        "libraries/lyger_Windows_{$arch}.dll",     // Windows
        "libraries/lyger.dylib",                   // Generic fallback
        "libraries/lyger.so",
    ];

    foreach ($candidates as $candidate) {
        if (file_exists($basePath . '/' . $candidate)) {
            return $basePath . '/' . $candidate;
        }
    }
    return null;  // → PHP fallback mode
}
```

If no library is found, the Engine falls back to pure-PHP implementations with no error — graceful degradation.

---

## FFI Function Reference

### Computation

#### `helloWorld(): string`
Returns a greeting string from Rust.

```php
$msg = Engine::getInstance()->helloWorld();
// "Hello from Rust! 🦀"
```

**Rust signature**: `lyger_hello_world() -> *mut c_char`

---

#### `heavyComputation(int $iterations): float`
Runs a SIMD-optimized math loop in Rust.

```php
$result = Engine::getInstance()->heavyComputation(10_000_000);
// Returns the computed value — useful to prevent compiler optimization
```

**Rust signature**: `lyger_heavy_computation(iterations: u64) -> c_double`

**Use case**: Benchmarking. Any computation that would block PHP for hundreds of milliseconds can be offloaded here.

---

#### `systemInfo(): string`
Returns framework status as a JSON string.

```php
$info = json_decode(Engine::getInstance()->systemInfo(), true);
// [
//   'framework'      => 'Lyger v0.1',
//   'status'         => 'running',
//   'tokio_runtime'  => 'active',
//   'async_enabled'  => true,
//   'total_memory'   => 16777216,
// ]
```

**Rust signature**: `lyger_system_info() -> *mut c_char`

---

### Database (Zero-Copy)

#### `dbQuery(string $dsn, string $query): int`
Execute a database query asynchronously in Rust's Tokio runtime. Returns an opaque pointer ID — the result stays in Rust memory.

```php
$ptr = Engine::getInstance()->dbQuery(
    'postgres://user:pass@localhost/mydb',
    'SELECT id, name, email FROM users WHERE active = true'
);
// Returns: 42 (an opaque u64 ID)
```

**Rust signature**: `lyger_db_query(dsn: *const c_char, query: *const c_char) -> u64`

Supported DSN prefixes:
- `postgres://` — uses `tokio-postgres` (async)
- `mysql://` — uses `mysql_async` (async)
- `sqlite:` or bare path — uses `rusqlite` (sync)

---

#### `jsonifyResult(int $ptr): string`
Convert a result pointer to a JSON string using `serde_json` (hardware-optimized).

```php
$ptr  = Engine::getInstance()->dbQuery($dsn, $sql);
$json = Engine::getInstance()->jsonifyResult($ptr);
$data = json_decode($json, true);
```

**Rust signature**: `lyger_jsonify_result(ptr: u64) -> *mut c_char`

The JSON string is the only data that crosses the FFI boundary — the result rows themselves never leave Rust memory.

---

#### `freeResult(int $ptr): void`
Free the memory associated with a result pointer. **Always call this** after you're done with a result.

```php
Engine::getInstance()->freeResult($ptr);
```

**Rust signature**: `lyger_free_result(ptr: u64)`

---

#### `dbQueryJson(string $dsn, string $query): string`
Convenience wrapper that calls `dbQuery` + `jsonifyResult` + `freeResult` in one call.

```php
$json = Engine::getInstance()->dbQueryJson($dsn, 'SELECT * FROM users');
$data = json_decode($json, true);
```

---

### Cache (Rust Thread-Local)

The cache functions operate on Rust's `thread_local!` storage — effectively lock-free for single-threaded PHP workers.

#### `cacheSet(string $key, string $value): void`

```php
Engine::getInstance()->cacheSet('user:42', json_encode($user));
```

#### `cacheGet(string $key): string`

```php
$raw  = Engine::getInstance()->cacheGet('user:42');
$user = json_decode($raw, true);
```

Returns an empty string if the key doesn't exist.

#### `cacheDelete(string $key): void`

```php
Engine::getInstance()->cacheDelete('user:42');
```

#### `cacheClear(): void`

```php
Engine::getInstance()->cacheClear();
```

#### `cacheSize(): int`

```php
$count = Engine::getInstance()->cacheSize();
```

---

### HTTP Server

#### `startServer(callable $routerHandler, int $port = 8000): void`
Start the Axum HTTP server and the Always-Alive PHP worker loop.

```php
Engine::getInstance()->startServer(function ($request) use ($router) {
    return $router->dispatch($request);
}, 8000);
```

**Rust signature**: `lyger_start_server(port: u16)`

The Axum router handles:
- `GET /` → root handler (Rust)
- `GET /health` → health check (Rust, no PHP)
- Everything else → forwarded to PHP worker via callback

#### `stopServer(): void`

```php
Engine::getInstance()->stopServer();
```

**Rust signature**: `lyger_stop_server()` — sets `SERVER_RUNNING = false`

---

## Rust Internal Architecture

### Tokio Runtime

A single multi-threaded Tokio runtime is initialized once at library load:

```rust
static RUNTIME: Lazy<Runtime> = Lazy::new(|| {
    tokio::runtime::Builder::new_multi_thread()
        .enable_all()
        .build()
        .unwrap()
});
```

All async operations (database queries, HTTP serving) run inside this shared runtime.

### Result Store (Zero-Copy)

Database results are stored in a `HashMap` indexed by auto-incrementing `u64` IDs:

```rust
struct ResultStore {
    data: Vec<HashMap<String, serde_json::Value>>,
    json_cache: Option<String>,
}

static RESULT_STORE: Lazy<Mutex<HashMap<u64, ResultStore>>> = Lazy::new(|| {
    Mutex::new(HashMap::new())
});
```

**Memory lifecycle**:
1. `lyger_db_query` → stores result, returns `u64` ID
2. `lyger_jsonify_result(id)` → reads from store, returns JSON string to PHP
3. `lyger_free_result(id)` → removes from HashMap, memory freed

### Thread-Local Cache

```rust
thread_local! {
    static CACHE: RefCell<HashMap<String, String>> = RefCell::new(HashMap::new());
}
```

No mutex needed — each PHP worker thread has its own isolated cache.

---

## Complete Zero-Copy Example

```php
use Lyger\Core\Engine;

$engine = Engine::getInstance();

// 1. Execute query — result stays in Rust
$ptr = $engine->dbQuery('postgres://user:pass@localhost/app', 'SELECT * FROM products');

// 2. Convert to JSON in Rust (serde_json, hardware-optimized)
$json = $engine->jsonifyResult($ptr);

// 3. Free Rust memory
$engine->freeResult($ptr);

// 4. Use data in PHP
$products = json_decode($json, true);

return Response::json([
    'products' => $products,
    'count'    => count($products),
]);
```

Data movement: Only the final JSON string crosses the FFI boundary. For 1000 rows, this is roughly 50-200 KB of JSON versus potentially thousands of PHP object allocations in the traditional PDO approach.

---

## Rust Cargo Dependencies

```toml
[dependencies]
tokio          = { version = "1", features = ["full"] }
serde          = { version = "1", features = ["derive"] }
serde_json     = "1"
tokio-postgres = "0.7"
mysql_async    = "0.34"
axum           = "0.7"
tower          = "0.4"
libc           = "0.2"
once_cell      = "1"
bb8            = "0.8"      # Connection pooling
```
