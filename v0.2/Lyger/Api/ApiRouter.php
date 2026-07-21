<?php

declare(strict_types=1);

namespace Lyger\Api;

use Lyger\Http\Request;

/**
 * ApiRouter - Router for API routes with versioning
 */
class ApiRouter
{
    private string $version;
    private array $routes = [];

    public function __construct(string $version = 'v1')
    {
        $this->version = $version;
    }

    public function get(string $path, callable|array $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, callable|array $handler): self
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute(string $method, string $path, callable|array $handler): self
    {
        $prefix = "/api/{$this->version}";
        $fullPath = $prefix . ($path === '/' ? '' : $path);

        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'handler' => $handler,
            'api' => true,
            'version' => $this->version,
        ];

        return $this;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function group(callable $callback): void
    {
        $callback($this);
    }

    public function middleware(callable $middleware): self
    {
        // Apply middleware to all routes
        foreach ($this->routes as &$route) {
            $route['middleware'] = $middleware;
        }
        return $this;
    }
}

/**
 * ApiVersion - API versioning helper
 */
class ApiVersion
{
    private static array $versions = ['v1', 'v2', 'v3'];

    public static function getLatest(): string
    {
        return end(self::$versions);
    }

    public static function getSupported(): array
    {
        return self::$versions;
    }

    public static function isSupported(string $version): bool
    {
        return in_array($version, self::$versions, true);
    }

    public static function parseFromRequest(Request $request): ?string
    {
        $uri = $request->uri();

        if (preg_match('/\/api\/(v\d+)/', $uri, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
