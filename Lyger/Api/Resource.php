<?php

declare(strict_types=1);

namespace Lyger\Api;

/**
 * ApiResource - Transform Eloquent models to JSON API responses
 * Inspired by Laravel API Resources
 */

use Lyger\Database\Model;

/**
 * ApiResource - Base class for API resources
 */
abstract class ApiResource
{
    protected mixed $resource;

    public function __construct(mixed $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Transform the resource into an array
     */
    abstract public function toArray(): array;

    /**
     * Create a resource collection
     */
    public static function collection(mixed $resources): ApiResourceCollection
    {
        return new ApiResourceCollection($resources, static::class);
    }

    /**
     * Get the resource data
     */
    public function getResource(): mixed
    {
        return $this->resource;
    }

    /**
     * Transform to JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Include a relationship
     */
    protected function include(string $relation, callable $callback): array
    {
        if (!isset($this->resource->$relation)) {
            return [];
        }

        $result = $callback($this->resource->$relation);

        return [
            $relation => $result,
        ];
    }

    /**
     * Include if condition is met
     */
    protected function when(bool $condition, mixed $value, mixed $default = null): mixed
    {
        return $condition ? $value : $default;
    }

    /**
     * Merge data
     */
    protected function merge(array $data): array
    {
        return array_merge($this->toArray(), $data);
    }

    /**
     * Add meta data
     */
    protected function meta(array $meta): array
    {
        return ['meta' => $meta];
    }
}

/**
 * ApiResourceCollection - Collection of resources
 */
class ApiResourceCollection
{
    protected array $resources;
    protected string $collects;
    protected ?array $with = null;
    protected ?array $withMeta = null;

    public function __construct(mixed $resources, ?string $collects = null)
    {
        $this->resources = is_array($resources) ? $resources : iterator_to_array($resources);
        $this->collects = $collects ?? ApiResource::class;
    }

    /**
     * Include relationships
     */
    public function with(array $relations): self
    {
        $this->with = $relations;
        return $this;
    }

    /**
     * Add meta information
     */
    public function withMeta(array $meta): self
    {
        $this->withMeta = $meta;
        return $this;
    }

    /**
     * Transform the collection
     */
    public function toArray(): array
    {
        $data = array_map(function ($resource) {
            $instance = new $this->collects($resource);
            return $instance->toArray();
        }, $this->resources);

        $result = ['data' => $data];

        if ($this->withMeta) {
            $result['meta'] = $this->withMeta;
        }

        return $result;
    }

    /**
     * Transform to JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Count items
     */
    public function count(): int
    {
        return count($this->resources);
    }
}

/**
 * JsonApiResource - Simple JSON response wrapper
 */
class JsonApiResource
{
    private mixed $data;
    private array $headers = [];

    public function __construct(mixed $data)
    {
        $this->data = $data;
    }

    /**
     * Add meta to response
     */
    public function meta(array $meta): self
    {
        $this->headers['meta'] = $meta;
        return $this;
    }

    /**
     * Add pagination info
     */
    public function pagination(int $currentPage, int $perPage, int $total): self
    {
        $this->headers['pagination'] = [
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage),
        ];
        return $this;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        $response = [];

        if ($this->data instanceof ApiResource) {
            $response['data'] = $this->data->toArray();
        } elseif ($this->data instanceof ApiResourceCollection) {
            return $this->data->toArray();
        } elseif (is_array($this->data)) {
            $response['data'] = $this->data;
        } else {
            $response['data'] = $this->data;
        }

        if ($this->headers) {
            $response = array_merge($response, $this->headers);
        }

        return $response;
    }

    /**
     * Convert to JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}
