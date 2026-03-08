---
layout: default
title: CLI Reference
nav_order: 19
---

# CLI Reference (`rawr`)

---

## Overview

Lyger's command-line interface is the `rawr` executable located in your project root. It is a PHP script invoked as:

```bash
php rawr <command> [arguments] [--flags]
```

---

## Server Commands

### `serve`

Starts the **Always-Alive** Rust-powered server using Axum:

```bash
php rawr serve
php rawr serve --port=8080
```

- Default port: `8000`
- Starts the Rust HTTP server (`lyger_start_server`)
- Keeps the PHP worker alive in memory
- Preloads 14 framework classes at startup

### `serve:php`

Starts the **PHP built-in server** (development fallback):

```bash
php rawr serve:php
php rawr serve:php --port=9000
```

Equivalent to: `php -S localhost:PORT -t public`

Use this when the compiled Rust FFI library is not available or during initial setup.

---

## Code Generation Commands

### `make:controller`

Generate a new controller class:

```bash
php rawr make:controller UserController
php rawr make:controller Api/PostController
```

Creates `App/Controllers/UserController.php` with basic CRUD method stubs.

### `make:model`

Generate a new model class:

```bash
php rawr make:model Post
php rawr make:model Post --migration    # Also creates a migration file
```

Creates `App/Models/Post.php` extending `Lyger\Database\Model`.

### `make:migration`

Generate a blank migration file:

```bash
php rawr make:migration create_posts_table
php rawr make:migration add_avatar_to_users
```

Creates a timestamped file in `database/migrations/` with `up()` and `down()` stubs.

### `make:auth`

Generate authentication scaffolding:

```bash
php rawr make:auth
```

Creates controllers, routes, and views for user registration, login, and logout.

### `make:dash`

Generate an admin dashboard:

```bash
php rawr make:dash
php rawr make:dash Analytics    # Named dashboard
```

Creates an admin panel with a customizable name.

---

## Database Commands

### `migrate`

Run all pending migrations:

```bash
php rawr migrate
```

- Creates the `migrations` tracking table if it doesn't exist
- Executes `up()` on every migration not yet in the `migrations` table
- Records each completed migration with a batch number

### `migrate:rollback`

Revert the last batch of migrations:

```bash
php rawr migrate:rollback
```

Calls `down()` on every migration in the most recent batch.

### `migrate:status`

Display migration status:

```bash
php rawr migrate:status
```

Shows a table with two columns: **Ran** (✓) vs **Pending** (✗) for each migration file.

---

## Setup Commands

### `install`

Run the interactive Zero-Bloat installer:

```bash
php rawr install
```

The installer walks through 4 steps:

**Step 1 — Architecture**
```
? What architecture do you want?
  > API Headless    (REST API, no frontend)
    Full-Stack      (with frontend framework)
```

**Step 2 — Frontend** *(Full-Stack only)*
```
? Choose a frontend framework:
  > Vue.js
    React
    Svelte
```

**Step 3 — Database**
```
? Choose a database engine:
  > SQLite          (default, zero-config)
    PostgreSQL
    MySQL
```

**Step 4 — Authentication**
```
? Choose an authentication method:
  > Lyger Session
    JWT
    None
```

After completing the wizard:
- Unused framework modules are **physically deleted** from disk
- `.env` is configured with your database settings
- `.lyger_installed` is written to prevent re-running the installer

---

## Command Summary Table

| Command | Description |
|---------|-------------|
| `php rawr serve` | Start Always-Alive Rust server (port 8000) |
| `php rawr serve --port=N` | Start server on custom port |
| `php rawr serve:php` | Start PHP built-in server |
| `php rawr make:controller Name` | Generate controller |
| `php rawr make:model Name` | Generate model |
| `php rawr make:model Name --migration` | Generate model + migration |
| `php rawr make:migration Name` | Generate migration file |
| `php rawr make:auth` | Generate auth scaffolding |
| `php rawr make:dash [Name]` | Generate admin dashboard |
| `php rawr migrate` | Run pending migrations |
| `php rawr migrate:rollback` | Rollback last batch |
| `php rawr migrate:status` | Show migration status |
| `php rawr install` | Interactive project setup |

---

## Generated File Locations

| Command | Output Location |
|---------|----------------|
| `make:controller` | `App/Controllers/{Name}.php` |
| `make:model` | `App/Models/{Name}.php` |
| `make:migration` | `database/migrations/{timestamp}_{name}.php` |

---

## Getting Help

```bash
php rawr
php rawr --help
```

Displays available commands and usage information.
