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

    /** Build a request from the Rust queue payload without touching superglobals. */
    public static function fromServerPayload(array $payload): self
    {
        $request = new self();
        $request->server = [
            'REQUEST_METHOD' => strtoupper((string) ($payload['method'] ?? 'GET')),
            'REQUEST_URI' => (string) ($payload['uri'] ?? '/'),
            'REMOTE_ADDR' => (string) ($payload['client_ip'] ?? '127.0.0.1'),
        ];
        foreach (($payload['headers'] ?? []) as $name => $value) {
            $request->server['HTTP_' . str_replace('-', '_', strtoupper((string) $name))] = (string) $value;
        }
        $body = (string) ($payload['body'] ?? '');
        $contentType = strtolower((string) ($request->header('Content-Type') ?? ''));
        if ($body !== '' && str_contains($contentType, 'application/json')) {
            $decoded = json_decode($body, true);
            $request->json = is_array($decoded) ? $decoded : [];
            $request->body = $request->json;
        } elseif ($body !== '') {
            parse_str($body, $request->post);
            $request->body = $request->post;
        }
        return $request;
    }

    public function rawBody(): ?array
    {
        return $this->body;
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
