---
layout: default
title: Architecture
nav_order: 3
---

# Architecture

---

## Overview

Lyger's architecture inverts the traditional PHP-FPM model. Instead of spawning a fresh PHP process for each request, Lyger keeps a **persistent PHP worker in memory** while a **Rust HTTP server** (powered by Axum) handles raw I/O at native speed.

The two components communicate over a **Zero-Copy FFI bridge** — PHP never copies data from Rust; it holds lightweight opaque pointer IDs that reference data living entirely in Rust memory.

```
                        ┌─────────────────────────────────────────┐
                        │            Lyger Runtime                │
                        │                                         │
   HTTP Request         │   ┌──────────────┐    ┌─────────────┐  │
──────────────────────▶ │   │  Axum (Rust) │───▶│ PHP Worker  │  │
                        │   │  HTTP Server │    │ (always in  │  │
   HTTP Response        │   │  port :8000  │◀───│  memory)    │  │
◀────────────────────── │   └──────────────┘    └──────┬──────┘  │
                        │                              │          │
                        │                         ┌────▼──────┐  │
                        │                         │  Rust FFI │  │
                        │                         │  Library  │  │
                        │                         │ (.dylib/  │  │
                        │                         │  .so/.dll)│  │
                        │                         └───────────┘  │
                        └─────────────────────────────────────────┘
```

---

## Always-Alive Worker

### The Problem with Traditional PHP

Every PHP-FPM request triggers:
1. Process fork/spawn
2. PHP runtime bootstrap
3. Framework class loading (~500+ files for Laravel/Symfony)
4. Autoloader execution
5. Service provider registration
6. Request handling
7. Process death

This repeats **millions of times per day** in production, burning CPU cycles and memory on setup work that never changes.

### The Lyger Solution

Lyger's `ServerManager` preloads the entire framework once:

```php
// Preloaded at startup — never reloaded per request:
$preloaded = [
    Engine::class, Router::class, Request::class, Response::class,
    Container::class, QueryBuilder::class, Schema::class, Model::class,
    Collection::class, Middleware::class, Validator::class,
    Cache::class, Env::class, Path::class,
];
```

After startup:
- **PHP Worker** stays running in an infinite loop
- **Rust Axum server** accepts TCP connections on `:8000`
- Each incoming request is **forwarded to the PHP worker** via an FFI callback
- PHP processes only the application logic, not framework bootstrap

### Server Lifecycle

```
php rawr serve
    │
    ▼
Engine::startServer(callable $routerHandler, int $port = 8000)
    │
    ├── ServerManager::preloadFramework()   # Load 14 core classes into memory
    │
    └── lyger_start_server(port: u16)       # FFI call → Rust
            │
            ├── Spawn Tokio runtime in thread
            ├── Create Axum router:
            │   GET /        → root_handler
            │   GET /health  → health_handler
            │   *            → forward_to_php(callback)
            │
            └── TcpListener::bind("0.0.0.0:8000")
```

---

## Zero-Copy FFI Bridge

### The Problem with Standard PHP Extensions

Traditional PHP extensions (like PDO) copy data back and forth between C memory and PHP's heap. For large result sets, this serialization overhead dominates execution time.

### How Zero-Copy Works in Lyger

Lyger uses **opaque pointer IDs** — PHP never holds or copies the actual data:

```
PHP                                 Rust
─────                               ────
                                    ┌─────────────────┐
$id = $engine->dbQuery($dsn,$sql)   │  RESULT_STORE   │
    │                               │  HashMap<u64,   │
    │◀─── returns u64 ID ────────── │  ResultStore>   │
    │                               └─────────────────┘
    │                                        │
$json = $engine->jsonifyResult($id)          │
    │                               ┌────────▼────────┐
    │◀─── returns JSON string ───── │  serde_json     │
    │                               │  serialization  │
    │                               └─────────────────┘
$engine->freeResult($id)
    │
    └─── lyger_free_result(id) ───▶ HashMap::remove(id)
```

**Key insight**: The `u64` ID (8 bytes) crosses the FFI boundary, never the result rows (potentially megabytes). JSON serialization happens once, in Rust, using hardware-optimized `serde_json`.

### FFI Library Loading

`Engine.php` automatically detects your platform and loads the correct compiled library:

```php
// Platform detection logic
private function findLibrary(): ?string
{
    $arch = $this->detectArchitecture();  // 'arm64' or 'x86_64'
    $os   = PHP_OS_FAMILY;               // 'Darwin', 'Linux', 'Windows'

    $candidates = [
        "libraries/lyger_{$os}_{$arch}.dylib",   // macOS
        "libraries/lyger_{$os}_{$arch}.so",       // Linux
        "libraries/lyger_{$os}_{$arch}.dll",      // Windows
        "libraries/lyger.dylib",                  // Fallback
        // ... more candidates
    ];

    foreach ($candidates as $path) {
        if (file_exists($basePath . '/' . $path)) return $path;
    }
    return null;  // FFI unavailable → PHP fallback mode
}
```

