<?php

declare(strict_types=1);

namespace Lyger\Database;

/**
 * Collection - Base collection class for Eloquent collections
 */
class Collection
{
    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function all(): array
    {
        return $this->items;
    }

    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    public function last(): mixed
    {
        return end($this->items) ?: null;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function map(callable $callback): Collection
    {
        return new Collection(array_map($callback, $this->items));
    }

    public function filter(callable $callback): Collection
    {
        return new Collection(array_filter($this->items, $callback));
    }

    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    public function pluck(string $value, ?string $key = null): array
    {
        $result = [];
        foreach ($this->items as $item) {
            if (is_array($item)) {
                $result[] = $item[$value] ?? null;
            } elseif (is_object($item) && isset($item->$value)) {
                $result[] = $item->$value;
            }
        }
        return $result;
    }

    public function where(string $key, $operator, $value = null): Collection
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        return $this->filter(function ($item) use ($key, $operator, $value) {
            $itemValue = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);

            return match ($operator) {
                '=', '==' => $itemValue == $value,
                '===', '===' => $itemValue === $value,
                '!=', '<>' => $itemValue != $value,
                '<' => $itemValue < $value,
                '>' => $itemValue > $value,
                '<=' => $itemValue <= $value,
                '>=' => $itemValue >= $value,
                'like' => str_contains($itemValue, $value),
                default => false,
            };
        });
    }

    public function sortBy(string $key, int $direction = SORT_ASC): Collection
    {
        $items = $this->items;
        usort($items, function ($a, $b) use ($key, $direction) {
            $aValue = is_array($a) ? ($a[$key] ?? '') : ($a->$key ?? '');
            $bValue = is_array($b) ? ($b[$key] ?? '') : ($b->$key ?? '');

            $result = $aValue <=> $bValue;
            return $direction === SORT_DESC ? -$result : $result;
        });

        return new Collection($items);
    }

    public function sortByDesc(string $key): Collection
    {
        return $this->sortBy($key, SORT_DESC);
    }

    public function take(int $limit): Collection
    {
        return new Collection(array_slice($this->items, 0, $limit));
    }

    public function flatten(): Collection
    {
        return new Collection(array_merge(...array_map(fn($item) => is_array($item) ? $item : [$item], $this->items)));
    }

    public function values(): Collection
    {
        return new Collection(array_values($this->items));
    }

    public function keys(): Collection
    {
        return new Collection(array_keys($this->items));
    }

    public function toArray(): array
    {
        return array_map(function ($item) {
            if ($item instanceof Model) {
                return $item->toArray();
            }
            return is_array($item) ? $item : (array) $item;
        }, $this->items);
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
