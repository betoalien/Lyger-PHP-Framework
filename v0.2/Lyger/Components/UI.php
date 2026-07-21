<?php

declare(strict_types=1);

namespace Lyger\Components;

/**
 * FormComponent - Reusable form component
 */
abstract class FormComponent extends Component
{
    protected array $fields = [];
    protected array $values = [];
    protected array $errors = [];

    protected function mount(): void
    {
        $this->fields = $this->defineFields();
    }

    /**
     * Define form fields
     */
    protected function defineFields(): array
    {
        return [];
    }

    /**
     * Get field configuration
     */
    protected function getField(string $name): ?array
    {
        return $this->fields[$name] ?? null;
    }

    /**
     * Get field value
     */
    protected function value(string $name): mixed
    {
        return $this->values[$name] ?? $this->getField($name)['default'] ?? null;
    }

    /**
     * Set field value
     */
    protected function setValue(string $name, mixed $value): void
    {
        $this->values[$name] = $value;
    }

    /**
     * Get error for field
     */
    protected function error(string $name): ?string
    {
        return $this->errors[$name] ?? null;
    }

    /**
     * Has error
     */
    protected function hasError(string $name): bool
    {
        return isset($this->errors[$name]);
    }

    /**
     * Submit form
     */
    public function submit(): bool
    {
        try {
            $this->validate($this->getValidationRules());
            $this->handleSubmit();
            return true;
        } catch (ValidationException $e) {
            $this->errors = $e->getErrors();
            return false;
        }
    }

    /**
     * Get validation rules
     */
    protected function getValidationRules(): array
    {
        $rules = [];
        foreach ($this->fields as $name => $field) {
            if (isset($field['rules'])) {
                $rules[$name] = $field['rules'];
            }
        }
        return $rules;
    }

    /**
     * Handle form submission
     */
    protected function handleSubmit(): void
    {
        // Override in subclass
    }

    /**
     * Render form fields
     */
    public function renderFields(): string
    {
        $html = '';
        foreach ($this->fields as $name => $field) {
            $html .= $this->renderField($name, $field);
        }
        return $html;
    }

    /**
     * Render single field
     */
    protected function renderField(string $name, array $field): string
    {
        $type = $field['type'] ?? 'text';
        $label = $field['label'] ?? ucfirst($name);
        $placeholder = $field['placeholder'] ?? '';
        $value = $this->value($name);
        $error = $this->error($name);

        $errorClass = $error ? 'border-red-500' : 'border-gray-300';
        $errorHtml = $error ? "<p class=\"text-red-500 text-sm mt-1\">{$error}</p>" : '';

        $options = '';
        if ($type === 'select' && isset($field['options'])) {
            foreach ($field['options'] as $optValue => $optLabel) {
                $selected = $value === $optValue ? 'selected' : '';
                $options .= "<option value=\"{$optValue}\" {$selected}>{$optLabel}</option>";
            }
        }

        $input = match($type) {
            'textarea' => "<textarea name=\"{$name}\" class=\"w-full px-3 py-2 border {$errorClass} rounded-lg focus:ring-2 focus:ring-blue-500\" placeholder=\"{$placeholder}\">{$value}</textarea>",
            'select' => "<select name=\"{$name}\" class=\"w-full px-3 py-2 border {$errorClass} rounded-lg focus:ring-2 focus:ring-blue-500\">{$options}</select>",
            'checkbox' => "<input type=\"checkbox\" name=\"{$name}\" value=\"1\" " . ($value ? 'checked' : '') . " class=\"rounded\">",
            'file' => "<input type=\"file\" name=\"{$name}\" class=\"w-full px-3 py-2 border {$errorClass} rounded-lg\">",
            default => "<input type=\"{$type}\" name=\"{$name}\" value=\"{$value}\" class=\"w-full px-3 py-2 border {$errorClass} rounded-lg focus:ring-2 focus:ring-blue-500\" placeholder=\"{$placeholder}\">",
        };

        return <<<HTML
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">{$label}</label>
            {$input}
            {$errorHtml}
        </div>
        HTML;
    }
}

/**
 * ModalComponent - Reusable modal component
 */
class Modal extends Component
{
    protected string $title = '';
    protected bool $isOpen = false;
    protected string $size = 'md'; // sm, md, lg, xl

    protected function mount(): void
    {
        $this->title = $this->title ?? 'Modal';
    }

