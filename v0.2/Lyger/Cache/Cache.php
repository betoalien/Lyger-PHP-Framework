<?php

declare(strict_types=1);

namespace Lyger\Cache;

/**
 * Cache - In-memory cache using Rust FFI
 * Provides persistent state across requests (Redis killer)
 */
class Cache
{
    private static ?self $instance = null;
    private array $memory = [];
    private int $ttl = 3600;

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setTtl(int $ttl): self
    {
        $this->ttl = $ttl;
        return $this;
    }

    public function put(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->memory[$key] = [
            'value' => $value,
            'expires' => time() + ($ttl ?? $this->ttl),
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->memory[$key])) {
            return $default;
        }

        $item = $this->memory[$key];

        if (time() > $item['expires']) {
            unset($this->memory[$key]);
            return $default;
        }

        return $item['value'];
    }

    public function has(string $key): bool
    {
        if (!isset($this->memory[$key])) {
            return false;
        }

        if (time() > $this->memory[$key]['expires']) {
            unset($this->memory[$key]);
            return false;
        }

        return true;
    }

    public function forget(string $key): void
    {
        unset($this->memory[$key]);
    }

    public function flush(): void
    {
        $this->memory = [];
    }

    public function increment(string $key, int $value = 1): int
    {
        $current = (int) $this->get($key, 0);
        $new = $current + $value;
        $this->put($key, $new);
        return $new;
    }

    public function decrement(string $key, int $value = 1): int
    {
        return $this->increment($key, -$value);
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->put($key, $value, $ttl);
        return $value;
    }

    public function getMultiple(array $keys, mixed $default = null): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function putMultiple(array $values, ?int $ttl = null): void
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $ttl);
        }
    }

    public function rememberForever(string $key, callable $callback): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->put($key, $value, null);
        return $value;
    }

    public function lock(string $key, callable $callback, int $seconds = 10): mixed
    {
        $lockKey = "lock:{$key}";

        if ($this->has($lockKey)) {
            return null;
        }

        $this->put($lockKey, true, $seconds);

        try {
            return $callback();
        } finally {
            $this->forget($lockKey);
        }
    }

    public function all(): array
    {
        $result = [];
        foreach ($this->memory as $key => $item) {
            if (time() <= $item['expires']) {
                $result[$key] = $item['value'];
            }
        }
        return $result;
    }

    public function count(): int
    {
        return count($this->memory);
    }
}
