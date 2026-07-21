<?php

declare(strict_types=1);

namespace Lyger\Testing;

use Lyger\Database\Model;

/**
 * Factory - Creates model instances for testing
 */
class Factory
{
    private string $modelClass;
    private array $definitions = [];
    private int $times = 1;

    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
    }

    public static function of(string $modelClass): self
    {
        return new self($modelClass);
    }

    public function define(callable $callback): self
    {
        $this->definitions['default'] = $callback;
        return $this;
    }

    public function times(int $count): self
    {
        $this->times = $count;
        return $this;
    }

    public function make(array $overrides = []): Model|array
    {
        $results = [];

        for ($i = 0; $i < $this->times; $i++) {
            $attributes = [];

            if (isset($this->definitions['default'])) {
                $attributes = ($this->definitions['default'])($i);
            }

            $attributes = array_merge($attributes, $overrides);

            if ($this->times === 1) {
                return new $this->modelClass($attributes);
            }

            $results[] = new $this->modelClass($attributes);
        }

        return $results;
    }

    public function create(array $overrides = []): Model|array
    {
        $model = $this->make($overrides);

        if (is_array($model)) {
            foreach ($model as $m) {
                $m->save();
            }
            return $model;
        }

        $model->save();
        return $model;
    }
}
