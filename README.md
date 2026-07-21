<div align="center">

# ⚡ Lyger Framework v0.2

**Lyger, the leader in stars.**

v0.1 is frozen for historical compatibility. v0.2 is the validated release line for the Rust FFI bridge.

A high-performance PHP 8.3+ framework powered by a Rust FFI backend.
Always-Alive workers. Zero-Copy data. Zero-Bloat installation.

[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Rust](https://img.shields.io/badge/Powered%20by-Rust-CE422B?style=flat-square&logo=rust&logoColor=white)](https://www.rust-lang.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-22c55e?style=flat-square)](LICENSE)
[![Version](https://img.shields.io/badge/Version-0.2-3b82f6?style=flat-square)]()

<br/>

[📖 Documentation](https://lygerphp.com/) &nbsp;·&nbsp;
[🚀 Quick Start](#quick-start) &nbsp;·&nbsp;
[📊 Benchmarks](#performance) &nbsp;·&nbsp;
[🎯 Demo](https://github.com/betoalien/Lyger-PHP-v0.1-Dental-Clinic-Demo)

</div>

See [`CHANGELOG.md`](CHANGELOG.md) for the ordered release history and [`docs/MIGRATION-v0.1-v0.2.md`](docs/MIGRATION-v0.1-v0.2.md) for upgrade notes.

---

## Why Lyger?

Lyger exists to make a persistent PHP application lifecycle measurable and controllable. Instead of repeatedly booting a conventional framework, Lyger keeps a PHP worker alive while Rust handles native HTTP I/O, database drivers and bounded result storage. This gives applications a clear Rust/PHP boundary without requiring invasive changes to an existing framework.

The goal is not an unqualified performance promise: v0.2 reports reproducible contracts and local measurements, while separating estimates from direct benchmarks. See the [validation record](docs/validation-results.md).

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

> Measurements must be reproducible. Results below are local microbenchmarks, not a universal performance guarantee.

| Operation | Lyger | Laravel | Symfony | Advantage |
|-----------|:-----:|:-------:|:-------:|:---------:|
| SQLite FFI query + JSON (single call) | **1.68 ms** | — | — | measured locally |
| Heavy Computation (10M iter) | **73 ms** | 368 ms | — | **5.1×** |
| JSON Serialization (1 000 objects × 100) | **6.86 ms*** | 20.59 ms | — | **3×*** |
| Hello World in-process loop (1 000 iterations) | **0.01 ms** | — | — | not HTTP throughput |

\* The JSON Rust value is currently a calculated estimate in the legacy benchmark and is not a direct Rust timing. PostgreSQL/MySQL CRUD validation is covered by the Rust-core integration suite; equivalent PHP-driver benchmarks are not yet public claims.

Benchmark methodology and reproduction steps: [docs/validation-results.md](docs/validation-results.md) and [benchmark/run.php](benchmark/run.php).

## Roadmap: v0.3 and PardoX

v0.3 will investigate an official Lyger–PardoX integration for large-scale data analytics exposed through PHP. This is a roadmap commitment, not a v0.2 feature: the future contract must define stable Rust/PHP/PardoX bindings, bounded data exchange and reproducible analytical benchmarks. See [the v0.3 roadmap](Changes/RUST_CORE_PRODUCTION_AND_COMPETITIVE_ROADMAP.md).

## Quick Start

### Requirements

- PHP 8.3+ with `ffi` extension
- Composer

### Install

**Via Composer (recommended):**

```bash
composer create-project betoalien/lyger my-app
cd my-app
```

**Via Git:**

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
| 📖 **Documentation** | [lygerphp.com](https://lygerphp.com/) |
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
