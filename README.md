# Lyger Framework Documentation

## Installation

```bash
# Clone the repository
git clone <repository-url>
cd lyger-framework

# Install PHP dependencies
composer install

# Compile Rust core (required for FFI)
# See lyger_framework_rust- folder
cargo build --release

# Copy the compiled library to libraries folder
cp target/release/liblyger.dylib libraries/

# Start development server
php rawr serve
```

## Configuration

### PHP Requirements
- PHP 8.0 or higher
- FFI extension enabled (`ffi.enable=1` in php.ini)

### Environment
Create a `php.ini` file in the root directory:
```ini
[PHP]
ffi.enable = 1
```

### Database Configuration
Lyger supports multiple databases. Configure in `.env`:

```env
# Supported: sqlite, postgres, mysql, mariadb, mongodb
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# PostgreSQL
# DB_CONNECTION=postgres
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=lyger
# DB_USERNAME=postgres
# DB_PASSWORD=

# MySQL / MariaDB
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=lyger
# DB_USERNAME=root
# DB_PASSWORD=
```

## Architecture

### Directory Structure
```
lyger-framework/
├── Lyger/                  # Framework core
│   ├── Container/          # Dependency Injection
│   ├── Core/              # FFI Engine
│   ├── Database/           # Schema, Migrations, Query Builder
│   ├── Foundation/         # Env Config
│   ├── Http/               # Request, Response
│   ├── Middleware/         # HTTP Middleware
│   ├── Routing/            # Router
│   └── Validation/         # Validator
├── App/                    # Application code
│   ├── Controllers/        # Controllers
│   └── Models/             # Models
├── database/
│   ├── migrations/         # Database migrations
│   └── database.sqlite    # SQLite database
├── libraries/              # Compiled Rust libraries
├── public/                 # Web root
├── routes/                 # Route definitions
└── stubs/                  # Template files
```

## Core Features

### Routing
```php
use Lyger\Routing\Route;

// Basic route
Route::get('/path', function() {
    return ['message' => 'Hello'];
});

// Route with controller
Route::get('/users/{id}', [UserController::class, 'show']);

// POST route
Route::post('/users', [UserController::class, 'store']);
```

### Middleware
```php
use Lyger\Middleware\CorsMiddleware;
use Lyger\Middleware\AuthMiddleware;
use Lyger\Middleware\RateLimitMiddleware;

// Apply to all routes
$router->addMiddleware(new CorsMiddleware());
$router->addMiddleware(new RateLimitMiddleware(60, 60));
$router->addMiddleware(new AuthMiddleware('your-token'));
```

### Validation
```php
use Lyger\Validation\Validator;

$validator = Validator::make($request->all(), [
    'email' => 'required|email',
    'password' => 'required|min:8',
    'name' => 'required|string|max:255',
]);

if ($validator->fails()) {
    return Response::json($validator->errors(), 422);
}

$data = $validator->validated();
```

### Database - Schema Builder
```php
use Lyger\Database\Schema;

Schema::create('users', function ($table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->timestamps();
});
```

### Database - Query Builder
```php
use Lyger\Database\QueryBuilder;

// Select
$users = QueryBuilder::table('users')
    ->where('active', 1)
    ->orderBy('created_at', 'DESC')
    ->get();

// Insert
QueryBuilder::table('users')->insert([
    'name' => 'John',
    'email' => 'john@example.com'
]);

// Pagination
$users = QueryBuilder::table('users')->paginate(15, $page);
```

### Database - Migrations
```bash
# Create migration
php rawr make:migration create_users_table

# Run migrations
php rawr migrate

# Rollback
php rawr migrate:rollback

# Status
php rawr migrate:status
```

## CLI Commands

```bash
# Start server
php rawr serve

# Create controller
php rawr make:controller UserController

# Create model
php rawr make:model User

# Create migration
php rawr make:migration create_users_table

# Run migrations
php rawr migrate
```

## Rust FFI

### Available Functions
```rust
// Returns greeting string
pub extern "C" fn lyger_hello_world() -> *mut c_char

// Heavy computation (CPU-bound)
pub extern "C" fn lyger_heavy_computation(iterations: u64) -> f64

// System information
pub extern "C" fn lyger_system_info() -> *mut c_char

// In-memory cache (persistent across requests)
pub extern "C" fn lyger_cache_set(key: *const c_char, value: *const c_char)
pub extern "C" fn lyger_cache_get(key: *const c_char) -> *mut c_char
pub extern "C" fn lyger_cache_delete(key: *const c_char)
pub extern "C" fn lyger_cache_clear()
pub extern "C" fn lyger_cache_size() -> u64
```

### PHP Usage
```php
use Lyger\Core\Engine;

$engine = Engine::getInstance();
$message = $engine->helloWorld();
$result = $engine->heavyComputation(1000000);
$info = $engine->systemInfo();

// Cache usage
$engine->cacheSet('user_1', '{"name":"John"}');
$value = $engine->cacheGet('user_1');
$size = $engine->cacheSize();
```

## Known Issues & Limitations

### 1. PHP Opcache Caching
**Issue:** Development server may cache old files.
**Solution:** Start PHP with opcache disabled:
```bash
php -d opcache.enable=0 -S localhost:8000 -t public
```

### 2. FFI on macOS
**Issue:** PHP FFI requires special configuration.
**Solution:** Enable FFI via CLI:
```bash
php -d ffi.enable=1 -S localhost:8000 -t public
```

### 3. Static Routes in Development
**Issue:** Static properties persist across requests.
**Solution:** Restart server after changing routes.

## Future Improvements

- Session Middleware
- CSRF Protection
- File Upload Handling
- Full ORM (Eloquent-style)
- Blade-like Templates
- WebSocket Support via Rust
- Multi-database support (PostgreSQL, MySQL, MongoDB)

## Best Practices

1. Always use strict types (`declare(strict_types=1);`)
2. Register dependencies in the Container as singletons for FFI
3. Use migrations for database changes
4. Validate all user input with the Validator
5. Use middleware for cross-cutting concerns (CORS, Auth, Rate Limiting)

## License

MIT License
