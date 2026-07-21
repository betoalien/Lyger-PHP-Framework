<?php

declare(strict_types=1);

namespace Lyger\Components;

/**
 * Component - Base class for Livewire-like components
 */
abstract class Component
{
    protected array $properties = [];
    protected array $updates = [];
    protected string $view = '';
    protected string $layout = 'default';

    public function __construct()
    {
        $this->mount();
    }

    /**
     * Called when component is mounted
     */
    protected function mount(): void
    {
        // Override in subclass
    }

    /**
     * Called before each render
     */
    protected function beforeRender(): void
    {
        // Override in subclass
    }

    /**
     * Render the component view
     */
    public function render(): string
    {
        $this->beforeRender();
        return $this->renderView();
    }

    /**
     * Get component data as array
     */
    public function toArray(): array
    {
        return [
            'properties' => $this->properties,
            'view' => $this->render(),
        ];
    }

    /**
     * Get view name
     */
    public function getView(): string
    {
        return $this->view;
    }

    /**
     * Set view name
     */
    public function view(string $view): self
    {
        $this->view = $view;
        return $this;
    }

    /**
     * Render view from string
     */
    protected function renderView(): string
    {
        $viewName = $this->view ?: $this->getDefaultViewName();

        // Check if view file exists
        $viewPath = base_path("resources/views/{$viewName}.php");

        if (file_exists($viewPath)) {
            extract($this->properties);
            ob_start();
            include $viewPath;
            return ob_get_clean();
        }

        // Default inline view
        return $this->renderInline();
    }

    /**
     * Render inline template
     */
    protected function renderInline(): string
    {
        return '<div>{{$slot}}</div>';
    }

    /**
     * Get default view name from class name
     */
    protected function getDefaultViewName(): string
    {
        $class = (new \ReflectionClass($this))->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-', $class));
    }

    /**
     * Get property
     */
    public function __get(string $key): mixed
    {
        return $this->properties[$key] ?? null;
    }

    /**
     * Set property
     */
    public function __set(string $key, mixed $value): void
    {
        $this->properties[$key] = $value;
        $this->updates[$key] = $value;
    }

    /**
     * Check if property is set
     */
    public function __isset(string $key): bool
    {
        return isset($this->properties[$key]);
    }

    /**
     * Call a method on the component
     */
    public function call(string $method, array $params = []): array
    {
        if (!method_exists($this, $method)) {
            return ['error' => "Method {$method} not found"];
        }

        try {
            $result = $this->$method(...$params);

            return [
                'success' => true,
                'data' => $result,
                'updates' => $this->updates,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Emit an event
     */
    protected function emit(string $event, mixed $data = null): array
    {
        return [
            'event' => $event,
            'data' => $data,
        ];
    }

    /**
     * Validate form data
     */
    protected function validate(array $rules, array $messages = []): array
    {
        $validator = new \Lyger\Validation\Validator($this->properties, $rules, $messages);

        if ($validator->fails()) {
            throw new \ValidationException($validator->errors());
        }

        return $validator->validated();
    }

    /**
     * Add property
     */
    protected function setProperty(string $key, mixed $value): void
    {
        $this->properties[$key] = $value;
    }

    /**
     * Get property
     */
    protected function getProperty(string $key): mixed
    {
        return $this->properties[$key] ?? null;
    }

    /**
     * Refresh component
     */
    public function refresh(): array
    {
        return $this->toArray();
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
 * ComponentManager - Manages component lifecycle
 */
class ComponentManager
{
    private static array $components = [];

    public static function register(string $name, string $class): void
    {
        self::$components[$name] = $class;
    }

    public static function make(string $name): Component
    {
        if (!isset(self::$components[$name])) {
            throw new \RuntimeException("Component {$name} not registered");
        }

        return new self::$components[$name]();
    }

    public static function getRegistered(): array
    {
        return self::$components;
    }
}

/**
 * Slot - Represents a slot in a component
 */
class Slot
{
    private string $content;

    public function __construct(string $content = '')
    {
        $this->content = $content;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}

/**
 * LiveHandler - Handles Livewire AJAX requests
 */
class LiveHandler
{
    private Component $component;

    public function __construct(Component $component)
    {
        $this->component = $component;
    }

    /**
     * Handle an AJAX request from the browser
     */
    public static function handle(array $request): array
    {
        $componentName = $request['component'] ?? '';
        $method = $request['method'] ?? 'render';
        $params = $request['params'] ?? [];
        $properties = $request['properties'] ?? [];

        $component = ComponentManager::make($componentName);

        // Set properties from request
        foreach ($properties as $key => $value) {
            $component->$key = $value;
        }

        // Call method
        return $component->call($method, $params);
    }
}
