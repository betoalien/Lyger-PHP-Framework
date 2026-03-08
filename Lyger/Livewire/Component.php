<?php

declare(strict_types=1);

namespace Lyger\Livewire;

/**
 * Livewire - Server-side reactive components
 *
 * Full implementation of Livewire-like functionality
 */

use Lyger\Http\Request;
use Lyger\Http\Response;

/**
 * Component - Base Livewire component
 */
abstract class Component
{
    protected array $properties = [];
    protected array $updates = [];
    protected Request $request;
    protected array $errors = [];
    protected bool $mounting = false;

    public function __construct()
    {
        $this->request = Request::capture();
    }

    /**
     * Mount the component (called when component is first created)
     */
    public function mount(...$params): void
    {
        // Override in subclass
    }

    /**
     * Render the component
     */
    abstract public function render(): string;

    /**
     * Get component data for JavaScript
     */
    public function getPublicProperties(): array
    {
        $properties = [];
        $reflector = new \ReflectionClass($this);

        foreach ($reflector->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic()) {
                $name = $property->getName();
                $properties[$name] = $this->$name;
            }
        }

        return $properties;
    }

    /**
     * Get the component signature
     */
    public function getSignature(): string
    {
        return strtolower((new \ReflectionClass($this))->getShortName());
    }

    /**
     * Call a method on the component
     */
    public function callMethod(string $method, array $params = []): array
    {
        if (!method_exists($this, $method)) {
            return [
                'effect' => [],
                'error' => "Method {$method} does not exist",
            ];
        }

        try {
            $result = $this->$method(...$params);

            return [
                'effect' => $this->getEffects(),
                'data' => $result,
            ];
        } catch (\Throwable $e) {
            return [
                'effect' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get effects (data updates, redirects, etc.)
     */
    protected function getEffects(): array
    {
        return [
            'properties' => $this->updates,
        ];
    }

    /**
     * Validate the given fields
     */
    protected function validate(array $rules, array $messages = []): array
    {
        $data = $this->all();
        $validator = new \Lyger\Validation\Validator($data, $rules, $messages);

        if ($validator->fails()) {
            $this->errors = $validator->errors();
            throw new ValidationException($validator->errors());
        }

        return $validator->validated();
    }

    /**
     * Add an error message
     */
    protected function addError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }

    /**
     * Get all component data
     */
    protected function all(): array
    {
        return array_merge($this->getPublicProperties(), $this->request->all());
    }

    /**
     * Get a specific property
     */
    protected function get(string $key, $default = null)
    {
        return $this->properties[$key] ?? $default;
    }

    /**
     * Set a property
     */
    protected function set(string $key, $value): void
    {
        $this->properties[$key] = $value;
        $this->updates[$key] = $value;
    }

    /**
     * Emit an event
     */
    protected function emit(string $event, ...$params): array
    {
        return [
            'event' => $event,
            'params' => $params,
        ];
    }

    /**
     * Emit to parent
     */
    protected function emitUp(string $event, ...$params): array
    {
        return [
            'event' => $event,
            'params' => $params,
            'target' => 'parent',
        ];
    }

    /**
     * Dispatch browser event
     */
    protected function dispatchBrowserEvent(string $event, array $data = []): array
    {
        return [
            'event' => $event,
            'data' => $data,
        ];
    }

    /**
     * Refresh the component
     */
    protected function refresh(): array
    {
        return [
            'effect' => [
                'refresh' => true,
            ],
        ];
    }

    /**
     * Redirect to URL
     */
    protected function redirect(string $url): array
    {
        return [
            'redirect' => $url,
        ];
    }

    /**
     * Redirect to route
     */
    protected function redirectToRoute(string $route, array $params = []): array
    {
        // Simple route redirect
        $url = $route;
        foreach ($params as $key => $value) {
            $url = str_replace("{{$key}}", $value, $url);
        }
        return $this->redirect($url);
    }

    /**
     * File upload handler
     */
    protected function validateFile(string $field, array $rules): array
    {
        $file = $this->request->file($field);

        if (!$file) {
            throw new ValidationException([$field => "File {$field} is required"]);
        }

        return $this->validate([$field => $rules]);
    }

    /**
     * Reset properties
     */
    protected function reset(...$properties): void
    {
        if (empty($properties)) {
            $this->properties = [];
        } else {
            foreach ($properties as $prop) {
                unset($this->properties[$prop]);
            }
        }
    }

    /**
     * Get current user (if authenticated)
     */
    protected function user(): ?array
    {
        $authHeader = $this->request->header('Authorization');

        if (!$authHeader) {
            return null;
        }

        $token = str_replace('Bearer ', '', $authHeader);
        $decoded = json_decode(base64_decode($token), true);

        if (!$decoded || ($decoded['exp'] ?? 0) < time()) {
            return null;
        }

        return $decoded;
    }

    /**
     * Check if user is authenticated
     */
    protected function isAuthenticated(): bool
    {
        return $this->user() !== null;
    }
}

/**
 * ValidationException - Thrown when validation fails
 */
class ValidationException extends \Exception
{
    private array $errors;

    public function __construct(array $errors)
    {
        parent::__construct('Validation failed');
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

/**
 * Manager - Manages Livewire components
 */
class Manager
{
    private static array $components = [];

    /**
     * Register a component
     */
    public static function register(string $name, string $class): void
    {
        self::$components[$name] = $class;
    }

    /**
     * Get a component instance
     */
    public static function get(string $name): ?Component
    {
        if (!isset(self::$components[$name])) {
            return null;
        }

        return new self::$components[$name]();
    }

    /**
     * Handle an incoming Livewire request
     */
    public static function handle(Request $request): Response
    {
        $action = $request->input('action');
        $component = $request->input('component');
        $params = $request->input('params', []);
        $propertyUpdates = $request->input('properties', []);

        $instance = self::get($component);

        if (!$instance) {
            return Response::json(['error' => 'Component not found'], 404);
        }

        // Apply property updates
        foreach ($propertyUpdates as $key => $value) {
            $instance->$key = $value;
        }

        // Execute action
        if ($action === 'render') {
            $html = $instance->render();
            return Response::json([
                'html' => $html,
                'data' => $instance->getPublicProperties(),
            ]);
        }

        if ($action && method_exists($instance, $action)) {
            $result = $instance->callMethod($action, $params);

            if (isset($result['error'])) {
                return Response::json($result, 422);
            }

            if (isset($result['redirect'])) {
                return Response::json([
                    'redirect' => $result['redirect'],
                ]);
            }

            return Response::json([
                'data' => $result['data'] ?? null,
                'effects' => $result['effect'] ?? [],
                'properties' => $instance->getPublicProperties(),
            ]);
        }

        return Response::json(['error' => 'Invalid action'], 400);
    }
}

/**
 * Traits - Reusable component traits
 */
trait WithEvents
{
    protected array $listeners = [];

    public function listeners(): array
    {
        return $this->listeners;
    }

    protected function listen(string $event, callable $callback): void
    {
        $this->listeners[$event] = $callback;
    }
}

trait WithFileUploads
{
    protected function upload(string $field, $file): string
    {
        // Simple file upload handling
        $filename = uniqid() . '_' . $file['name'];
        $path = base_path('public/uploads/' . $filename);

        // Ensure directory exists
        if (!is_dir(base_path('public/uploads'))) {
            mkdir(base_path('public/uploads'), 0755, true);
        }

        move_uploaded_file($file['tmp_name'], $path);

        return '/uploads/' . $filename;
    }
}

trait WithPagination
{
    protected int $perPage = 15;
    protected int $page = 1;

    public function pagination(): array
    {
        return [
            'per_page' => $this->perPage,
            'page' => $this->page,
        ];
    }

    protected function nextPage(): void
    {
        $this->page++;
    }

    protected function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    protected function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
    }
}

trait WithSearch
{
    protected string $search = '';
    protected array $searchable = [];

    public function updatingSearch(): void
    {
        $this->page = 1;
    }

    protected function applySearch($query)
    {
        if (empty($this->search) || empty($this->searchable)) {
            return $query;
        }

        foreach ($this->searchable as $field) {
            $query->orWhere($field, 'like', "%{$this->search}%");
        }

        return $query;
    }
}
