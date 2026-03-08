---
layout: default
title: Home
nav_order: 1
description: "Lyger — A high-performance PHP framework powered by Rust FFI."
permalink: /
---

# Lyger Framework

A high-performance PHP 8.0+ framework powered by Rust FFI. Always-Alive. Zero-Copy. Zero-Bloat.

**[Get Started →](getting-started.html)** &nbsp;&nbsp; [View on GitHub](https://github.com/betoalien/Lyger-PHP-Framework)

---

## What is Lyger?

Lyger is a next-generation PHP framework that fundamentally changes how PHP handles web requests. Instead of the traditional PHP-FPM model — where PHP boots from scratch on every request — Lyger keeps its worker **always alive in memory**, delegating raw HTTP handling to a Rust-powered **Axum** server via a zero-copy FFI bridge.

The result is PHP with the speed of a compiled language.

---

## Core Principles

| Principle | Description |
|-----------|-------------|
| **Always-Alive** | PHP worker stays loaded in memory across all requests — no boot overhead |
| **Zero-Copy FFI** | Data stays in Rust memory. PHP holds opaque pointer IDs, never copies raw bytes |
| **Zero-Bloat** | An interactive installer physically removes unused code after project setup |
| **Familiar DX** | Eloquent-style ORM, Route facades, Eloquent-style validation — Laravel patterns, Rust performance |

---

## Performance At a Glance

| Operation | Lyger | Laravel | Symfony |
|-----------|-------|---------|---------|
| Database CRUD (1000 ops) | **1.09 ms** | 342.61 ms | 340.92 ms |
| JSON Serialization (1000 objects) | **6.62 ms** | 17.24 ms | 17.67 ms |
| Heavy Computation (10M iterations) | **112 ms** | 360 ms | 357 ms |
| Memory per request | ~16 MB | ~32 MB | ~28 MB |

> Full benchmark methodology and results: [Performance](performance.html)

---

## Requirements

- PHP 8.0 or higher
- PHP `ffi` extension enabled (`ffi.enable = 1`)
- Composer
- Rust toolchain (for compiling the FFI library)

---

## Quick Install

```bash
git clone https://github.com/betoalien/Lyger-PHP-Framework.git my-project
cd my-project
composer install
php rawr install   # Interactive zero-bloat setup
php rawr serve     # Start the Always-Alive server
```

> See the full [Getting Started](getting-started.html) guide for step-by-step instructions.

---

## Documentation Overview

| Section | Description |
|---------|-------------|
| [Getting Started](getting-started.html) | Installation, project setup, first routes |
| [Architecture](architecture.html) | Always-Alive + Zero-Copy deep dive |
| [Routing](routing.html) | Route definitions, parameters, groups |
| [Requests](requests.html) | Capturing and reading HTTP input |
| [Responses](responses.html) | Sending JSON, HTML, and custom responses |
| [ORM](orm.html) | Models, relationships, collections |
| [Query Builder](query-builder.html) | Fluent SQL query builder |
| [Schema & Migrations](schema.html) | Table creation and migration system |
| [Validation](validation.html) | Request validation with 20+ rules |
| [Cache](cache.html) | In-memory caching with TTL |
| [Events](events.html) | Event dispatching and broadcasting |
| [Jobs & Queues](jobs.html) | Async job queue with persistence |
| [API Resources](api.html) | JSON API responses and resources |
| [Middleware](middleware.html) | Request/response pipeline |
| [Container](container.html) | Dependency injection |
| [Helpers](helpers.html) | Env, Path, Str, Arr, Config, Platform |
| [Testing](testing.html) | Test case classes and assertions |
| [CLI Reference](cli.html) | All `rawr` commands |
| [Rust FFI](rust-ffi.html) | The Rust backend and FFI API |
| [Performance](performance.html) | Benchmarks and analysis |
