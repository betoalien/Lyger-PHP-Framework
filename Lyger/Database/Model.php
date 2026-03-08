<?php

declare(strict_types=1);

namespace Lyger\Database;

use Lyger\Container\Container;

/**
 * Model - Base class for all Eloquent models
 * Inspired by Laravel Eloquent
 */
abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $hidden = [];
    protected array $casts = [];
    protected array $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected array $attributes = [];
    protected bool $timestamps = true;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::getTable());
    }

    public static function getTable(): string
    {
        $instance = new static();
        return $instance->table ?? self::getDefaultTableName();
    }

    private static function getDefaultTableName(): string
    {
        $class = static::class;
        $shortName = (new \ReflectionClass($class))->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_', $shortName)) . 's';
    }

    public static function find($id): ?static
    {
        $result = static::query()->where('id', '=', $id)->first();
        return $result ? new static($result) : null;
    }

    public static function findOrFail($id): static
    {
        $model = static::find($id);
        if ($model === null) {
            throw new \RuntimeException("Model not found: " . static::class . " with id {$id}");
        }
        return $model;
    }

    public static function all(): Collection
    {
        return new Collection(
            array_map(fn($attributes) => new static($attributes), static::query()->get())
        );
    }

    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, $this->fillable, true)) {
                $this->attributes[$key] = $value;
            }
        }
        return $this;
    }

    public function save(): bool
    {
        $this->fillTimestamps();

        if (isset($this->attributes[$this->primaryKey])) {
            return $this->performUpdate() > 0;
        }

        return $this->performInsert();
    }

    public function delete(): bool
    {
        if (!isset($this->attributes[$this->primaryKey])) {
            return false;
        }

        return static::query()
            ->where($this->primaryKey, '=', $this->attributes[$this->primaryKey])
            ->delete() > 0;
    }

    protected function fillTimestamps(): void
    {
        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            if (!isset($this->attributes['created_at'])) {
                $this->attributes['created_at'] = $now;
            }
            $this->attributes['updated_at'] = $now;
        }
    }

    protected function performInsert(): bool
    {
        $id = static::query()->insert($this->attributes);
        if ($id) {
            $this->attributes[$this->primaryKey] = $id;
        }
        return $id > 0;
    }

    protected function performUpdate(): int
    {
        $id = $this->attributes[$this->primaryKey];
        unset($this->attributes[$this->primaryKey]);
        $result = static::query()
            ->where($this->primaryKey, '=', $id)
            ->update($this->attributes);
        $this->attributes[$this->primaryKey] = $id;
        return $result;
    }

    public function __get(string $key): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->castAttribute($key, $this->attributes[$key]);
        }

        if (method_exists($this, $key)) {
            return $this->$key();
        }

        return null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    protected function castAttribute(string $key, mixed $value): mixed
    {
        if (!isset($this->casts[$key])) {
            return $value;
        }

        $cast = $this->casts[$key];

        return match ($cast) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'array' => is_array($value) ? $value : json_decode($value, true),
            'json' => is_array($value) ? json_encode($value) : $value,
            'string' => (string) $value,
            'datetime', 'date' => $value,
            default => $value,
        };
    }

    public function toArray(): array
    {
        $attributes = $this->attributes;

        foreach ($this->hidden as $key) {
            unset($attributes[$key]);
        }

        foreach ($attributes as $key => $value) {
            $attributes[$key] = $this->castAttribute($key, $value);
        }

        return $attributes;
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function getKey(): mixed
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    public function getTableName(): string
    {
        return $this->table ?? self::getDefaultTableName();
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    // Relationship methods

    public function hasOne(string $related, string $foreignKey = null): HasOne
    {
        $relatedInstance = new $related();
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        return new HasOne(
            $related::query()->where($foreignKey, '=', $this->getKey()),
            $this,
            $foreignKey,
            $relatedInstance->getPrimaryKey()
        );
    }

    public function hasMany(string $related, string $foreignKey = null): HasMany
    {
        $relatedInstance = new $related();
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        return new HasMany(
            $related::query()->where($foreignKey, '=', $this->getKey()),
            $this,
            $foreignKey,
            $relatedInstance->getPrimaryKey()
        );
    }

    public function belongsTo(string $related, string $foreignKey = null): BelongsTo
    {
        $relatedInstance = new $related();
        $foreignKey = $foreignKey ?? $relatedInstance->getForeignKey();
        return new BelongsTo(
            $related::query(),
            $this,
            $foreignKey,
            $relatedInstance->getPrimaryKey()
        );
    }

    public function belongsToMany(string $related, string $pivotTable = null): BelongsToMany
    {
        $relatedInstance = new $related();
        $pivotTable = $pivotTable ?? $this->getPivotTable($related);
        return new BelongsToMany(
            $this,
            $relatedInstance,
            $pivotTable,
            $this->getForeignKey(),
            $relatedInstance->getForeignKey()
        );
    }

    protected function getForeignKey(): string
    {
        return strtolower((new \ReflectionClass($this))->getShortName()) . '_id';
    }

    protected function getPivotTable(string $related): string
    {
        $models = [static::class, $related];
        sort($models);
        $shortNames = array_map(
            fn($model) => strtolower(preg_replace('/(?<!^)[A-Z]/', '_', (new \ReflectionClass($model))->getShortName())),
            $models
        );
        return implode('_', $shortNames);
    }
}
