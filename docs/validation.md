---
layout: default
title: Validation
nav_order: 10
---

# Validation

---

## Overview

Lyger includes a powerful validation system with 20+ built-in rules, custom error messages, and form request classes for organizing validation logic.

---

## Basic Validation

Use `Validator::make()` to create a validator:

```php
use Lyger\Validation\Validator;

$validator = Validator::make($data, $rules);

if ($validator->fails()) {
    return Response::json(['errors' => $validator->errors()], 422);
}

$validated = $validator->validated(); // Only validated fields
```

### Full Example in a Controller

```php
public function store(Request $request): Response
{
    $validator = Validator::make($request->all(), [
        'name'     => 'required|string|max:100',
        'email'    => 'required|email',
        'password' => 'required|min:8|confirmed',
        'age'      => 'required|integer|min:18|max:120',
    ]);

    if ($validator->fails()) {
        return Response::json([
            'message' => 'Validation failed',
            'errors'  => $validator->errors(),
        ], 422);
    }

    $user = User::create($validator->validated());
    return Response::json($user->toArray(), 201);
}
```

---

## Available Rules

Rules are combined as a pipe-delimited string or an array.

### Presence

| Rule | Description |
|------|-------------|
| `required` | Field must be present and not empty/null |

### Type Checking

| Rule | Description |
|------|-------------|
| `string` | Must be a string |
| `integer` | Must be an integer |
| `numeric` | Must be numeric (int or float) |
| `boolean` | Must be a boolean (or `'true'`/`'false'`/`0`/`1`) |
| `array` | Must be an array |

### Format

| Rule | Description |
|------|-------------|
| `email` | Must be a valid email address |
| `url` | Must be a valid URL |
| `ip` | Must be a valid IP address |
| `date` | Must be parseable as a date (`strtotime`) |
| `alpha` | Must contain only letters |
| `alpha_num` | Must contain only letters and numbers |
| `regex:/pattern/` | Must match the given regular expression |

### Size & Length

| Rule | Description |
|------|-------------|
| `min:N` | For strings: minimum length. For numbers: minimum value. For arrays: minimum count. |
| `max:N` | For strings: maximum length. For numbers: maximum value. For arrays: maximum count. |

### Inclusion

| Rule | Description |
|------|-------------|
| `in:val1,val2,...` | Value must be one of the listed values |

### Confirmation

| Rule | Description |
|------|-------------|
| `confirmed` | Field must match a `{field}_confirmation` field |

### Database

| Rule | Description |
|------|-------------|
| `unique` | Value must not exist in the database (stub — extend to implement) |
| `exists` | Value must exist in the database (stub — extend to implement) |

---

## Custom Error Messages

Override the default error messages per field:

```php
$validator = Validator::make($request->all(), [
    'email' => 'required|email',
    'age'   => 'required|integer|min:18',
], [
    'email.required' => 'We need your email address.',
    'email.email'    => 'Please provide a valid email.',
    'age.min'        => 'You must be at least 18 years old.',
]);
```

---

## Checking Validation Results

```php
$validator->validate();  // Returns true/false
$validator->fails();     // true if there are errors
$validator->errors();    // ['field' => ['Error message', ...], ...]
$validator->validated(); // Only the validated fields (not extra input)
```

---

## Form Requests

For complex validation, create a **Form Request** class to encapsulate rules and authorization:

```bash
# No rawr command yet — create manually in App/Requests/
```

```php
<?php

namespace App\Requests;

use Lyger\Validation\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Return true to allow, false to deny (403)
        return true;
    }

    public function rules(): array
    {
        return [
            'title'     => 'required|string|max:512',
            'content'   => 'required|string',
            'published' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'A post title is required.',
            'content.required' => 'Post content cannot be empty.',
        ];
    }
}
```

Use it in a controller:

```php
public function store(Request $request): Response
{
    $formRequest = new StorePostRequest($request->all());

    try {
        $data = $formRequest->validate();
    } catch (\Lyger\Validation\ValidationException $e) {
        return Response::json(['errors' => $e->getErrors()], 422);
    }

    $post = Post::create($data);
    return Response::json($post->toArray(), 201);
}
```

---

## ValidationException

When using `FormRequest::validate()`, a `ValidationException` is thrown if validation fails:

```php
use Lyger\Validation\ValidationException;

try {
    $data = $formRequest->validate();
} catch (ValidationException $e) {
    $errors    = $e->getErrors();        // ['field' => ['msg', ...]]
    $validator = $e->getValidator();     // The Validator instance
}
```

---

## Rule Examples

```php
// Password confirmation
$validator = Validator::make($request->all(), [
    'password'              => 'required|min:8',
    'password_confirmation' => 'required',
    // 'confirmed' checks that password === password_confirmation
    // Add to password rule: 'required|min:8|confirmed'
]);

// Enum-like validation
$validator = Validator::make($request->all(), [
    'role'   => 'required|in:admin,editor,viewer',
    'status' => 'required|in:active,inactive,pending',
]);

// Regex
$validator = Validator::make($request->all(), [
    'phone'   => 'required|regex:/^\+?[0-9]{10,15}$/',
    'zip'     => 'required|regex:/^[0-9]{5}(-[0-9]{4})?$/',
]);

// Nested integer range
$validator = Validator::make($request->all(), [
    'page'     => 'integer|min:1',
    'per_page' => 'integer|min:5|max:100',
]);
```

---

## Method Reference

### Validator

| Method | Description |
|--------|-------------|
| `make(array $data, array $rules, array $messages = []): self` | Static factory |
| `validate(): bool` | Run all rules, returns true/false |
| `fails(): bool` | Returns true if there are validation errors |
| `errors(): array` | All errors as `['field' => ['msg', ...]]` |
| `validated(): array` | Only the fields that passed validation |

### FormRequest

| Method | Description |
|--------|-------------|
| `authorize(): bool` | Override to add authorization logic |
| `rules(): array` | Override to define validation rules |
| `messages(): array` | Override to customize error messages |
| `validate(): array` | Run validation, throw `ValidationException` on failure |
| `validated(): array` | Get validated data |
| `fails(): bool` | Check for errors |
| `errors(): array` | Get all errors |
