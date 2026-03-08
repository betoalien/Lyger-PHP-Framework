---
layout: default
title: ORM & Models
nav_order: 7
---

# ORM & Models

---

## Overview

Lyger's ORM is inspired by Laravel's Eloquent. Each database table has a corresponding **Model** class that provides an expressive, fluent interface for querying and mutating data.

---

## Defining a Model

Generate a model with the CLI:

```bash
php rawr make:model Post
php rawr make:model Post --migration   # Also creates a migration
```

A model lives in `App/Models/` and extends `Lyger\Database\Model`:

```php
<?php

namespace App\Models;

use Lyger\Database\Model;

class Post extends Model
{
    // Table name (optional — auto-derived: 'Post' → 'posts')
    protected string $table = 'posts';

    // Columns that can be mass-assigned
    protected array $fillable = ['title', 'content', 'user_id', 'published'];

    // Columns hidden from toArray()/toJson()
    protected array $hidden = [];

    // Automatic type casting
    protected array $casts = [
        'published' => 'bool',
        'user_id'   => 'int',
        'metadata'  => 'array',     // JSON stored as array
    ];

    // Auto-manage created_at / updated_at
    protected bool $timestamps = true;
}
```

---

## Table Name Convention

| Model Class | Default Table |
|-------------|---------------|
| `User` | `users` |
| `Post` | `posts` |
| `BlogPost` | `blog_posts` |
| `OrderItem` | `order_items` |

Override with `protected string $table = 'custom_table';`.

---

## Creating Records

```php
// Mass assignment (uses $fillable)
$post = Post::create([
    'title'   => 'Hello World',
    'content' => 'My first post.',
    'user_id' => 1,
]);

// Instantiate and save
$post = new Post();
$post->title   = 'Hello World';
$post->content = 'My first post.';
$post->save();
```

---

## Reading Records

```php
// Find by primary key
$post = Post::find(1);          // Returns null if not found
$post = Post::findOrFail(1);    // Throws exception if not found

// All records
$posts = Post::all();           // Returns a Collection

// Using the query builder
$posts = Post::query()
    ->where('published', true)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();
```

---

## Updating Records

```php
// Direct property mutation
$post = Post::find(1);
$post->title = 'Updated Title';
$post->save();

// Mass assignment
$post->fill(['title' => 'New Title', 'content' => 'New content.']);
$post->save();
```

---

## Deleting Records

```php
$post = Post::find(1);
$post->delete();
```

If your table has a `deleted_at` column (soft deletes via `$table->softDeletes()`), `delete()` sets the timestamp instead of removing the row.

---

## Checking if Model is Persisted

```php
// New vs saved
$post = new Post(['title' => 'Draft']);
$post->getKey(); // null — not yet saved

$post->save();
$post->getKey(); // 1 — now has an ID
```

---

## Type Casting

Configure `$casts` to auto-convert column values:

```php
protected array $casts = [
    'age'       => 'int',
    'price'     => 'float',
    'is_active' => 'bool',
    'settings'  => 'array',   // JSON string ↔ PHP array
    'metadata'  => 'json',    // Alias for array
    'born_at'   => 'datetime',
];
```

Values are cast automatically when accessed via `$model->attribute`:

```php
$user->age;       // int(25), not string '25'
$user->is_active; // bool(true), not string '1'
$user->settings;  // array(['theme' => 'dark']), not JSON string
```

---

## Serialization

```php
$post->toArray();           // Array (respects $hidden)
$post->toJson();            // JSON string
$post->toJson(JSON_PRETTY_PRINT);  // Pretty-printed JSON
```

---

## Relationships

### Has One

```php
class User extends Model
{
    public function profile()
    {
        return $this->hasOne(Profile::class, 'user_id');
    }
}

$profile = User::find(1)->profile();
```

### Has Many

```php
class User extends Model
{
    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id');
    }
}

$posts = User::find(1)->posts(); // Returns array of Posts
```

### Belongs To

```php
class Post extends Model
{
    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

$author = Post::find(1)->author();
```

### Belongs To Many

```php
class Post extends Model
{
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'post_tag');
    }
}

$tags = Post::find(1)->tags(); // Through pivot table 'post_tag'
```

---

## Collections

`Model::all()` and query results return a `Collection` object with chainable methods:

```php
$posts = Post::all();

// Filter
$published = $posts->filter(fn($p) => $p['published'] === true);

// Map
$titles = $posts->map(fn($p) => $p['title']);

// Pluck a column
$ids = $posts->pluck('id');

// Sort
$sorted = $posts->sortBy('created_at');

// Paginate-like operations
$first5 = $posts->take(5);

// Serialize
$array = $posts->toArray();
$json  = $posts->toJson();
```

See the [Query Builder](query-builder) doc for filtering before fetching from the database.

---

## Mass Assignment Protection

Only columns listed in `$fillable` can be set via `create()` or `fill()`:

```php
protected array $fillable = ['name', 'email'];

// 'is_admin' is NOT in $fillable → silently ignored
User::create(['name' => 'Alice', 'email' => 'alice@test.com', 'is_admin' => true]);
```

---

## Model Properties Reference

| Property | Type | Description |
|----------|------|-------------|
| `$table` | `string` | Table name (auto-derived if omitted) |
| `$primaryKey` | `string` | Primary key column (default: `'id'`) |
| `$fillable` | `array` | Mass-assignable columns |
| `$hidden` | `array` | Columns excluded from serialization |
| `$casts` | `array` | Column type casting map |
| `$dates` | `array` | Date columns (default: `['created_at','updated_at','deleted_at']`) |
| `$timestamps` | `bool` | Auto-manage timestamps (default: `true`) |

---

## Static Method Reference

| Method | Description |
|--------|-------------|
| `find($id): ?static` | Find by primary key or null |
| `findOrFail($id): static` | Find by primary key or throw |
| `all(): Collection` | All rows as Collection |
| `create(array $attributes): static` | Insert and return model |
| `query(): QueryBuilder` | Start a fluent query |
| `getTable(): string` | Get resolved table name |

## Instance Method Reference

| Method | Description |
|--------|-------------|
| `fill(array $attrs): self` | Mass-assign (respects $fillable) |
| `save(): bool` | INSERT (new) or UPDATE (existing) |
| `delete(): bool` | Delete or soft-delete row |
| `toArray(): array` | Serialize to array |
| `toJson(int $opts = 0): string` | Serialize to JSON |
| `getKey(): mixed` | Get primary key value |
| `hasOne(string $related, ?string $fk): HasOne` | Define has-one relation |
| `hasMany(string $related, ?string $fk): HasMany` | Define has-many relation |
| `belongsTo(string $related, ?string $fk): BelongsTo` | Define belongs-to relation |
| `belongsToMany(string $related, ?string $pivot): BelongsToMany` | Define many-to-many relation |
