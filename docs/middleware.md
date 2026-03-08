---
layout: default
title: Middleware
nav_order: 15
---

# Middleware

---

## Overview

Middleware provides a convenient mechanism for inspecting and filtering HTTP requests entering your application. They form a **chain of responsibility** — each middleware can process the request, modify the response, or stop the chain entirely.

---

## Defining Middleware

Implement `MiddlewareInterface` or extend the abstract `Middleware` class:

```php
<?php

namespace App\Middleware;

use Lyger\Middleware\Middleware;
use Lyger\Http\Request;
use Lyger\Http\Response;

class AuthMiddleware extends Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $token = $request->header('Authorization');

        if (!$token || !$this->isValidToken($token)) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        // Call the next middleware or the route handler
        return $next($request);
    }

    private function isValidToken(string $token): bool
    {
        // Validate JWT or session token
        return str_starts_with($token, 'Bearer ');
    }
}
```

---

## Middleware Interface

Any class implementing `MiddlewareInterface` is a valid middleware:

```php
use Lyger\Middleware\MiddlewareInterface;
use Lyger\Http\Request;
use Lyger\Http\Response;

class LogMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $elapsed = round((microtime(true) - $start) * 1000, 2);
        error_log("[{$request->method()}] {$request->uri()} — {$elapsed}ms");

        return $response;
    }
}
```

---

## Chaining Middleware

Chain middleware using `setNext()`:

```php
$auth    = new AuthMiddleware();
$log     = new LogMiddleware();
$cors    = new CorsMiddleware();

// Chain: CORS → Log → Auth → Handler
$cors->setNext($log)->setNext($auth);

$response = $cors->process($request, function ($req) use ($router) {
    return $router->dispatch($req);
});
```

---

## Common Middleware Examples

### CORS Middleware

```php
class CorsMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        return $response
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
}
```

### Rate Limiting Middleware

```php
use Lyger\Cache\Cache;

class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxRequests = 60;
    private int $window      = 60;  // seconds

    public function handle(Request $request, callable $next): Response
    {
        $ip  = $request->ip();
        $key = "rate:{$ip}";

        $cache = Cache::getInstance();
        $hits  = $cache->increment($key);

        if ($hits === 1) {
            $cache->put($key, 1, $this->window);  // Set TTL on first hit
        }

        if ($hits > $this->maxRequests) {
            return Response::json([
                'error' => 'Too Many Requests',
            ], 429);
        }

        return $next($request);
    }
}
```

### JSON Content-Type Middleware

```php
class JsonOnlyMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $contentType = $request->header('Content-Type', '');

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'])
            && !str_contains($contentType, 'application/json')) {
            return Response::json(['error' => 'JSON content required'], 415);
        }

        return $next($request);
    }
}
```

### JWT Authentication Middleware

```php
class JwtMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $authHeader = $request->header('Authorization', '');

        if (!str_starts_with($authHeader, 'Bearer ')) {
            return Response::json(['error' => 'Missing token'], 401);
        }

        $token = substr($authHeader, 7);

        try {
            $payload = $this->verifyJwt($token);
            // Attach user data to request if needed
        } catch (\Exception $e) {
            return Response::json(['error' => 'Invalid token'], 401);
        }

        return $next($request);
    }
}
```

---

## Applying Middleware in index.php

Until route-level middleware binding is implemented, apply middleware in `public/index.php`:

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Lyger\Http\Request;
use Lyger\Routing\Router;
use Lyger\Container\Container;
use Lyger\Core\Engine;
use App\Middleware\CorsMiddleware;
use App\Middleware\LogMiddleware;

$container = Container::getInstance();
$engine    = Engine::getInstance();
$router    = new Router($container);

$router->loadRoutesFromFile(__DIR__ . '/../routes/web.php');

$request = Request::capture();

// Apply middleware chain
$cors = new CorsMiddleware();
$log  = new LogMiddleware();
$cors->setNext($log);

$response = $cors->process($request, function ($req) use ($router) {
    return $router->dispatch($req);
});

$response->send();
```

---

## Method Reference

### MiddlewareInterface

| Method | Description |
|--------|-------------|
| `handle(Request $request, callable $next): Response` | Process the request |

### Middleware (abstract)

| Method | Description |
|--------|-------------|
| `setNext(Middleware $middleware): self` | Chain the next middleware (returns $this) |
| `process(Request $request, callable $handler): Response` | Execute the chain |
| `handle(Request $request, callable $next): Response` | Override in subclasses |
