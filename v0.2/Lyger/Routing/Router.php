<?php

declare(strict_types=1);

namespace Lyger\Routing;

use Lyger\Http\Request;
use Lyger\Http\Response;
use Lyger\Container\Container;

final class Route
{
    private static array $routes = [];

    public static function get(string $path, callable|array $handler): void
    {
        self::addRoute('GET', $path, $handler);
    }

    public static function post(string $path, callable|array $handler): void
    {
        self::addRoute('POST', $path, $handler);
    }

    public static function put(string $path, callable|array $handler): void
    {
        self::addRoute('PUT', $path, $handler);
    }

    public static function delete(string $path, callable|array $handler): void
    {
        self::addRoute('DELETE', $path, $handler);
    }

    private static function addRoute(string $method, string $path, callable|array $handler): void
    {
        self::$routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
        ];
    }

    public static function getRoutes(): array
    {
        return self::$routes;
    }

    public static function clear(): void
    {
        self::$routes = [];
    }
}

final class Router
{
    private array $routes = [];
    private Container $container;
    private ?Request $currentRequest = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function registerRoute(string $method, string $path, callable|array $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $this->currentRequest = $request;
        $method = $request->method();
        $uri = $request->uri();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchRoute($route['path'], $uri);

            if ($params !== false) {
                return $this->executeHandler($route['handler'], $params);
            }
        }

        return Response::error('Not Found', 404);
    }

    private function matchRoute(string $pattern, string $uri): array|false
    {
        $patternParts = explode('/', trim($pattern, '/'));
        $uriParts = explode('/', trim($uri, '/'));

        if (count($patternParts) !== count($uriParts)) {
            return false;
        }

        $params = [];

        foreach ($patternParts as $index => $part) {
            if (str_starts_with($part, '{') && str_ends_with($part, '}')) {
                $paramName = trim($part, '{}');
                $params[$paramName] = $uriParts[$index] ?? '';
            } elseif ($part !== $uriParts[$index]) {
                return false;
            }
        }

        return $params;
    }

    private function executeHandler(callable|array $handler, array $params): Response
    {
        try {
            if (is_callable($handler)) {
                $result = $handler($this->currentRequest, ...array_values($params));
                return $this->formatResponse($result);
            }

            if (is_array($handler) && count($handler) === 2) {
                [$class, $method] = $handler;

                if (is_string($class)) {
                    $controller = $this->container->make($class);
                } else {
                    $controller = $class;
                }

                if (!method_exists($controller, $method)) {
                    throw new \RuntimeException("Method {$method} not found on controller");
                }

                // Pass Request as first parameter to controller methods
                $result = $controller->$method($this->currentRequest, ...array_values($params));
                return $this->formatResponse($result);
            }

            throw new \RuntimeException('Invalid handler');
        } catch (\Throwable $e) {
            return Response::error('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }

    private function formatResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        return Response::html((string) $result);
    }

    public function loadRoutesFromFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            require $filePath;
        }

        $routes = Route::getRoutes();
        foreach ($routes as $route) {
            $this->registerRoute($route['method'], $route['path'], $route['handler']);
        }
    }
}