    public function open(): void
    {
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function toggle(): void
    {
        $this->isOpen = !$this->isOpen;
    }

    protected function renderInline(): string
    {
        if (!$this->isOpen) {
            return '';
        }

        $sizeClasses = match($this->size) {
            'sm' => 'max-w-md',
            'md' => 'max-w-lg',
            'lg' => 'max-w-2xl',
            'xl' => 'max-w-4xl',
            'full' => 'max-w-full',
            default => 'max-w-lg',
        };

        return <<<HTML
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" onclick="closeModal()"></div>
                <div class="{$sizeClasses} mx-auto bg-white rounded-lg shadow-xl">
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-lg font-semibold">{$this->title}</h3>
                    </div>
                    <div class="px-6 py-4">
                        <div class="modal-content">{{ \$slot }}</div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 border-t flex justify-end gap-2">
                        <button onclick="closeModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">Cancel</button>
                        <button onclick="submitModal()" class="px-4 py-2 text-white bg-blue-500 rounded-lg hover:bg-blue-600">Confirm</button>
                    </div>
                </div>
            </div>
        </div>
        HTML;
    }
}

/**
 * TableComponent - Reusable table component
 */
class Table extends Component
{
    protected array $columns = [];
    protected array $data = [];
    protected array $actions = [];
    protected string $sortBy = '';
    protected string $sortDir = 'asc';

    protected function mount(): void
    {
        $this->columns = $this->defineColumns();
        $this->data = $this->getData();
    }

    /**
     * Define table columns
     */
    protected function defineColumns(): array
    {
        return [];
    }

    /**
     * Get table data
     */
    protected function getData(): array
    {
        return [];
    }

    /**
     * Sort data
     */
    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }

        usort($this->data, function($a, $b) use ($column) {
            $aVal = is_array($a) ? ($a[$column] ?? '') : ($a->$column ?? '');
            $bVal = is_array($b) ? ($b[$column] ?? '') : ($b->$column ?? '');
            return $this->sortDir === 'asc' ? $aVal <=> $bVal : $bVal <=> $aVal;
        });
    }

    /**
     * Render table
     */
    protected function renderInline(): string
    {
        $headers = '';
        foreach ($this->columns as $key => $column) {
            $label = $column['label'] ?? ucfirst($key);
            $sortable = $column['sortable'] ?? false;
            $sortIcon = '';

            if ($sortable && $this->sortBy === $key) {
                $sortIcon = $this->sortDir === 'asc' ? ' ↑' : ' ↓';
            }

            $headers .= "<th class=\"px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider\">{$label}{$sortIcon}</th>";
        }

        if (!empty($this->actions)) {
            $headers .= "<th class=\"px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase\">Actions</th>";
        }

        $rows = '';
        foreach ($this->data as $row) {
            $cells = '';
            foreach ($this->columns as $key => $column) {
                $value = is_array($row) ? ($row[$key] ?? '') : ($row->$key ?? '');

                if (isset($column['format'])) {
                    $value = ($column['format'])($value, $row);
                }

                $cells .= "<td class=\"px-4 py-4 whitespace-nowrap\">{$value}</td>";
            }

            if (!empty($this->actions)) {
                $actionsHtml = '';
                foreach ($this->actions as $action) {
                    $label = $action['label'] ?? 'Action';
                    $url = is_callable($action['url']) ? ($action['url'])($row) : $action['url'];
                    $class = $action['class'] ?? 'text-blue-600 hover:text-blue-900';
                    $actionsHtml .= "<a href=\"{$url}\" class=\"{$class} mr-3\">{$label}</a>";
                }
                $cells .= "<td class=\"px-4 py-4 whitespace-nowrap text-right\">{$actionsHtml}</td>";
            }

            $rows .= "<tr class=\"hover:bg-gray-50\">{$cells}</tr>";
        }

        return <<<HTML
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>{$headers}</tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    {$rows}
                </tbody>
            </table>
        </div>
        HTML;
    }
}

/**
 * AlertComponent - Reusable alert component
 */
class Alert extends Component
{
    protected string $type = 'info'; // info, success, warning, error
    protected string $message = '';
    protected bool $dismissible = false;

    protected function mount(): void
    {
        $this->message = $this->message ?? '';
    }

    protected function renderInline(): string
    {
        $colors = match($this->type) {
            'success' => 'bg-green-50 border-green-200 text-green-800',
            'warning' => 'bg-yellow-50 border-yellow-200 text-yellow-800',
            'error' => 'bg-red-50 border-red-200 text-red-800',
            default => 'bg-blue-50 border-blue-200 text-blue-800',
        };

        $icons = match($this->type) {
            'success' => '✓',
            'warning' => '⚠',
            'error' => '✕',
            default => 'ℹ',
        };

        $dismissBtn = $this->dismissible
            ? '<button onclick="this.parentElement.remove()" class="ml-auto -mx-1.5 -my-1.5 p-1.5 rounded-lg focus:ring-2 inline-flex h-8 w-8">&times;</button>'
            : '';

        return <<<HTML
        <div class="border-l-4 p-4 rounded {$colors} flex items-start" role="alert">
            <span class="mr-3">{$icons}</span>
            <div class="flex-1">{$this->message}</div>
            {$dismissBtn}
        </div>
        HTML;
    }
}
