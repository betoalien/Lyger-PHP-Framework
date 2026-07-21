---
layout: default
title: v0.2 Home
nav_order: 1
description: "Lyger — A high-performance PHP framework powered by Rust FFI."
permalink: /
---

# Lyger Framework v0.2

A PHP 8.3+ framework with a Rust FFI backend, an explicit PHP fallback, and reproducible validation notes.

> v0.1 is frozen. v0.2 is the validated release line; its Rust queue transport, database contracts and bounded result streaming have reproducible acceptance tests.

**[Get Started →](getting-started.html)** &nbsp;&nbsp; [View on GitHub](https://github.com/betoalien/Lyger-PHP-Framework)

---

## Why Lyger?

Lyger is designed for teams that want a persistent PHP worker with a native Rust boundary for HTTP and database I/O, while retaining familiar PHP routing, validation and ORM patterns. v0.2 makes that architecture measurable through ABI, database, HTTP, memory and streaming contracts.

## What is Lyger?

Lyger is a next-generation PHP framework that fundamentally changes how PHP handles web requests. Instead of the traditional PHP-FPM model — where PHP boots from scratch on every request — Lyger keeps its worker **always alive in memory**, delegating raw HTTP handling to a Rust-powered **Axum** server via a zero-copy FFI bridge.

The result is PHP with the speed of a compiled language.

---

## Core Principles

| Principle | Description |
|-----------|-------------|
| **Always-Alive** | Rust keeps the HTTP transport alive while PHP handles requests on its main thread |
| **Bounded streaming** | Large result sets are consumed in ordered chunks with an explicit memory budget |
| **Zero-Copy FFI** | Data stays in Rust-owned result handles until the final JSON ABI boundary |
| **Zero-Bloat** | An interactive installer physically removes unused code after project setup |
| **Familiar DX** | Eloquent-style ORM, Route facades, Eloquent-style validation — Laravel patterns, Rust performance |

---

## Performance At a Glance

| Operation | Lyger | Laravel | Symfony |
|-----------|-------|---------|---------|
| SQLite FFI query + JSON (single call) | **1.68 ms** | — | — |
| Heavy computation (10M iterations) | **73 ms** | 368 ms | — |
| JSON serialization | estimated only* | — | — |
| Memory sample | 16 MB used / 18 MB peak | — | — |

\* The current JSON Rust value is calculated by the legacy benchmark and is not a direct Rust timing. Full methodology and results: [Performance](performance.html).

---

## Requirements

- PHP 8.3 or higher
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
