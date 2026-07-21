<?php

declare(strict_types=1);

namespace Lyger\Container;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionException;

final class Container
{
    private static ?self $instance = null;
    private array $bindings = [];
    private array $singletons = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function bind(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    public function singleton(string $abstract, object $instance): void
    {
        $this->singletons[$abstract] = $instance;
    }

    public function make(string $abstract): object
    {
        if (isset($this->singletons[$abstract])) {
            return $this->singletons[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $instance = ($this->bindings[$abstract])($this);
            if ($instance instanceof $abstract) {
                $this->singletons[$abstract] = $instance;
            }
            return $instance;
        }

        return $this->resolve($abstract);
    }

    private function resolve(string $abstract): object
    {
        if (!class_exists($abstract)) {
            throw new \RuntimeException("Class {$abstract} does not exist");
        }

        $reflector = new ReflectionClass($abstract);

        if (!$reflector->isInstantiable()) {
            throw new \RuntimeException("Class {$abstract} is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $abstract();
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type === null || !$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                    continue;
                }
                throw new \RuntimeException(
                    "Cannot resolve dependency: {$parameter->getName()}"
                );
            }

            $dependencies[] = $this->make($type->getName());
        }

        return $reflector->newInstanceArgs($dependencies);
    }
}
