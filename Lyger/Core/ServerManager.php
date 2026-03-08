<?php

declare(strict_types=1);

namespace Lyger\Core;

/**
 * Always-Alive Server Manager
 * Manages the persistent PHP worker that stays alive in memory
 */
class ServerManager
{
    private static bool $running = false;
    /** @var callable|null */
    private static $router = null;
    private static array $loadedClasses = [];

    /**
     * Start the Always-Alive server
     * This keeps PHP alive in memory between requests
     */
    public static function start(callable $router): void
    {
        self::$running = true;
        self::$router = $router;

        // Preload all framework classes into memory
        self::preloadFramework();

        echo "\n";
        echo "========================================\n";
        echo "   LYGER SERVER v0.1\n";
        echo "   Always-Alive Mode\n";
        echo "========================================\n\n";
        echo "✓ Framework loaded in memory\n";
        echo "✓ Waiting for requests...\n";
        echo "   Ctrl+C to stop\n\n";

        // Keep PHP alive - waiting for FFI callbacks from Rust
        while (self::$running) {
            // In a real implementation, this would wait for Rust to invoke callbacks
            // For now, we simulate with a simple sleep
            sleep(1);

            // Check if we should stop
            if (!self::$running) {
                break;
            }
        }
    }

    /**
     * Handle a request from Rust (callback)
     */
    public static function handleRequest(string $uri, string $method, array $data = []): string
    {
        if (self::$router === null) {
            return json_encode(['error' => 'No router configured']);
        }

        try {
            // Execute the router - this is instant since everything is in memory!
            $response = (self::$router)($uri, $method, $data);
            return $response;
        } catch (\Throwable $e) {
            return json_encode([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * Preload all framework classes into memory
     * This makes subsequent requests instant
     */
    private static function preloadFramework(): void
    {
        $basePath = dirname(__DIR__, 2);

        // Preload core classes
        $classes = [
            // Core
            'Lyger\Core\Engine',
            'Lyger\Routing\Router',
            'Lyger\Http\Request',
            'Lyger\Http\Response',

            // Container
            'Lyger\Container\Container',

            // Database
            'Lyger\Database\QueryBuilder',
            'Lyger\Database\Schema',
            'Lyger\Database\Model',
            'Lyger\Database\Collection',

            // Middleware
            'Lyger\Middleware\Middleware',
            'Lyger\Middleware\CorsMiddleware',
            'Lyger\Middleware\RateLimitMiddleware',
            'Lyger\Middleware\AuthMiddleware',

            // Validation
            'Lyger\Validation\Validator',

            // Cache
            'Lyger\Cache\Cache',

            // Foundation
            'Lyger\Foundation\Env',
            'Lyger\Foundation\Path',
        ];

        foreach ($classes as $class) {
            if (class_exists($class)) {
                self::$loadedClasses[] = $class;
            }
        }
    }

    /**
     * Stop the server
     */
    public static function stop(): void
    {
        self::$running = false;
    }

    /**
     * Get loaded class count
     */
    public static function getLoadedClasses(): int
    {
        return count(self::$loadedClasses);
    }

    /**
     * Check if server is running
     */
    public static function isRunning(): bool
    {
        return self::$running;
    }
}
