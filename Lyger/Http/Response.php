<?php

declare(strict_types=1);

namespace Lyger\Http;

final class Response
{
    private int $statusCode;
    private array $headers;
    private string $content;

    public function __construct(mixed $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->content = $this->formatContent($content);
    }

    private function formatContent(mixed $content): string
    {
        if (is_array($content)) {
            return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_string($content)) {
            return $content;
        }

        return (string) $content;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}");
        }

        if (!isset($this->headers['Content-Type'])) {
            if ($this->isJson($this->content)) {
                header('Content-Type: application/json; charset=utf-8');
            } else {
                header('Content-Type: text/html; charset=utf-8');
            }
        }

        echo $this->content;
    }

    private function isJson(string $content): bool
    {
        json_decode($content);
        return json_last_error() === JSON_ERROR_NONE;
    }

    public static function json(mixed $data, int $statusCode = 200): self
    {
        return new self($data, $statusCode, ['Content-Type' => 'application/json']);
    }

    public static function html(string $html, int $statusCode = 200): self
    {
        return new self($html, $statusCode, ['Content-Type' => 'text/html']);
    }

    public static function text(string $text, int $statusCode = 200): self
    {
        return new self($text, $statusCode, ['Content-Type' => 'text/plain']);
    }

    public static function error(string $message, int $statusCode = 500): self
    {
        return self::json(['error' => $message], $statusCode);
    }

    public function setHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeader(string $key, ?string $default = null): ?string
    {
        return $this->headers[$key] ?? $default;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
