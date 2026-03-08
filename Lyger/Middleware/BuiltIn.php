<?php

declare(strict_types=1);

namespace Lyger\Middleware;

use Lyger\Http\Request;
use Lyger\Http\Response;

/**
 * CORS Middleware - Handles Cross-Origin Resource Sharing
 * Inspired by Laravel CORS middleware
 */
class CorsMiddleware extends Middleware
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
            'exposed_headers' => [],
            'max_age' => 86400,
            'supports_credentials' => false,
        ], $config);
    }

    public function handle(Request $request, callable $next): Response
    {
        $origin = $request->header('Origin', '*');

        if (in_array('*', $this->config['allowed_origins'], true) || in_array($origin, $this->config['allowed_origins'], true)) {
            $response = $next($request);

            $response->setHeader('Access-Control-Allow-Origin', $origin);
            $response->setHeader('Access-Control-Allow-Methods', implode(', ', $this->config['allowed_methods']));
            $response->setHeader('Access-Control-Allow-Headers', implode(', ', $this->config['allowed_headers']));
            $response->setHeader('Access-Control-Max-Age', (string) $this->config['max_age']);

            if ($this->config['supports_credentials']) {
                $response->setHeader('Access-Control-Allow-Credentials', 'true');
            }

            if (!empty($this->config['exposed_headers'])) {
                $response->setHeader('Access-Control-Expose-Headers', implode(', ', $this->config['exposed_headers']));
            }

            return $response;
        }

        return Response::error('Origin not allowed', 403);
    }
}

/**
 * Rate Limiting Middleware - Prevents abuse
 * Inspired by Laravel ThrottleRequests
 */
class RateLimitMiddleware extends Middleware
{
    private int $maxAttempts;
    private int $decaySeconds;
    private array $storage;

    public function __construct(int $maxAttempts = 60, int $decaySeconds = 60)
    {
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
        $this->storage = [];
    }

    public function handle(Request $request, callable $next): Response
    {
        $key = $this->resolveRequestSignature($request);
        $now = time();

        if (!isset($this->storage[$key])) {
            $this->storage[$key] = [
                'attempts' => 0,
                'reset_at' => $now + $this->decaySeconds,
            ];
        }

        $record = &$this->storage[$key];

        if ($now > $record['reset_at']) {
            $record = [
                'attempts' => 0,
                'reset_at' => $now + $this->decaySeconds,
            ];
        }

        $record['attempts']++;

        if ($record['attempts'] > $this->maxAttempts) {
            $retryAfter = $record['reset_at'] - $now;
            return Response::json([
                'error' => 'Too Many Requests',
                'message' => 'Rate limit exceeded. Try again later.',
                'retry_after' => $retryAfter,
            ], 429, [
                'X-RateLimit-Limit' => (string) $this->maxAttempts,
                'X-RateLimit-Remaining' => '0',
                'Retry-After' => (string) $retryAfter,
            ]);
        }

        $response = $next($request);

        $remaining = $this->maxAttempts - $record['attempts'];
        $response->setHeader('X-RateLimit-Limit', (string) $this->maxAttempts);
        $response->setHeader('X-RateLimit-Remaining', (string) $remaining);
        $response->setHeader('X-RateLimit-Reset', (string) $record['reset_at']);

        return $response;
    }

    private function resolveRequestSignature(Request $request): string
    {
        return sha1($request->ip() . '|' . $request->uri());
    }
}

/**
 * Authentication Middleware - Simple token-based auth
 * Inspired by Laravel auth middleware
 */
class AuthMiddleware extends Middleware
{
    private ?string $token;
    private string $headerName;

    public function __construct(?string $token = null, string $headerName = 'Authorization')
    {
        $this->token = $token;
        $this->headerName = $headerName;
    }

    public function handle(Request $request, callable $next): Response
    {
        $token = $request->header($this->headerName);

        if ($token === null) {
            return Response::json([
                'error' => 'Unauthorized',
                'message' => 'Authentication token required',
            ], 401);
        }

        $token = str_replace('Bearer ', '', $token);

        if ($this->token !== null && $token !== $this->token) {
            return Response::json([
                'error' => 'Unauthorized',
                'message' => 'Invalid authentication token',
            ], 401);
        }

        return $next($request);
    }
}

/**
 * JSON Body Parser Middleware
 * Inspired by Laravel middleware for parsing JSON
 */
class JsonParserMiddleware extends Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $contentType = $request->header('Content-Type', '');

        if (str_contains($contentType, 'application/json')) {
            $json = $request->getJson();
            if ($json !== null) {
                $_POST = array_merge($_POST, $json);
            }
        }

        return $next($request);
    }
}

/**
 * Logging Middleware - Logs HTTP requests
 */
class LoggingMiddleware extends Middleware
{
    private $logger;

    public function __construct(?callable $logger = null)
    {
        $this->logger = $logger ?? function ($request, $response) {
            $timestamp = date('Y-m-d H:i:s');
            $method = $request->method();
            $uri = $request->uri();
            $status = $response->getStatusCode();
            $ip = $request->ip();
            echo "[{$timestamp}] {$method} {$uri} - {$status} - {$ip}\n";
        };
    }

    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        ($this->logger)($request, $response);

        return $response;
    }
}
