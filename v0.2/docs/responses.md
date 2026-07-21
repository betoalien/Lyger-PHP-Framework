---
layout: default
title: Responses
nav_order: 6
---

# HTTP Responses

---

## Overview

The `Lyger\Http\Response` class is a fluent HTTP response builder. It handles setting status codes, headers, and content — automatically detecting the correct `Content-Type` when you use the static factory methods.

---

## JSON Responses

The most common response type in API development:

```php
use Lyger\Http\Response;

// Simple
return Response::json(['message' => 'Created']);

// With status code
return Response::json(['user' => $user->toArray()], 201);

// Arrays are automatically JSON-encoded
return Response::json([
    'data'  => $users,
    'total' => count($users),
    'page'  => 1,
]);
```

The `Content-Type` header is set to `application/json` automatically.

---

## HTML Responses

```php
return Response::html('<h1>Hello World</h1>');
return Response::html($renderedTemplate, 200);
```

---

## Plain Text Responses

```php
return Response::text('Hello, plain world!');
return Response::text('Service Unavailable', 503);
```

---

## Error Responses

```php
return Response::error('Resource not found', 404);
return Response::error('Internal Server Error', 500);
return Response::error('Unauthorized', 401);
```

---

## Custom Status Codes

All static factory methods accept a status code as their second parameter:

```php
return Response::json(['created' => true], 201);
return Response::json(['deleted' => true], 200);
return Response::json(['errors' => $errors], 422);
return Response::html($page, 404);
```

---

## Adding Custom Headers

The response builder is fluent — you can chain `setHeader()` calls:

```php
return Response::json(['token' => $token])
    ->setHeader('Authorization', "Bearer {$token}")
    ->setHeader('X-Request-Id', uniqid());
```

---

## Reading Response Properties

```php
$response = Response::json(['x' => 1], 201);

$response->getStatusCode();           // 201
$response->getContent();              // '{"x":1}'
$response->getHeader('Content-Type'); // 'application/json'
```

---

## Manual Constructor

You can also instantiate `Response` directly:

```php
// Array → auto JSON
$response = new Response(['key' => 'value'], 200, [
    'X-Custom-Header' => 'my-value',
]);

// String → plain text
$response = new Response('raw content', 200);
```

---

## Sending the Response

In normal framework usage, `send()` is called automatically by the bootstrapper. You typically never call it yourself:

```php
// Called internally by public/index.php:
$response->send();
// → Sends headers (status code + all set headers)
// → Outputs content body
```

---

## Method Reference

| Method | Description |
|--------|-------------|
| `json(mixed $data, int $status = 200): Response` | JSON response |
| `html(string $html, int $status = 200): Response` | HTML response |
| `text(string $text, int $status = 200): Response` | Plain text response |
| `error(string $message, int $status = 500): Response` | Error response |
| `setHeader(string $key, string $value): self` | Add/override header (fluent) |
| `getStatusCode(): int` | Get HTTP status code |
| `getContent(): string` | Get response body string |
| `getHeader(string $key, ?string $default = null): ?string` | Get header value |
| `send(): void` | Write headers and body to output |

---

## Common Status Codes

| Code | Meaning | Usage |
|------|---------|-------|
| 200 | OK | Successful GET/PUT |
| 201 | Created | Successful POST |
| 204 | No Content | Successful DELETE |
| 400 | Bad Request | Invalid input |
| 401 | Unauthorized | Missing/invalid auth |
| 403 | Forbidden | Insufficient permissions |
| 404 | Not Found | Resource doesn't exist |
| 422 | Unprocessable Entity | Validation errors |
| 500 | Internal Server Error | Unexpected exception |

---

## Auto-Detection

When a controller returns a non-Response value, the router automatically wraps it:

```php
// Returning an array → JSON response
Route::get('/users', fn() => User::all()->toArray());

// Returning a string → text/html response
Route::get('/health', fn() => 'OK');

// Returning null → empty 200 response
Route::get('/ping', fn() => null);
```
