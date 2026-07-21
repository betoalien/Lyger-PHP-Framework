<?php

declare(strict_types=1);

namespace Lyger\Routing;

use Attribute;

/**
 * RouteAttribute Attribute - PHP 8 attribute for route definition
 * Enables auto-documentation generation
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class RouteAttributeAttribute
{
    public string $method;
    public string $path;
    public string $name;
    public array $middleware;
    public array $tags;
    public string $summary;
    public string $description;
    public array $parameters;
    public array $responses;

    public function __construct(
        string $method = 'GET',
        string $path = '/',
        string $name = '',
        array $middleware = [],
        array $tags = [],
        string $summary = '',
        string $description = '',
        array $parameters = [],
        array $responses = []
    ) {
        $this->method = $method;
        $this->path = $path;
        $this->name = $name;
        $this->middleware = $middleware;
        $this->tags = $tags;
        $this->summary = $summary;
        $this->description = $description;
        $this->parameters = $parameters;
        $this->responses = $responses;
    }
}

/**
 * OpenAPI Documentation Generator
 * Generates OpenAPI 3.0 schema from route attributes
 */
class OpenApiGenerator
{
    private array $routes = [];
    private array $info;

    public function __construct()
    {
        $this->info = [
            'title' => 'Lyger API',
            'version' => '1.0.0',
            'description' => 'Auto-generated API documentation',
        ];
    }

    public function setInfo(string $title, string $version, string $description = ''): self
    {
        $this->info = [
            'title' => $title,
            'version' => $version,
            'description' => $description,
        ];
        return $this;
    }

    public function addRouteAttribute(\ReflectionMethod $method, string $path, string $methodType): void
    {
        $attributes = $method->getAttributes(RouteAttributeAttribute::class);

        if (!empty($attributes)) {
            $attr = $attributes[0]->newInstance();
            $this->routes[] = [
                'path' => $attr->path,
                'method' => strtolower($attr->method),
                'name' => $attr->name,
                'summary' => $attr->summary,
                'description' => $attr->description,
                'tags' => $attr->tags,
                'parameters' => $attr->parameters,
                'responses' => $attr->responses,
                'middleware' => $attr->middleware,
            ];
        } else {
            // Fallback to basic route from method name
            $this->routes[] = [
                'path' => $path,
                'method' => strtolower($methodType),
                'name' => $method->getName(),
                'summary' => '',
                'description' => '',
                'tags' => [],
                'parameters' => [],
                'responses' => [],
                'middleware' => [],
            ];
        }
    }

    public function generate(): array
    {
        $paths = [];

        foreach ($this->routes as $route) {
            $path = $route['path'];
            $method = $route['method'];

            if (!isset($paths[$path])) {
                $paths[$path] = [];
            }

            $operation = [
                'summary' => $route['summary'],
                'description' => $route['description'],
                'tags' => $route['tags'],
                'responses' => $this->buildResponses($route['responses']),
                'x-middleware' => $route['middleware'],
            ];

            // Add parameters
            if (!empty($route['parameters'])) {
                $operation['parameters'] = $route['parameters'];
            }

            $paths[$path][$method] = $operation;
        }

        return [
            'openapi' => '3.0.0',
            'info' => $this->info,
            'paths' => $paths,
            'components' => [
                'schemas' => $this->generateSchemas(),
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        ' bearerFormat' => 'JWT',
                    ],
                    'apiKey' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-API-Key',
                    ],
                ],
            ],
        ];
    }

    private function buildResponses(array $responses): array
    {
        $output = [
            '200' => [
                'description' => 'Successful response',
            ],
            '400' => [
                'description' => 'Bad request',
            ],
            '401' => [
                'description' => 'Unauthorized',
            ],
            '404' => [
                'description' => 'Not found',
            ],
            '500' => [
                'description' => 'Internal server error',
            ],
        ];

        foreach ($responses as $code => $response) {
            $output[$code] = [
                'description' => $response['description'] ?? '',
                'content' => $response['content'] ?? [],
            ];
        }

        return $output;
    }

    private function generateSchemas(): array
    {
        return [
            'Error' => [
                'type' => 'object',
                'properties' => [
                    'error' => [
                        'type' => 'string',
                        'description' => 'Error message',
                    ],
                ],
            ],
            'Success' => [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'type' => 'object',
                        'description' => 'Response data',
                    ],
                ],
            ],
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->generate(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function save(string $path): void
    {
        file_put_contents($path, $this->toJson());
    }
}
