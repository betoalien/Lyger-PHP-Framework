<?php

declare(strict_types=1);

namespace Lyger\Database;

/**
 * HasOne Relationship - One to One relationship (child side)
 */
class HasOne
{
    private QueryBuilder $query;
    private Model $parent;
    private string $foreignKey;
    private string $localKey;

    public function __construct(QueryBuilder $query, Model $parent, string $foreignKey, string $localKey)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
    }

    public function get(): ?Model
    {
        $result = $this->query->first();
        if (!$result) {
            return null;
        }

        $class = get_class($this->parent);
        $relatedClass = str_replace('\\Models\\', '\\Models\\', substr($class, 0, strrpos($class, '\\')) . '\\Models\\');
        $relatedClass = (new \ReflectionClass($this->query))->getName();
        $relatedClass = preg_replace('/\\\\QueryBuilder$/', '', $relatedClass);

        return new $relatedClass($result);
    }
}

/**
 * HasMany Relationship - One to Many relationship (child side)
 */
class HasMany
{
    private QueryBuilder $query;
    private Model $parent;
    private string $foreignKey;
    private string $localKey;

    public function __construct(QueryBuilder $query, Model $parent, string $foreignKey, string $localKey)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
    }

    public function get(): Collection
    {
        $results = $this->query->get();
        $items = [];

        foreach ($results as $result) {
            $relatedClass = $this->getRelatedClass();
            $items[] = new $relatedClass($result);
        }

        return new Collection($items);
    }

    public function first(): ?Model
    {
        return $this->get()->first();
    }

    public function count(): int
    {
        return $this->query->count();
    }

    private function getRelatedClass(): string
    {
        $queryClass = (new \ReflectionClass($this->query))->getName();
        return preg_replace('/\\\\QueryBuilder$/', '', $queryClass);
    }
}

/**
 * BelongsTo Relationship - Inverse of HasOne/HasMany (parent side)
 */
class BelongsTo
{
    private QueryBuilder $query;
    private Model $parent;
    private string $foreignKey;
    private string $ownerKey;

    public function __construct(QueryBuilder $query, Model $parent, string $foreignKey, string $ownerKey)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
    }

    public function get(): ?Model
    {
        $foreignKeyValue = $this->parent->{$this->foreignKey};
        if ($foreignKeyValue === null) {
            return null;
        }

        $result = $this->query->where($this->ownerKey, '=', $foreignKeyValue)->first();
        if (!$result) {
            return null;
        }

        $relatedClass = $this->getRelatedClass();
        return new $relatedClass($result);
    }

    private function getRelatedClass(): string
    {
        $queryClass = (new \ReflectionClass($this->query))->getName();
        return preg_replace('/\\\\QueryBuilder$/', '', $queryClass);
    }
}

/**
 * BelongsToMany Relationship - Many to Many relationship
 */
class BelongsToMany
{
    private Model $parent;
    private Model $related;
    private string $pivotTable;
    private string $parentKey;
    private string $relatedKey;

    public function __construct(Model $parent, Model $related, string $pivotTable, string $parentKey, string $relatedKey)
    {
        $this->parent = $parent;
        $this->related = $related;
        $this->pivotTable = $pivotTable;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;
    }

    public function get(): Collection
    {
        $parentKeyValue = $this->parent->getKey();

        $pivotQuery = new QueryBuilder($this->pivotTable);
        $pivotResults = $pivotQuery->where($this->parentKey, '=', $parentKeyValue)->get();

        if (empty($pivotResults)) {
            return new Collection([]);
        }

        $relatedIds = array_column($pivotResults, $this->relatedKey);
        $relatedQuery = new QueryBuilder($this->related->getTable());
        $relatedResults = $relatedQuery->whereIn($this->related->getPrimaryKey(), $relatedIds)->get();

        $items = [];
        $relatedClass = get_class($this->related);
        foreach ($relatedResults as $result) {
            $items[] = new $relatedClass($result);
        }

        return new Collection($items);
    }
}
