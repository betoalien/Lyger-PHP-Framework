<div align="center">

# ⚡ Lyger Framework

**The PHP framework that never sleeps.**

A high-performance PHP 8.0+ framework powered by a Rust FFI backend.
Always-Alive workers. Zero-Copy data. Zero-Bloat installation.

[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Rust](https://img.shields.io/badge/Powered%20by-Rust-CE422B?style=flat-square&logo=rust&logoColor=white)](https://www.rust-lang.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-22c55e?style=flat-square)](LICENSE)
[![Version](https://img.shields.io/badge/Version-0.1-3b82f6?style=flat-square)]()

<br/>

[📖 Documentation](https://betoalien.github.io/Lyger-PHP-Framework/) &nbsp;·&nbsp;
[🚀 Quick Start](#quick-start) &nbsp;·&nbsp;
[📊 Benchmarks](#performance) &nbsp;·&nbsp;
[🎯 Demo](https://github.com/betoalien/Lyger-PHP-v0.1-Dental-Clinic-Demo)

</div>

---

## What makes Lyger different?

Traditional PHP frameworks die and restart on every request. Lyger doesn't.

```
Laravel / Symfony                    Lyger
─────────────────                    ──────────────────────────────────
Request → Boot PHP    (50-200ms)     Request → Rust HTTP Server (Axum)
        → Load 500+ files                    → PHP Worker (already loaded)
        → Register services                  → Execute logic only
        → Handle request                     → Return response
        → Die
        → Repeat forever
```

Lyger keeps PHP **always alive in memory** and routes requests through a native **Rust HTTP server**. Database queries run through a **Zero-Copy FFI bridge** — results stay in Rust memory, PHP never touches the raw bytes.

---

## Performance

> Real benchmarks. Same hardware. Same workload.

| Operation | Lyger | Laravel | Symfony | Advantage |
|-----------|:-----:|:-------:|:-------:|:---------:|
| Database CRUD (1 000 ops) | **1.09 ms** | 342.61 ms | 340.92 ms | **313×** |
| JSON Serialization (1 000 obj) | **6.62 ms** | 17.24 ms | 17.67 ms | **3×** |
| Heavy Computation (10M iter) | **112 ms** | 360 ms | 357 ms | **3.2×** |
| Throughput (Hello World) | **139M req/s** | 123M req/s | 110M req/s | **+13%** |

The 313× database advantage comes from bypassing PDO entirely — Rust's `tokio-postgres` and `mysql_async` handle queries asynchronously, and results never leave Rust's heap until you need the final JSON.

---

## Quick Start

### Requirements

- PHP 8.0+ with `ffi` extension
- Composer

### Install

```bash
git clone https://github.com/betoalien/Lyger-PHP-Framework.git my-app
cd my-app
composer install
```

Enable FFI in `php.ini`:

```ini
ffi.enable = 1
```

### Setup (Zero-Bloat installer)

```bash
php rawr install
```

The interactive installer removes every module you don't need — leaving only the code your project actually uses.

```
? Architecture   →  API Headless  |  Full-Stack
? Frontend       →  Vue.js  |  React  |  Svelte
? Database       →  SQLite  |  PostgreSQL  |  MySQL
? Auth           →  Session  |  JWT  |  None
```

### Start the server

```bash
php rawr serve          # Always-Alive mode (Rust HTTP server)
php rawr serve:php      # PHP built-in server (fallback)
```

Visit `http://localhost:8000`

---

## Your first route

```php
// routes/web.php
use Lyger\Routing\Route;
use Lyger\Http\Response;

Route::get('/api/users', function () {
    $users = User::all();
    return Response::json($users->toArray());
});

Route::post('/api/users/{id}', [UserController::class, 'update']);
```

---

## Core features

| Feature | Description |
|---------|-------------|
| **Always-Alive Worker** | PHP stays loaded in memory — zero restart overhead per request |
| **Rust FFI Bridge** | Native Rust library for HTTP, DB, cache, and computation |
| **Zero-Copy Database** | Query results live in Rust memory; PHP holds an opaque pointer |
| **Eloquent-style ORM** | `find()`, `all()`, `create()`, relationships, timestamps, soft deletes |
| **Reflection-based DI** | Constructor dependencies resolved automatically — no manual wiring |
| **Fluent Query Builder** | `where()`, `join()`, `paginate()`, `orderBy()` — all chainable |
| **Validation** | 20+ built-in rules, custom messages, Form Request classes |
| **In-memory Cache** | TTL, `remember()`, locks — Redis-like, zero dependencies |
| **Event System** | Dispatch, wildcard listeners, broadcast channels |
| **Job Queue** | Persistent async jobs, retries, `Dispatchable` trait |
| **API Resources** | `ApiResponse`, `JsonResource`, `ApiController` base class |
| **Schema & Migrations** | Fluent `Blueprint`, `migrate` / `rollback` / `status` |
| **Testing Framework** | `TestCase` + `HttpTestCase` — no PHPUnit required |
| **Zero-Bloat Install** | Unused code physically deleted after interactive setup |

---

## CLI reference

```bash
php rawr serve                       # Start Always-Alive Rust server
php rawr serve --port=8080           # Custom port
php rawr serve:php                   # PHP built-in server fallback

php rawr make:controller Name        # Generate controller
php rawr make:model Name             # Generate model
php rawr make:model Name --migration # Generate model + migration
php rawr make:migration Name         # Generate migration file
php rawr make:auth                   # Auth scaffolding
php rawr make:dash                   # Admin dashboard

php rawr migrate                     # Run pending migrations
php rawr migrate:rollback            # Rollback last batch
php rawr migrate:status              # Show migration status
```

---

## Documentation

| | |
|---|---|
| 📖 **GitHub Pages** | [betoalien.github.io/Lyger-PHP-Framework](https://betoalien.github.io/Lyger-PHP-Framework/) |
| 🌐 **Mintlify Docs** | [betoalien-lyger-php-framework.mintlify.app/introduction](https://betoalien-lyger-php-framework.mintlify.app/introduction) |

---

## Live demo

```bash
git clone https://github.com/betoalien/Lyger-PHP-v0.1-Dental-Clinic-Demo.git
cd Lyger-PHP-v0.1-Dental-Clinic-Demo
composer install
php rawr serve
```

---

## Architecture overview

```
                    ┌──────────────────────────────┐
  HTTP Requests ──▶ │   Rust Axum HTTP Server      │  Native I/O
                    └──────────────┬───────────────┘
                                   │ FFI callback
                    ┌──────────────▼───────────────┐
                    │   PHP Worker (Always-Alive)   │  Zero restart
                    │   Router · DI · ORM · Cache   │
                    └──────────────┬───────────────┘
                                   │ FFI call
                    ┌──────────────▼───────────────┐
                    │   Rust Tokio Runtime          │  Async I/O
                    │   tokio-postgres · mysql_async│
                    │   Result: opaque u64 pointer  │  Zero-Copy
                    └──────────────────────────────┘
```

---

## License

MIT — see [LICENSE](LICENSE)