If no library is found, Lyger gracefully falls back to pure-PHP implementations.

---

## Request Lifecycle

A complete request flow through Lyger:

```
1. TCP connection arrives at Rust Axum server (port 8000)
2. Axum matches route → forward_to_php(callback)
3. PHP worker receives URI, method, body via FFI callback
4. public/index.php bootstraps (already loaded, instant):
   a. Container::getInstance()    # Singleton, 0 overhead
   b. Engine::getInstance()       # Singleton, 0 overhead
   c. Router dispatches Request   # Match URI pattern
   d. Container resolves controller via Reflection
   e. Controller method executes
   f. Response returned
5. Response serialized (JSON via serde_json if FFI active)
6. Axum sends HTTP response to client
```

---

## Dependency Injection Container

Lyger uses **Reflection-based automatic DI**. You don't need to manually wire dependencies.

```php
// Container.php auto-resolves constructor parameters:
public function resolve(string $abstract): object
{
    $reflection = new ReflectionClass($abstract);
    $constructor = $reflection->getConstructor();

    $dependencies = [];
    foreach ($constructor->getParameters() as $param) {
        $type = $param->getType()?->getName();
        if ($type && class_exists($type)) {
            $dependencies[] = $this->make($type);  // Recursive resolution
        } elseif ($param->isDefaultValueAvailable()) {
            $dependencies[] = $param->getDefaultValue();
        }
    }

    return $reflection->newInstanceArgs($dependencies);
}
```

This means any class with type-hinted constructor parameters is automatically resolved — no manual container bindings required.

---

## Zero-Bloat Installer

The `php rawr install` command runs an interactive wizard that **permanently removes unused framework code** from your installation.

### What Gets Removed

Based on your selections, entire subsystems are unlinked:

| Selection | Removed Code |
|-----------|-------------|
| API Headless | `Lyger/Livewire/`, `Lyger/Components/`, frontend assets |
| Vue.js chosen | React and Svelte stubs |
| SQLite chosen | MySQL, PostgreSQL, MongoDB drivers |
| No Auth | `App/Auth/`, JWT middleware, session auth |

### Why Physical Deletion

The "Zero-Bloat" approach means the framework footprint on disk matches exactly what your application uses. No dead code, no unused dependencies, no inflated vendor directories.

After installation, a `.lyger_installed` marker file records your choices:

```
installed=true
architecture=api
database=sqlite
auth=jwt
```

---

## Rust Core Components

### Tokio Async Runtime

All Rust I/O is handled in a single persistent `tokio::runtime::Runtime`:

```rust
static RUNTIME: Lazy<Runtime> = Lazy::new(|| {
    tokio::runtime::Builder::new_multi_thread()
        .enable_all()
        .build()
        .unwrap()
});
```

This runtime is initialized once at library load and shared across all FFI calls.

### Result Store (Zero-Copy Memory)

Database results live in a `HashMap` keyed by `u64` IDs:

```rust
struct ResultStore {
    data: Vec<HashMap<String, serde_json::Value>>,
    json_cache: Option<String>,  // Cached JSON string for repeated jsonify calls
}

static RESULT_STORE: Lazy<Mutex<HashMap<u64, ResultStore>>> = Lazy::new(|| {
    Mutex::new(HashMap::new())
});
```

### Cache (Thread-Local)

The in-memory cache uses Rust's `thread_local!` for lock-free access:

```rust
thread_local! {
    static CACHE: RefCell<HashMap<String, String>> = RefCell::new(HashMap::new());
}
```

### Axum HTTP Server

The HTTP server uses **Axum** (built on `hyper` + `tokio`):

```rust
async fn lyger_start_server_async(port: u16) {
    let app = Router::new()
        .route("/", get(root_handler))
        .route("/health", get(health_handler))
        .fallback(forward_to_php);

    let listener = TcpListener::bind(format!("0.0.0.0:{port}")).await.unwrap();
    axum::serve(listener, app).await.unwrap();
}
```

---

## Memory Architecture

| Component | Location | Persistence |
|-----------|----------|-------------|
| PHP Worker classes | PHP heap | Entire server lifetime |
| Route map | PHP heap | Entire server lifetime |
| Result Store | Rust heap | Per-query (freed by `freeResult`) |
| Cache data | Rust thread-local | Entire server lifetime |
| Request data | PHP heap | Per-request (GC'd after response) |

---

## Performance Architecture

Lyger's speed advantages stack:

1. **No per-request bootstrap** → 0 ms framework startup overhead
2. **Native Rust HTTP** → I/O at kernel speed via `tokio` + `hyper`
3. **Zero-Copy results** → No data movement for DB queries
4. **Hardware JSON** → `serde_json` uses SIMD instructions
5. **Async DB drivers** → `tokio-postgres`, `mysql_async` bypass PDO thread blocking
6. **SIMD computation** → Rust compiler vectorizes math-heavy loops

> See [Performance](performance.html) for full benchmark results.
