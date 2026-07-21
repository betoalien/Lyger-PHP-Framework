---
layout: default
title: Testing
nav_order: 18
---

# Testing

---

## Overview

Lyger ships with a lightweight testing framework — no PHPUnit required. The base classes `TestCase` and `HttpTestCase` provide assertion methods for unit and integration testing.

---

## Writing a Test

Create test files in a `tests/` directory in your project:

```php
<?php

use Lyger\Testing\TestCase;
use App\Models\User;

class UserModelTest extends TestCase
{
    public function testCreateUser(): void
    {
        $user = User::create([
            'name'  => 'Alice',
            'email' => 'alice@test.com',
        ]);

        $this->assertNotNull($user->getKey());
        $this->assertEquals('Alice', $user->name);
        $this->assertEquals('alice@test.com', $user->email);
    }

    public function testFindOrFailThrows(): void
    {
        try {
            User::findOrFail(99999);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertStringContainsString('not found', strtolower($e->getMessage()));
        }
    }
}
```

---

## Unit Assertions (TestCase)

### Truth Assertions

```php
$this->assertTrue($value);
$this->assertFalse($value);
```

### Equality

```php
$this->assertEquals($expected, $actual);    // == comparison
$this->assertSame($expected, $actual);      // === comparison (type-strict)
```

### Null Checks

```php
$this->assertNull($value);
$this->assertNotNull($value);
```

### Array Assertions

```php
$this->assertContains('expected', $array);
$this->assertArrayHasKey('key', $array);
$this->assertCount(5, $array);
```

### String Assertions

```php
$this->assertStringContainsString('substring', $string);
$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date);
```

### Type Assertions

```php
$this->assertInstanceOf(User::class, $user);
```

### JSON Assertions

```php
$jsonString = '{"name":"Alice"}';
$this->assertJson($jsonString);
$this->assertJsonStringEqualsJsonString('{"a":1}', '{"a":1}');
```

### Fail Explicitly

```php
$this->fail('This test should not reach this point');
```

---

## HTTP Integration Testing (HttpTestCase)

`HttpTestCase` extends `TestCase` and adds fluent HTTP request simulation:

```php
<?php

use Lyger\Testing\HttpTestCase;

class UserApiTest extends HttpTestCase
{
    public function testGetUsers(): void
    {
        $this->get('/api/users')
             ->assertOk()
             ->seeJsonResponse()
             ->assertJsonValid();
    }

    public function testCreateUser(): void
    {
        $this->post('/api/users', [
                 'name'  => 'Bob',
                 'email' => 'bob@test.com',
             ])
             ->assertCreated()
             ->seeJsonResponse();
    }

    public function testGetUserNotFound(): void
    {
        $this->get('/api/users/99999')
             ->assertNotFound();
    }

    public function testUnauthorizedAccess(): void
    {
        $this->get('/api/admin/dashboard')
             ->assertUnauthorized();
    }
}
```

---

## HTTP Methods

```php
$this->get('/path');
$this->get('/path', ['Authorization' => 'Bearer token123']);  // With headers

$this->post('/path', ['key' => 'value']);
$this->post('/path', $data, ['Content-Type' => 'application/json']);

$this->put('/path/{id}', ['field' => 'value']);
$this->delete('/path/{id}');
```

All methods return `$this` for fluent chaining of assertions.

---

## Status Code Assertions

```php
$this->assertStatus(200);        // Custom status code
$this->assertOk();               // 200
$this->assertCreated();          // 201
$this->assertBadRequest();       // 400
$this->assertUnauthorized();     // 401
$this->assertForbidden();        // 403
$this->assertNotFound();         // 404
$this->assertUnprocessable();    // 422
$this->assertServerError();      // 500
```

---

## Response Assertions

```php
// Assert the response is JSON
$this->seeJsonResponse();

// Assert JSON is valid (parseable)
$this->assertJsonValid();

// Assert JSON has a specific structure (top-level keys)
$this->assertJsonStructure(['data', 'meta', 'links']);

// Assert a specific header
$this->assertHeader('Content-Type', 'application/json');

// Get raw response body
$body = $this->getContent();
$data = json_decode($body, true);
```

---

## Running Tests

Since Lyger's test classes are standalone PHP, run them directly:

```bash
php tests/UserModelTest.php
php tests/UserApiTest.php
```

Or create a simple test runner:

```php
// tests/run.php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

$tests = glob(__DIR__ . '/*Test.php');
foreach ($tests as $test) {
    require_once $test;
}

$classes = array_filter(get_declared_classes(), function ($class) {
    return str_ends_with($class, 'Test');
});

$passed = $failed = 0;
foreach ($classes as $class) {
    $instance = new $class();
    $methods  = get_class_methods($class);

    foreach ($methods as $method) {
        if (!str_starts_with($method, 'test')) continue;

        try {
            $instance->$method();
            echo "✓ {$class}::{$method}\n";
            $passed++;
        } catch (\Exception $e) {
            echo "✗ {$class}::{$method}: " . $e->getMessage() . "\n";
            $failed++;
        }
    }
}

echo "\n{$passed} passed, {$failed} failed.\n";
```

```bash
php tests/run.php
```

---

## Assertion Method Reference

### TestCase

| Method | Description |
|--------|-------------|
| `assertTrue(mixed $val): void` | Assert value is truthy |
| `assertFalse(mixed $val): void` | Assert value is falsy |
| `assertEquals($exp, $act): void` | Assert `$exp == $act` |
| `assertSame($exp, $act): void` | Assert `$exp === $act` |
| `assertNull(mixed $val): void` | Assert value is null |
| `assertNotNull(mixed $val): void` | Assert value is not null |
| `assertContains($needle, array $arr): void` | Assert array contains value |
| `assertArrayHasKey(string $key, array $arr): void` | Assert key exists |
| `assertCount(int $count, array $arr): void` | Assert array has N items |
| `assertStringContainsString(string $needle, string $haystack): void` | Substring check |
| `assertMatchesRegularExpression(string $pattern, string $str): void` | Regex match |
| `assertInstanceOf(string $class, mixed $obj): void` | Type check |
| `assertJson(string $val): void` | Assert valid JSON string |
| `assertJsonStringEqualsJsonString(string $a, string $b): void` | JSON equality |
| `fail(string $message): void` | Force test failure |

### HttpTestCase

| Method | Description |
|--------|-------------|
| `get(string $uri, array $headers = []): self` | Simulate GET request |
| `post(string $uri, array $data = [], array $headers = []): self` | Simulate POST request |
| `put(string $uri, array $data = [], array $headers = []): self` | Simulate PUT request |
| `delete(string $uri, array $headers = []): self` | Simulate DELETE request |
| `assertStatus(int $code): self` | Assert status code |
| `assertOk(): self` | Assert 200 |
| `assertCreated(): self` | Assert 201 |
| `assertBadRequest(): self` | Assert 400 |
| `assertUnauthorized(): self` | Assert 401 |
| `assertForbidden(): self` | Assert 403 |
| `assertNotFound(): self` | Assert 404 |
| `assertUnprocessable(): self` | Assert 422 |
| `assertServerError(): self` | Assert 500 |
| `seeJsonResponse(): self` | Assert response is JSON |
| `assertJsonValid(): self` | Assert JSON is parseable |
| `assertJsonStructure(array $keys): self` | Assert JSON keys exist |
| `assertHeader(string $key, string $value): self` | Assert header value |
| `getContent(): string` | Return raw response body |
