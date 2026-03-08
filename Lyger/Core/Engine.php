<?php

declare(strict_types=1);

namespace Lyger\Core;

use FFI;

final class Engine
{
    private static ?self $instance = null;
    private ?FFI $ffi = null;
    private static bool $serverRunning = false;

    private function __construct()
    {
        $this->initializeFFI();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeFFI(): void
    {
        $libPath = $this->findLibrary();

        if ($libPath === null) {
            // FFI not available - use PHP fallback
            return;
        }

        $header = "
            char* lyger_hello_world(void);
            double lyger_heavy_computation(unsigned long iterations);
            char* lyger_system_info(void);
            void lyger_cache_set(const char* key, const char* value);
            char* lyger_cache_get(const char* key);
            void lyger_cache_delete(const char* key);
            void lyger_cache_clear(void);
            unsigned long lyger_cache_size(void);
            void lyger_free_string(char* ptr);
            void lyger_free_engine(void* ptr);

            // Zero-Copy Database
            unsigned long lyger_db_query(const char* dsn, const char* query);
            char* lyger_jsonify_result(unsigned long ptr);
            void lyger_free_result(unsigned long ptr);

            // HTTP Server
            void lyger_start_server(unsigned short port);
            void lyger_stop_server(void);
        ";

        $this->ffi = FFI::cdef($header, $libPath);
    }

    private function findLibrary(): ?string
    {
        // Use realpath to resolve any symlinks or path issues
        $basePath = realpath(dirname(__DIR__, 2));

        if ($basePath === false) {
            $basePath = dirname(__DIR__, 2);
        }

        $os = PHP_OS;
        $arch = $this->detectArchitecture();

        // Direct path to library based on OS and architecture
        if ($os === 'Darwin') {
            $libFile = ($arch === 'arm64')
                ? 'lyger-MacOS-ARM64.dylib'
                : 'lyger-MacOS-Intel.dylib';
            $path = $basePath . '/libraries/libs/Mac/' . $libFile;
            if (file_exists($path)) {
                return $path;
            }
        } elseif ($os === 'WINNT') {
            $path = $basePath . '/libraries/libs/Win/lyger-Windows-x64.dll';
            if (file_exists($path)) {
                return $path;
            }
        } else {
            $path = $basePath . '/libraries/libs/Linux/lyger-Linux-x64.so';
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function detectArchitecture(): string
    {
        if (PHP_OS === 'Darwin') {
            $uname = trim(shell_exec('uname -m'));
            return ($uname === 'arm64') ? 'arm64' : 'x86_64';
        }
        return 'x64';
    }

    private function getLibraryNames(): array
    {
        return [];
    }

    /**
     * Start the Always-Alive server
     * PHP worker stays in memory, Rust handles HTTP
     */
    public static function startServer(callable $routerHandler, int $port = 8000): void
    {
        echo "\n";
        echo "========================================\n";
        echo "   LYGER v0.1 - Always-Alive Server\n";
        echo "========================================\n\n";

        // Preload framework
        echo "Loading framework into memory...\n";
        ServerManager::start($routerHandler);

        // Try to start Rust server if FFI available
        $instance = self::getInstance();

        if ($instance->ffi !== null) {
            try {
                echo "Starting Rust HTTP server on port {$port}...\n";
                $instance->ffi->lyger_start_server($port);
                self::$serverRunning = true;
            } catch (\Throwable $e) {
                echo "Note: Using PHP built-in server (FFI start_server not available)\n";
            }
        } else {
            echo "Note: Using PHP built-in server (FFI not available)\n";
        }

        // Keep the PHP worker alive
        while (self::$serverRunning) {
            sleep(1);
        }
    }

    /**
     * Stop the server
     */
    public static function stopServer(): void
    {
        self::$serverRunning = false;

        try {
            $instance = self::getInstance();
            if ($instance->ffi !== null) {
                $instance->ffi->lyger_stop_server();
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        ServerManager::stop();
    }

    // Basic Functions
    public function helloWorld(): string
    {
        if ($this->ffi === null) {
            return 'Hello from Lyger v0.1';
        }

        try {
            $result = $this->ffi->lyger_hello_world();
            $string = $result === null ? '' : FFI::string($result);
            $this->ffi->lyger_free_string($result);
            return $string;
        } catch (\Throwable $e) {
            return 'Hello from Lyger v0.1';
        }
    }

    public function heavyComputation(int $iterations = 1000000): float
    {
        if ($this->ffi === null) {
            $result = 0;
            for ($i = 0; $i < $iterations; $i++) {
                $result += sqrt($i) * sin($i);
            }
            return $result;
        }

        try {
            return $this->ffi->lyger_heavy_computation($iterations);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function systemInfo(): string
    {
        if ($this->ffi === null) {
            return json_encode([
                'framework' => 'Lyger v0.1',
                'mode' => 'Always-Alive',
                'status' => 'running',
                'php_version' => PHP_VERSION,
            ]);
        }

        try {
            $result = $this->ffi->lyger_system_info();
            $string = $result === null ? '' : FFI::string($result);
            $this->ffi->lyger_free_string($result);
            return $string;
        } catch (\Throwable $e) {
            return '{}';
        }
    }

    // Cache
    public function cacheSet(string $key, string $value): void
    {
        if ($this->ffi === null) {
            return;
        }

        try {
            $this->ffi->lyger_cache_set($key, $value);
        } catch (\Throwable $e) {
            // Ignore
        }
    }

    public function cacheGet(string $key): string
    {
        if ($this->ffi === null) {
            return '';
        }

        try {
            $result = $this->ffi->lyger_cache_get($key);
            $string = FFI::string($result);
            $this->ffi->lyger_free_string($result);
            return $string;
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function cacheDelete(string $key): void
    {
        if ($this->ffi === null) {
            return;
        }

        try {
            $this->ffi->lyger_cache_delete($key);
        } catch (\Throwable $e) {
            // Ignore
        }
    }

    public function cacheClear(): void
    {
        if ($this->ffi === null) {
            return;
        }

        try {
            $this->ffi->lyger_cache_clear();
        } catch (\Throwable $e) {
            // Ignore
        }
    }

    public function cacheSize(): int
    {
        if ($this->ffi === null) {
            return 0;
        }

        try {
            return (int) $this->ffi->lyger_cache_size();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // Zero-Copy Database
    public function dbQuery(string $dsn, string $query): int
    {
        if ($this->ffi === null) {
            return 0;
        }

        try {
            return (int) $this->ffi->lyger_db_query($dsn, $query);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function jsonifyResult(int $ptr): string
    {
        if ($this->ffi === null || $ptr === 0) {
            return '[]';
        }

        try {
            $result = $this->ffi->lyger_jsonify_result($ptr);
            $string = FFI::string($result);
            $this->ffi->lyger_free_string($result);
            return $string;
        } catch (\Throwable $e) {
            return '[]';
        }
    }

    public function freeResult(int $ptr): void
    {
        if ($this->ffi === null || $ptr === 0) {
            return;
        }

        try {
            $this->ffi->lyger_free_result($ptr);
        } catch (\Throwable $e) {
            // Ignore
        }
    }

    public function dbQueryJson(string $dsn, string $query): string
    {
        $ptr = $this->dbQuery($dsn, $query);
        if ($ptr === 0) {
            return '[]';
        }

        $json = $this->jsonifyResult($ptr);
        $this->freeResult($ptr);
        return $json;
    }
}
