<?php

declare(strict_types=1);

namespace Lyger\Api;

/**
 * Resource - Transform models to JSON API responses
 */
abstract class Resource
{
    protected mixed $resource;

    public function __construct(mixed $resource)
    {
        $this->resource = $resource;
    }

    public function toArray(): array
    {
        // Override in subclass
        if (is_array($this->resource)) {
            return $this->resource;
        }
        if (is_object($this->resource) && method_exists($this->resource, 'toArray')) {
            return $this->resource->toArray();
        }
        return (array) $this->resource;
    }

    public static function collection(array $resources): array
    {
        return array_map(fn($resource) => (new static($resource))->toArray(), $resources);
    }
}

/**
 * JsonResource - JSON API response wrapper
 */
class JsonResource
{
    private array $data;
    private array $meta = [];
    private array $links = [];
    private ?array $included = [];

    public function __construct(mixed $data)
    {
        $this->data = $data;
    }

    public function meta(array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }

    public function link(string $key, string $url): self
    {
        $this->links[$key] = $url;
        return $this;
    }

    public function toArray(): array
    {
        $response = [];

        if (!empty($this->links)) {
            $response['links'] = $this->links;
        }

        if (!empty($this->meta)) {
            $response['meta'] = $this->meta;
        }

        $response['data'] = $this->transformData($this->data);

        if ($this->included !== null && !empty($this->included)) {
            $response['included'] = $this->included;
        }

        return $response;
    }

    private function transformData(mixed $data): mixed
    {
        if (is_array($data)) {
            return array_map(fn($item) => $this->transformItem($item), $data);
        }

        return $this->transformItem($data);
    }

    private function transformItem(mixed $item): mixed
    {
        if ($item instanceof Resource) {
            return $item->toArray();
        }

        if (is_array($item)) {
            return $item;
        }

        if (is_object($item) && method_exists($item, 'toArray')) {
            return $item->toArray();
        }

        return $item;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

/**
 * ApiResponse - Helper for API responses
 */
class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): array
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return $response;
    }

    public static function error(string $message, int $statusCode = 400, ?array $errors = null): array
    {
        $response = [
            'success' => false,
            'error' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return $response;
    }

    public static function notFound(string $message = 'Resource not found'): array
    {
        return self::error($message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): array
    {
        return self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): array
    {
        return self::error($message, 403);
    }

    public static function validationError(array $errors): array
    {
        return self::error('Validation failed', 422, $errors);
    }

    public static function created(mixed $data = null, string $message = 'Created successfully'): array
    {
        return self::success($data, $message, 201);
    }

    public static function deleted(string $message = 'Deleted successfully'): array
    {
        return self::success(null, $message);
    }

    public static function paginated(array $data, int $currentPage, int $perPage, int $total): array
    {
        return [
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
                'from' => ($currentPage - 1) * $perPage + 1,
                'to' => min($currentPage * $perPage, $total),
            ],
            'links' => [
                'self' => $_SERVER['REQUEST_URI'] ?? '',
                'first' => self::buildPageUrl(1),
                'last' => self::buildPageUrl((int) ceil($total / $perPage)),
                'next' => $currentPage < (int) ceil($total / $perPage) ? self::buildPageUrl($currentPage + 1) : null,
                'prev' => $currentPage > 1 ? self::buildPageUrl($currentPage - 1) : null,
            ],
        ];
    }

    private static function buildPageUrl(int $page): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $query = $_GET ?? [];
        $query['page'] = $page;
        return explode('?', $uri)[0] . '?' . http_build_query($query);
    }
}

/**
 * ApiController - Base controller for API endpoints
 */
abstract class ApiController
{
    protected function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): array
    {
        return ApiResponse::success($data, $message, $statusCode);
    }

    protected function error(string $message, int $statusCode = 400, ?array $errors = null): array
    {
        return ApiResponse::error($message, $statusCode, $errors);
    }

    protected function notFound(?string $message = null): array
    {
        return ApiResponse::notFound($message ?? 'Resource not found');
    }

    protected function unauthorized(?string $message = null): array
    {
        return ApiResponse::unauthorized($message ?? 'Unauthorized');
    }

    protected function forbidden(?string $message = null): array
    {
        return ApiResponse::forbidden($message ?? 'Forbidden');
    }

    protected function validationError(array $errors): array
    {
        return ApiResponse::validationError($errors);
    }

    protected function created(mixed $data = null, string $message = 'Created successfully'): array
    {
        return ApiResponse::created($data, $message);
    }

    protected function deleted(string $message = 'Deleted successfully'): array
    {
        return ApiResponse::deleted($message);
    }

    protected function paginated(array $data, int $currentPage, int $perPage, int $total): array
    {
        return ApiResponse::paginated($data, $currentPage, $perPage, $total);
    }
}
