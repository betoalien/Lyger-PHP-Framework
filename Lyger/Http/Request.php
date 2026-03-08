<?php

declare(strict_types=1);

namespace Lyger\Http;

final class Request
{
    private array $get;
    private array $post;
    private array $server;
    private ?array $json = null;
    private ?array $body = null;

    public function __construct()
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->server = $_SERVER;

        // Parse JSON body
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $this->body = json_decode(file_get_contents('php://input'), true) ?? [];
            $this->json = $this->body;
        }
    }

    public static function capture(): self
    {
        return new self();
    }

    public function get(string $key, $default = null)
    {
        return $this->get[$key] ?? $default;
    }

    public function post(string $key, $default = null)
    {
        return $this->post[$key] ?? $default;
    }

    public function input(string $key, $default = null)
    {
        // Check JSON body first, then POST, then GET
        if ($this->body !== null && isset($this->body[$key])) {
            return $this->body[$key];
        }
        return $this->post[$key] ?? $this->get[$key] ?? $default;
    }

    public function all(): array
    {
        if ($this->body !== null) {
            return array_merge($this->get, $this->post, $this->body);
        }
        return array_merge($this->get, $this->post);
    }

    public function method(): string
    {
        return $this->server['REQUEST_METHOD'] ?? 'GET';
    }

    public function uri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        return strtok($uri, '?');
    }

    public function header(string $key, $default = null)
    {
        $key = 'HTTP_' . str_replace('-', '_', strtoupper($key));
        return $this->server[$key] ?? $default;
    }

    public function getJson(): ?array
    {
        return $this->json;
    }

    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
