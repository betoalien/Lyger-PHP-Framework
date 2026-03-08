<?php

declare(strict_types=1);

namespace Lyger\Database;

/**
 * HasOne Relationship - One-to-One inverse relationship
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
        if ($result === null) {
            return null;
        }

        $class = get_class($this->parent);
        $relatedClass = str_replace('\\Models\\', '\\Models\\', $this->query->getTable());
        $relatedClass = $this->getRelatedClass();

        return new $relatedClass($result);
    }

    private function getRelatedClass(): string
    {
        $table = $this->query->getTable();
        $parts = explode('_', $table);
        $className = implode('', array_map('ucfirst', $parts));
        return "App\\Models\\" . $className;
    }
}
