---
layout: default
title: Dependency Injection
nav_order: 16
---

# Dependency Injection Container

---

## Overview

Lyger's `Container` is a **reflection-based dependency injection (DI) container**. It automatically resolves class dependencies by inspecting constructor type hints — no manual wiring required for most use cases.

---

## Accessing the Container

The container is a singleton:

```php
use Lyger\Container\Container;

$container = Container::getInstance();
```

---

## Automatic Resolution

The container's primary feature is **automatic resolution**. Any class with type-hinted constructor parameters is resolved recursively:

```php
class UserRepository
{
    public function __construct(private QueryBuilder $db) {}
}

class UserService
{
    public function __construct(private UserRepository $repo) {}
}

// The container resolves the entire chain automatically
$service = $container->make(UserService::class);
// UserService → UserRepository → QueryBuilder → all resolved
```

---

## Manual Bindings

### Factory Binding

Register a factory closure that is called every time the abstract is resolved:

```php
$container->bind(LoggerInterface::class, function () {
    return new FileLogger('/var/log/app.log');
});
```

### Singleton Binding

Register a pre-instantiated object. The same instance is returned every time:

```php
$engine = Engine::getInstance();
$container->singleton(Engine::class, $engine);

// The same $engine instance is returned every call
$same = $container->make(Engine::class);
```

---

## Resolving Dependencies

```php
// Resolve a class (auto-injection)
$controller = $container->make(UserController::class);

// Resolve with an abstract binding
$logger = $container->make(LoggerInterface::class);
```

---

## How Automatic Resolution Works

```php
// Container inspects constructor signature:
class OrderService
{
    public function __construct(
        private OrderRepository $orders,
        private PaymentGateway  $payments,
        private string          $currency = 'USD'   // has default
    ) {}
}

$container->make(OrderService::class);
// 1. Reflects on OrderService constructor
// 2. Finds OrderRepository → recursively calls make(OrderRepository::class)
// 3. Finds PaymentGateway   → recursively calls make(PaymentGateway::class)
// 4. Finds string $currency → has default 'USD' → uses it
// 5. Constructs OrderService with all resolved dependencies
```

---

## Using the Container in Controllers

Controllers are automatically resolved by the router. Just type-hint your dependencies:

```php
<?php

namespace App\Controllers;

use Lyger\Http\Request;
use Lyger\Http\Response;
use Lyger\Core\Engine;
use App\Services\UserService;

class UserController
{
    // Engine and UserService are auto-injected by the container
    public function __construct(
        private Engine      $engine,
        private UserService $users
    ) {}

    public function index(Request $request): Response
    {
        return Response::json($this->users->getAll());
    }
}
```

---

## Registering Bindings

Organize your bindings in a bootstrap file or service provider pattern:

```php
// In public/index.php or a dedicated bootstrap file

$container = Container::getInstance();

// Engine singleton
$container->singleton(Engine::class, Engine::getInstance());

// Interface bindings
$container->bind(CacheInterface::class, function () {
    return Cache::getInstance();
});

$container->bind(MailerInterface::class, function () {
    return new SmtpMailer(Env::get('MAIL_HOST'), Env::get('MAIL_PORT'));
});
```

---

## Method Reference

| Method | Description |
|--------|-------------|
| `getInstance(): Container` | Get singleton container instance |
| `bind(string $abstract, callable $factory): void` | Register factory (new instance per call) |
| `singleton(string $abstract, object $instance): void` | Register shared instance |
| `make(string $abstract): object` | Resolve a class (checks bindings first, then auto-resolves) |
| `resolve(string $abstract): object` | Auto-resolve via Reflection (used internally by make) |

---

## Resolution Order

When `make($abstract)` is called:

1. **Check singletons** — if registered, return the shared instance
2. **Check bindings** — if a factory is registered, call and return it
3. **Auto-resolve** — use Reflection to inspect and recursively resolve constructor parameters
4. **Throw exception** — if a non-optional parameter has no resolvable type

---

## Complete Example

```php
// Interfaces
interface CacheInterface
{
    public function get(string $key): mixed;
    public function put(string $key, mixed $value): void;
}

// Implementation
class RustCache implements CacheInterface
{
    public function __construct(private Engine $engine) {}

    public function get(string $key): mixed
    {
        return json_decode($this->engine->cacheGet($key), true);
    }

    public function put(string $key, mixed $value): void
    {
        $this->engine->cacheSet($key, json_encode($value));
    }
}

// Service using the cache
class ProductService
{
    public function __construct(private CacheInterface $cache) {}

    public function getAll(): array
    {
        return $this->cache->get('products') ?? [];
    }
}

// Bootstrap
$container = Container::getInstance();
$container->singleton(Engine::class, Engine::getInstance());
$container->bind(CacheInterface::class, fn() => new RustCache(Engine::getInstance()));

// Resolve — ProductService → CacheInterface → RustCache → Engine
$service = $container->make(ProductService::class);
```
