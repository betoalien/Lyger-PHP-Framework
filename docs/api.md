---
layout: default
title: API Resources
nav_order: 14
---

# API Resources

---

## Overview

The `Lyger\Api` namespace provides tools to build consistent, well-structured JSON API responses. This includes resource transformers, JSON:API-compatible wrappers, and standardized response helpers.

---

## ApiResponse (Static Helpers)

The quickest way to return consistent API responses:

```php
use Lyger\Api\ApiResponse;

// Success
return Response::json(ApiResponse::success($data, 'Users retrieved'));

// Error
return Response::json(ApiResponse::error('Not found', 404), 404);

// Validation error
return Response::json(ApiResponse::validationError($validator->errors()), 422);
```

### Standard Response Shapes

**Success**:
```json
{
  "success": true,
  "message": "Users retrieved",
  "data": [...]
}
```

**Error**:
```json
{
  "success": false,
  "message": "Not found",
  "errors": null
}
```

**Validation Error**:
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."],
    "name": ["The name must be at least 2 characters."]
  }
}
```

---

## All ApiResponse Methods

```php
// Basic
ApiResponse::success(mixed $data, string $message = 'Success', int $code = 200): array
ApiResponse::error(string $message, int $code = 500, ?array $errors = null): array

// Convenience shortcuts
ApiResponse::notFound(): array                    // 404
ApiResponse::unauthorized(): array                // 401
ApiResponse::forbidden(): array                   // 403
ApiResponse::validationError(array $errors): array // 422

// Resource operations
ApiResponse::created(mixed $data): array          // 201 Created
ApiResponse::deleted(): array                     // 200 + "deleted" message

// Pagination
ApiResponse::paginated(
    array $data,
    int $page,
    int $perPage,
    int $total
): array
```

**Paginated response shape**:
```json
{
  "success": true,
  "data": [...],
  "meta": {
    "current_page": 2,
    "per_page": 15,
    "total": 120,
    "last_page": 8
  }
}
```

---

## Resource Transformers

Use `Resource` to transform model data and decouple your API shape from your database schema:

```php
<?php

namespace App\Resources;

use Lyger\Api\Resource;

class UserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id'         => $this->data['id'],
            'name'       => $this->data['name'],
            'email'      => $this->data['email'],
            'created_at' => $this->data['created_at'],
            // Omit: password, internal IDs, sensitive fields
        ];
    }
}
```

Use in a controller:

```php
$user     = User::find(1);
$resource = new UserResource($user->toArray());

return Response::json(ApiResponse::success($resource->toArray()));
```

### Collection of Resources

```php
$users     = User::all()->toArray();
$resources = UserResource::collection($users);

return Response::json(ApiResponse::success($resources));
```

---

## JSON:API Resource (JsonResource)

For JSON:API specification compliance, use `JsonResource`:

```php
use Lyger\Api\JsonResource;

$resource = new JsonResource($data);

// Add metadata
$resource->meta(['version' => '1.0', 'generated_at' => time()]);

// Add links
$resource->link('self', '/api/users/1');
$resource->link('related', '/api/users/1/posts');

return Response::json($resource->toArray());
```

**Output shape**:
```json
{
  "data": { "id": 1, "name": "Alice" },
  "meta": { "version": "1.0" },
  "links": { "self": "/api/users/1" }
}
```

---

## ApiController Base Class

Extend `ApiController` to get helper methods built in:

```php
<?php

namespace App\Controllers;

use Lyger\Api\ApiController;
use Lyger\Http\Request;
use Lyger\Http\Response;
use App\Models\Post;

class PostApiController extends ApiController
{
    public function index(Request $request): Response
    {
        $posts = Post::query()
            ->where('published', true)
            ->paginate(15, (int) $request->get('page', 1));

        return Response::json(
            $this->paginated($posts['data'], $posts['current_page'], 15, $posts['total'])
        );
    }

    public function show(Request $request, int $id): Response
    {
        $post = Post::find($id);
        if (!$post) {
            return Response::json($this->notFound(), 404);
        }
        return Response::json($this->success($post->toArray()));
    }

    public function store(Request $request): Response
    {
        // ... validate ...
        $post = Post::create($request->all());
        return Response::json($this->created($post->toArray()), 201);
    }

    public function destroy(Request $request, int $id): Response
    {
        $post = Post::findOrFail($id);
        $post->delete();
        return Response::json($this->deleted());
    }
}
```

Available methods via `ApiController`:

| Method | Description |
|--------|-------------|
| `success($data, $message, $code)` | Wrap in success shape |
| `error($message, $code, $errors)` | Wrap in error shape |
| `notFound()` | 404 response array |
| `unauthorized()` | 401 response array |
| `forbidden()` | 403 response array |
| `validationError($errors)` | 422 response array |
| `created($data)` | 201 Created response array |
| `deleted()` | Deleted response array |
| `paginated($data, $page, $perPage, $total)` | Paginated response array |

---

## Recommended API Route Structure

```php
// routes/web.php (API Headless architecture)
use App\Controllers\UserApiController;
use App\Controllers\PostApiController;

// Users
Route::get('/api/v1/users', [UserApiController::class, 'index']);
Route::post('/api/v1/users', [UserApiController::class, 'store']);
Route::get('/api/v1/users/{id}', [UserApiController::class, 'show']);
Route::put('/api/v1/users/{id}', [UserApiController::class, 'update']);
Route::delete('/api/v1/users/{id}', [UserApiController::class, 'destroy']);

// Posts
Route::get('/api/v1/posts', [PostApiController::class, 'index']);
Route::post('/api/v1/posts', [PostApiController::class, 'store']);
Route::get('/api/v1/posts/{id}', [PostApiController::class, 'show']);
Route::put('/api/v1/posts/{id}', [PostApiController::class, 'update']);
Route::delete('/api/v1/posts/{id}', [PostApiController::class, 'destroy']);
```
