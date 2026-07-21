---
layout: default
title: Routing
nav_order: 4
---

# Routing

---

## Basic Routing

Routes are defined in `routes/web.php` using the static `Route` facade. The router supports all standard HTTP verbs.

```php
<?php

use Lyger\Routing\Route;
use Lyger\Http\Request;
use Lyger\Http\Response;

Route::get('/path', $handler);
Route::post('/path', $handler);
Route::put('/path', $handler);
Route::delete('/path', $handler);
```

---

## Route Handlers

### Closure Handler

Pass an anonymous function directly:

```php
Route::get('/', function () {
    return Response::json(['status' => 'ok']);
});
```

The `Request` object is always available as the **first parameter** when you need it:

```php
Route::get('/search', function (Request $request) {
    $query = $request->get('q', '');
    return Response::json(['query' => $query]);
});
```

### Controller Handler

Pass `[ControllerClass::class, 'methodName']`:

```php
use App\Controllers\UserController;

Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::put('/users/{id}', [UserController::class, 'update']);
Route::delete('/users/{id}', [UserController::class, 'destroy']);
```

The container **automatically resolves** the controller and injects its dependencies.

---

## Route Parameters

Use `{paramName}` syntax to capture URI segments:

```php
Route::get('/users/{id}', function (Request $request, $id) {
    return Response::json(['user_id' => $id]);
});

Route::get('/posts/{postId}/comments/{commentId}', function (Request $request, $postId, $commentId) {
    return Response::json([
        'post'    => $postId,
        'comment' => $commentId,
    ]);
});
```

**Parameter injection order**: `Request` is always first, then route parameters in the order they appear in the URI pattern.

In a controller method:

```php
class PostController
{
    public function show(Request $request, int $id): Response
    {
        $post = Post::find($id);
        if (!$post) {
            return Response::error('Post not found', 404);
        }
        return Response::json($post->toArray());
    }
}
```

---

## Response Types

Any value returned from a route handler is automatically coerced to a `Response`:

| Returned Value | Behavior |
|----------------|----------|
| `Response` object | Sent as-is |
| `array` | Converted to JSON response |
| `string` | Sent as plain text |
| `null` | Empty 200 response |

```php
// All of these work:
Route::get('/a', fn() => Response::json(['x' => 1]));
Route::get('/b', fn() => ['x' => 1]);          // Auto JSON
Route::get('/c', fn() => 'Hello World');        // Auto text
```

---

## Controller Class Structure

A typical controller:

```php
<?php

namespace App\Controllers;

use Lyger\Http\Request;
use Lyger\Http\Response;
use App\Models\User;

class UserController
{
    public function index(Request $request): Response
    {
        $users = User::all();
        return Response::json($users->toArray());
    }

    public function show(Request $request, int $id): Response
    {
        $user = User::findOrFail($id);
        return Response::json($user->toArray());
    }

    public function store(Request $request): Response
    {
        $user = User::create($request->all());
        return Response::json($user->toArray(), 201);
    }

    public function update(Request $request, int $id): Response
    {
        $user = User::findOrFail($id);
        $user->fill($request->all());
        $user->save();
        return Response::json($user->toArray());
    }

    public function destroy(Request $request, int $id): Response
    {
        $user = User::findOrFail($id);
        $user->delete();
        return Response::json(['deleted' => true]);
    }
}
```

---

## Route File Loading

The router loads routes from `routes/web.php` via the bootstrapper in `public/index.php`:

```php
$router->loadRoutesFromFile(__DIR__ . '/../routes/web.php');
```

You can split your routes into multiple files by requiring them inside `web.php`:

```php
// routes/web.php
require __DIR__ . '/api.php';
require __DIR__ . '/admin.php';
```

---

## Route Registration Internals

Routes are stored in the static `Route` class and then imported by `Router`:

```php
// Route facade (static collection)
Route::get('/users', [UserController::class, 'index']);

// Router processes them on dispatch:
$router->dispatch($request);
// → matches URI pattern
// → resolves controller from container
// → injects Request + route params
// → returns Response
```

---

## Pattern Matching

The router uses a regex-free custom pattern matcher. The `{param}` segments are extracted and matched positionally against URI segments:

```
Pattern: /users/{id}/posts/{postId}
URI:     /users/42/posts/7

Result: ['id' => '42', 'postId' => '7']
```

Trailing slashes are normalized automatically.

---

## Accessing All Defined Routes

You can inspect all registered routes programmatically:

```php
$routes = Route::getRoutes();
// Returns: [['method' => 'GET', 'path' => '/users', 'handler' => ...], ...]
```

To clear all routes (useful in testing):

```php
Route::clear();
```
