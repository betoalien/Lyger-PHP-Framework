<?php

declare(strict_types=1);

namespace Lyger\Core;

use FFI;
use Lyger\Http\Response;

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
            unsigned int lyger_abi_version(void);
            char* lyger_core_version(void);

            // Zero-Copy Database
            unsigned long lyger_db_query(const char* dsn, const char* query);
            unsigned long lyger_db_query_v2(const char* dsn, const char* query, const char* bindings_json);
            int lyger_db_health(const char* dsn);
            int lyger_db_health_with_retry(const char* dsn, unsigned int attempts, unsigned long long backoff_ms);
            int lyger_db_transaction(const char* dsn, const char* statements_json);
            char* lyger_jsonify_result(unsigned long ptr);
            char* lyger_result_chunk(unsigned long ptr, unsigned long long offset, unsigned int limit);
            void lyger_free_result(unsigned long ptr);
            char* lyger_memory_metrics(void);

            // HTTP Server
            void lyger_start_server(unsigned short port);
            int lyger_start_server_v3(unsigned short port);
            char* lyger_next_request(unsigned long long timeout_ms);
            int lyger_send_response(unsigned long long request_id, const char* response_json);
            void lyger_stop_server(void);
            char* lyger_last_error(void);
        ";

        try {
            $ffi = FFI::cdef($header, $libPath);
            if ((int) $ffi->lyger_abi_version() !== 2) {
                return;
            }
            $this->ffi = $ffi;
        } catch (\Throwable $e) {
            // An incompatible or unavailable binary uses the documented PHP fallback.
            $this->ffi = null;
        }
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
        echo "   LYGER v0.2 - Always-Alive Server\n";
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

    public function startQueueServer(int $port): bool
    {
        if ($this->ffi === null || $port < 1 || $port > 65535) {
            return false;
        }
        $started = (int) $this->ffi->lyger_start_server_v3($port) === 1;
        if (!$started) {
            $this->lastError();
        }
        return $started;
    }

    public function lastError(): string
    {
        if ($this->ffi === null) {
            return 'FFI unavailable';
        }
        $ptr = $this->ffi->lyger_last_error();
        if ($ptr === null) {
            return '';
        }
        try {
            return FFI::string($ptr);
        } finally {
            $this->ffi->lyger_free_string($ptr);
        }
    }

    public function nextRequest(int $timeoutMs = 100): ?array
    {
        if ($this->ffi === null) {
            return null;
        }
        $ptr = $this->ffi->lyger_next_request($timeoutMs);
        if ($ptr === null) {
            return null;
        }
        try {
            $payload = json_decode(FFI::string($ptr), true, 512, JSON_THROW_ON_ERROR);
            return is_array($payload) ? $payload : null;
        } finally {
            $this->ffi->lyger_free_string($ptr);
        }
    }

    public function sendResponse(int $requestId, Response $response): bool
    {
        if ($this->ffi === null) {
            return false;
        }
        $payload = json_encode([
            'status' => $response->getStatusCode(),
            'headers' => (object) $response->getHeaders(),
            'body' => $response->getContent(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return (int) $this->ffi->lyger_send_response($requestId, $payload) === 1;
    }

    // Basic Functions
    public function helloWorld(): string
    {
        if ($this->ffi === null) {
            return 'Hello from Lyger v0.2';
        }

        try {
            $result = $this->ffi->lyger_hello_world();
            $string = $result === null ? '' : FFI::string($result);
            $this->ffi->lyger_free_string($result);
            return $string;
        } catch (\Throwable $e) {
            return 'Hello from Lyger v0.2';
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
                'framework' => 'Lyger v0.2',
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

    /** Return one bounded result page without cloning the underlying rows. */
    public function resultChunk(int $ptr, int $offset = 0, int $limit = 1000): array
    {
        if ($this->ffi === null || $ptr === 0 || $offset < 0 || $limit < 1) {
            throw new \InvalidArgumentException('Invalid result chunk arguments');
        }
        $json = $this->ffi->lyger_result_chunk($ptr, $offset, $limit);
        if ($json === null) {
            throw new \RuntimeException($this->lastError() ?: 'Rust result chunk failed');
        }
        try {
            return json_decode(FFI::string($json), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            $this->ffi->lyger_free_string($json);
        }
    }

    /** @return \Generator<int, array<string, mixed>, void, void> */
    public function streamResult(int $ptr, int $chunkSize = 1000): \Generator
    {
        if ($chunkSize < 1 || $chunkSize > 10000) {
            throw new \InvalidArgumentException('Chunk size must be between 1 and 10000');
        }
        $offset = 0;
        do {
            $chunk = $this->resultChunk($ptr, $offset, $chunkSize);
            foreach ($chunk['rows'] ?? [] as $row) {
                yield $row;
            }
            $count = count($chunk['rows'] ?? []);
            $offset += $count;
        } while ($count > 0 && ($chunk['has_more'] ?? false));
    }

    public function memoryMetrics(): array
    {
        if ($this->ffi === null) {
            throw new \RuntimeException('Rust engine is unavailable');
        }
        $json = $this->ffi->lyger_memory_metrics();
        if ($json === null) {
            throw new \RuntimeException($this->lastError() ?: 'Rust memory metrics failed');
        }
        try {
            return json_decode(FFI::string($json), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            $this->ffi->lyger_free_string($json);
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

    public function dbQueryJsonV2(string $dsn, string $query, array $bindings = []): array
    {
        if ($this->ffi === null) {
            throw new \RuntimeException('Rust database driver is unavailable');
        }
        $bindingsJson = json_encode(array_values($bindings), JSON_THROW_ON_ERROR);
        $ptr = (int) $this->ffi->lyger_db_query_v2($dsn, $query, $bindingsJson);
        if ($ptr === 0) {
            throw new \RuntimeException($this->lastError() ?: 'Rust database query failed');
        }
        try {
            return json_decode($this->jsonifyResult($ptr), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            $this->freeResult($ptr);
        }
    }

    public function dbHealth(string $dsn): bool
    {
        if ($this->ffi === null) {
            return false;
        }
        return (int) $this->ffi->lyger_db_health($dsn) === 1;
    }

    public function dbHealthWithRetry(string $dsn, int $attempts = 3, int $backoffMs = 100): bool
    {
        if ($this->ffi === null) {
            return false;
        }
        return (int) $this->ffi->lyger_db_health_with_retry($dsn, max(1, $attempts), max(0, $backoffMs)) === 1;
    }

    public function dbTransaction(string $dsn, array $statements): array
    {
        if ($this->ffi === null) {
            throw new \RuntimeException('Rust database driver is unavailable');
        }
        $json = json_encode(array_values($statements), JSON_THROW_ON_ERROR);
        $ptr = (int) $this->ffi->lyger_db_transaction($dsn, $json);
        if ($ptr === 0) {
            throw new \RuntimeException($this->lastError() ?: 'Rust database transaction failed');
        }
        try {
            return json_decode($this->jsonifyResult($ptr), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            $this->freeResult($ptr);
        }
    }
}
