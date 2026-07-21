---
layout: default
title: Requests
nav_order: 5
---

# HTTP Requests

---

## Overview

The `Lyger\Http\Request` class captures all incoming HTTP data — query strings, POST bodies, JSON payloads, and headers — into a single object injected into every route handler.

---

## Creating a Request

In normal usage, you never instantiate `Request` manually. The framework calls `Request::capture()` automatically:

```php
// public/index.php — done for you
$request = Request::capture();
$response = $router->dispatch($request);
```

For testing or manual usage:

```php
use Lyger\Http\Request;

$request = Request::capture();
```

---

## Reading Query Parameters

Query string parameters (`?key=value`):

```php
Route::get('/search', function (Request $request) {
    $query = $request->get('q');               // null if missing
    $page  = $request->get('page', 1);         // default 1
    $sort  = $request->get('sort', 'created_at');
});
```

---

## Reading POST Data

Standard form-encoded POST body:

```php
Route::post('/login', function (Request $request) {
    $email    = $request->post('email');
    $password = $request->post('password');
});
```

---

## Reading JSON Body

When the request has `Content-Type: application/json`, the body is automatically parsed:

```php
Route::post('/api/users', function (Request $request) {
    $json = $request->getJson();   // Returns parsed array or null

    $name  = $request->input('name');   // Searches JSON → POST → GET
    $email = $request->input('email');
});
```

The `input()` method searches in order: **JSON body → POST data → query string**.

---

## Getting All Input

Returns a merged array of GET + POST + JSON data:

```php
$all = $request->all();
// ['name' => 'Alice', 'email' => 'alice@example.com', 'page' => '1']
```

---

## Request Method

```php
$method = $request->method();
// 'GET', 'POST', 'PUT', 'DELETE', 'PATCH'
```

---

## Request URI

```php
$uri = $request->uri();
// '/api/users/42'  (query string stripped)
```

---

## Reading Headers

Headers are accessible via their HTTP name (case-insensitive, hyphens converted to underscores):

```php
$contentType = $request->header('Content-Type');
$accept      = $request->header('Accept', 'application/json');
$auth        = $request->header('Authorization');
// → reads $_SERVER['HTTP_AUTHORIZATION']
```

---

## Client IP Address

```php
$ip = $request->ip();
// '127.0.0.1'
```

---

## Method Reference

| Method | Description |
|--------|-------------|
| `capture(): self` | Static factory — creates Request from current HTTP context |
| `get(string $key, $default = null)` | Query string parameter |
| `post(string $key, $default = null)` | POST body parameter |
| `input(string $key, $default = null)` | JSON → POST → GET lookup |
| `all(): array` | All input merged |
| `getJson(): ?array` | Parsed JSON body (null if not JSON request) |
| `method(): string` | HTTP verb in uppercase |
| `uri(): string` | Request path without query string |
| `header(string $key, $default = null)` | HTTP header value |
| `ip(): string` | Client IP address |

---

## Example: Full Controller Usage

```php
<?php

namespace App\Controllers;

use Lyger\Http\Request;
use Lyger\Http\Response;
use Lyger\Validation\Validator;
use App\Models\Product;

class ProductController
{
    public function store(Request $request): Response
    {
        // Validate
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'price'    => 'required|numeric|min:0',
            'category' => 'required|in:electronics,clothing,food',
        ]);

        if ($validator->fails()) {
            return Response::json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Create product
        $product = Product::create($validator->validated());

        return Response::json($product->toArray(), 201);
    }

    public function index(Request $request): Response
    {
        $page    = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 15);
        $sort    = $request->get('sort', 'created_at');

        $products = Product::query()
            ->orderBy($sort, 'DESC')
            ->paginate($perPage, $page);

        return Response::json($products);
    }
}
```
