---
layout: default
title: Getting Started
nav_order: 2
---

# Getting Started

---

## Requirements

Before installing Lyger, make sure your system meets the following requirements:

- **PHP 8.0+** with the `ffi` extension enabled
- **Composer** (PHP dependency manager)
- **Rust toolchain** (`rustup`, `cargo`) — only needed if you want to recompile the FFI library
- **SQLite** (bundled with PHP, recommended for development)
- Optional: PostgreSQL or MySQL for production

---

## Installation

### Option A — Composer (recommended)

The fastest way to start a new Lyger project:

```bash
composer create-project betoalien/lyger my-app
cd my-app
```

Composer will download the framework and all dependencies in one step. Skip to [Configure PHP FFI](#3-configure-php-ffi).

### Option B — Git Clone

```bash
git clone https://github.com/betoalien/Lyger-PHP-Framework.git my-app
cd my-app
composer install
```

### 3. Configure PHP FFI

Lyger ships with a pre-configured `php.ini`. If you're using your system PHP, make sure FFI is enabled:

```ini
; php.ini
extension=ffi
ffi.enable = 1
```

You can verify FFI is available:

```bash
php -r "echo extension_loaded('ffi') ? 'FFI OK' : 'FFI missing';"
```

### 4. Run the Interactive Installer

```bash
php rawr install
```

The installer will walk you through 4 steps:

| Step | Options |
|------|---------|
| **Architecture** | API Headless or Full-Stack |
| **Frontend** | Vue.js, React, Svelte *(Full-Stack only)* |
| **Database** | PostgreSQL, MySQL, SQLite |
| **Authentication** | Lyger Session, JWT, None |

After completing the installer:
- Unused framework stubs are **physically deleted** (Zero-Bloat)
- `.env` is automatically configured for your selected database
- A `.lyger_installed` marker file is created

> See [Zero-Bloat Architecture](architecture.html#zero-bloat-installer) for details on what gets removed.

---

## Starting the Server

### Always-Alive Mode (Recommended)

Starts the Rust-powered Axum HTTP server alongside the persistent PHP worker:

```bash
php rawr serve
```

Custom port:

```bash
php rawr serve --port=8080
```

The server listens on `http://localhost:8000` by default.

### PHP Built-in Server (Fallback)

If the Rust FFI library is not available, use PHP's built-in server:

```bash
php rawr serve:php
```

This runs `php -S localhost:8000 -t public` behind the scenes.

---

## Project Structure

After installation, your project will have the following structure:

```
my-app/
├── App/
│   ├── Controllers/          # Your application controllers
│   └── Models/               # Your application models
├── Lyger/                    # Framework core (do not edit)
├── database/
│   ├── migrations/           # Database migration files
│   └── database.sqlite       # SQLite database (if selected)
├── public/
│   └── index.php             # Application entry point
├── routes/
│   └── web.php               # Route definitions
├── storage/
│   └── queue/                # Persisted job queue files
├── libraries/                # Compiled Rust FFI libraries
├── .env                      # Environment configuration
├── php.ini                   # PHP configuration (ffi.enable=1)
├── composer.json
└── rawr                      # CLI executable
```

---

## Your First Route

Open `routes/web.php` and add a route:

```php
<?php

use Lyger\Routing\Route;
use Lyger\Http\Response;

Route::get('/', function () {
    return Response::json(['message' => 'Hello from Lyger!']);
});

Route::get('/hello/{name}', function ($name) {
    return Response::json(['message' => "Hello, {$name}!"]);
});
```

Visit `http://localhost:8000/` and you should see:

```json
{ "message": "Hello from Lyger!" }
```

---

## Your First Controller

Generate a controller using the CLI:

```bash
php rawr make:controller UserController
```

This creates `App/Controllers/UserController.php`:

```php
<?php

namespace App\Controllers;

use Lyger\Http\Request;
use Lyger\Http\Response;

class UserController
{
    public function index(Request $request): Response
    {
        return Response::json(['users' => []]);
    }

    public function show(Request $request, int $id): Response
    {
        return Response::json(['user' => ['id' => $id]]);
    }
}
```

Register it in `routes/web.php`:

```php
use App\Controllers\UserController;

Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{id}', [UserController::class, 'show']);
```

---

## Your First Model

Generate a model with an accompanying migration:

```bash
php rawr make:model Post --migration
```

Edit the migration in `database/migrations/`:

```php
public function up(): void
{
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('content')->nullable();
        $table->boolean('published')->default(false);
        $table->timestamps();
    });
}
```

Run migrations:

```bash
php rawr migrate
```

Use your model:

```php
use App\Models\Post;

// Create
$post = Post::create(['title' => 'My First Post', 'content' => 'Hello!']);

// Read
$post = Post::find(1);
$all  = Post::all();

// Update
$post->title = 'Updated Title';
$post->save();

// Delete
$post->delete();
```

---

## Environment Configuration

The `.env` file controls all environment-specific settings:

```dotenv
APP_NAME=Lyger
APP_ENV=development
APP_DEBUG=true
APP_PORT=8000

DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# For PostgreSQL:
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=mydb
# DB_USERNAME=user
# DB_PASSWORD=secret
```

Read environment variables in code:

```php
use Lyger\Foundation\Env;

Env::load(__DIR__ . '/../.env');

$debug = Env::get('APP_DEBUG', false);
$port  = Env::get('APP_PORT', 8000);
```

---

## Next Steps

- Learn about [Routing](routing.html) — parameters, groups, middleware
- Explore the [ORM](orm.html) — relationships, scopes, collections
- Understand the [Architecture](architecture.html) — how Always-Alive and Zero-Copy work
- Read the [CLI Reference](cli.html) for all available `rawr` commands
