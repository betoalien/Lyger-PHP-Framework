---
layout: default
title: Home
nav_order: 1
description: "Lyger — A high-performance PHP framework powered by Rust FFI."
permalink: /
---

# Lyger Framework

A high-performance PHP 8.0+ framework powered by Rust FFI. Always-Alive. Zero-Copy. Zero-Bloat.

**[Get Started →](getting-started)** &nbsp;&nbsp; [View on GitHub](https://github.com/lyger-framework/lyger)

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

> Full benchmark methodology and results: [Performance](performance)

---

## Requirements

- PHP 8.0 or higher
- PHP `ffi` extension enabled (`ffi.enable = 1`)
- Composer
- Rust toolchain (for compiling the FFI library)

---

## Quick Install

```bash
git clone https://github.com/lyger-framework/lyger.git my-project
cd my-project
composer install
php rawr install   # Interactive zero-bloat setup
php rawr serve     # Start the Always-Alive server
```

> See the full [Getting Started](getting-started) guide for step-by-step instructions.

---

## Documentation Overview

| Section | Description |
|---------|-------------|
| [Getting Started](getting-started) | Installation, project setup, first routes |
| [Architecture](architecture) | Always-Alive + Zero-Copy deep dive |
| [Routing](routing) | Route definitions, parameters, groups |
| [Requests](requests) | Capturing and reading HTTP input |
| [Responses](responses) | Sending JSON, HTML, and custom responses |
| [ORM](orm) | Models, relationships, collections |
| [Query Builder](query-builder) | Fluent SQL query builder |
| [Schema & Migrations](schema) | Table creation and migration system |
| [Validation](validation) | Request validation with 20+ rules |
| [Cache](cache) | In-memory caching with TTL |
| [Events](events) | Event dispatching and broadcasting |
| [Jobs & Queues](jobs) | Async job queue with persistence |
| [API Resources](api) | JSON API responses and resources |
| [Middleware](middleware) | Request/response pipeline |
| [Container](container) | Dependency injection |
| [Helpers](helpers) | Env, Path, Str, Arr, Config, Platform |
| [Testing](testing) | Test case classes and assertions |
| [CLI Reference](cli) | All `rawr` commands |
| [Rust FFI](rust-ffi) | The Rust backend and FFI API |
| [Performance](performance) | Benchmarks and analysis |
