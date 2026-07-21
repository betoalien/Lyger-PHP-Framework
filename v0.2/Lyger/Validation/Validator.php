<?php

declare(strict_types=1);

namespace Lyger\Validation;

use Lyger\Http\Request;

/**
 * Validator - Inspired by Laravel's Validator
 * Provides powerful data validation with rules
 */
class Validator
{
    private array $data;
    private array $rules;
    private array $messages;
    private array $errors = [];
    private array $validated = [];

    public function __construct(array $data, array $rules, array $messages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
    }

    public static function make(array $data, array $rules, array $messages = []): self
    {
        return new self($data, $rules, $messages);
    }

    public function validate(): bool
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        $parts = explode(':', $rule);
        $ruleName = $parts[0];
        $ruleParams = $parts[1] ?? null;

        $method = 'validate' . ucfirst($ruleName);

        if (method_exists($this, $method)) {
            $result = $this->$method($field, $value, $ruleParams);
            if (!$result) {
                $this->addError($field, $rule);
            } else {
                $this->validated[$field] = $value;
            }
        }
    }

    private function addError(string $field, string $rule): void
    {
        $key = "{$field}.{$rule}";
        if (isset($this->messages[$key])) {
            $this->errors[$field][] = $this->messages[$key];
        } else {
            $this->errors[$field][] = $this->getDefaultMessage($field, $rule);
        }
    }

    private function getDefaultMessage(string $field, string $rule): string
    {
        $messages = [
            'required' => "The {$field} field is required.",
            'email' => "The {$field} must be a valid email address.",
            'min' => "The {$field} must be at least {$rule} characters.",
            'max' => "The {$field} must not exceed {$rule} characters.",
            'numeric' => "The {$field} must be a number.",
            'integer' => "The {$field} must be an integer.",
            'string' => "The {$field} must be a string.",
            'array' => "The {$field} must be an array.",
            'boolean' => "The {$field} must be true or false.",
            'in' => "The selected {$field} is invalid.",
            'url' => "The {$field} must be a valid URL.",
            'ip' => "The {$field} must be a valid IP address.",
            'alpha' => "The {$field} may only contain letters.",
            'alpha_num' => "The {$field} may only contain letters and numbers.",
            'date' => "The {$field} is not a valid date.",
            'confirmed' => "The {$field} confirmation does not match.",
            'unique' => "The {$field} has already been taken.",
            'exists' => "The selected {$field} is invalid.",
            'regex' => "The {$field} format is invalid.",
        ];

        $ruleName = explode(':', $rule)[0];
        return $messages[$ruleName] ?? "The {$field} is invalid.";
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    // Validation Rules

    private function validateRequired(string $field, mixed $value, ?string $param): bool
    {
        if (is_null($value)) {
            return false;
        }
        if (is_string($value) && trim($value) === '') {
            return false;
        }
        return true;
    }

    private function validateEmail(string $field, mixed $value, ?string $param): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function validateMin(string $field, mixed $value, ?string $param): bool
    {
        if (is_numeric($value)) {
            return $value >= (float) $param;
        }
        if (is_string($value)) {
            return mb_strlen($value) >= (int) $param;
        }
        if (is_array($value)) {
            return count($value) >= (int) $param;
        }
        return false;
    }

    private function validateMax(string $field, mixed $value, ?string $param): bool
    {
        if (is_numeric($value)) {
            return $value <= (float) $param;
        }
        if (is_string($value)) {
            return mb_strlen($value) <= (int) $param;
        }
        if (is_array($value)) {
            return count($value) <= (int) $param;
        }
        return false;
    }

    private function validateNumeric(string $field, mixed $value, ?string $param): bool
    {
        return is_numeric($value);
    }

    private function validateInteger(string $field, mixed $value, ?string $param): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    private function validateString(string $field, mixed $value, ?string $param): bool
    {
        return is_string($value);
    }

    private function validateArray(string $field, mixed $value, ?string $param): bool
    {
        return is_array($value);
    }

    private function validateBoolean(string $field, mixed $value, ?string $param): bool
    {
        return in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true);
    }

    private function validateIn(string $field, mixed $value, ?string $param): bool
    {
        $values = explode(',', $param);
        return in_array($value, $values, true);
    }

    private function validateUrl(string $field, mixed $value, ?string $param): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function validateIp(string $field, mixed $value, ?string $param): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    private function validateAlpha(string $field, mixed $value, ?string $param): bool
    {
        return preg_match('/^[a-zA-Z]+$/', $value) === 1;
    }

    private function validateAlphaNum(string $field, mixed $value, ?string $param): bool
    {
        return preg_match('/^[a-zA-Z0-9]+$/', $value) === 1;
    }

    private function validateDate(string $field, mixed $value, ?string $param): bool
    {
        $timestamp = strtotime($value);
        return $timestamp !== false;
    }

    private function validateRegex(string $field, mixed $value, ?string $param): bool
    {
        return preg_match($param, $value) === 1;
    }
}

/**
 * Form Request - Inspired by Laravel's Form Request
 */
abstract class FormRequest
{
    protected array $rules = [];
    protected array $messages = [];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->rules;
    }

    public function messages(): array
    {
        return $this->messages;
    }

    public function validate(): array
    {
        $data = Request::capture()->all();
        $validator = Validator::make($data, $this->rules(), $this->messages());

        if (!$validator->validate()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function validated(): array
    {
        return $this->validate();
    }

    public function fails(): bool
    {
        $data = Request::capture()->all();
        $validator = Validator::make($data, $this->rules(), $this->messages());
        return !$validator->validate();
    }

    public function errors(): array
    {
        $data = Request::capture()->all();
        $validator = Validator::make($data, $this->rules(), $this->messages());
        $validator->validate();
        return $validator->errors();
    }
}

class ValidationException extends \Exception
{
    private Validator $validator;

    public function __construct(Validator $validator)
    {
        parent::__construct('Validation failed');
        $this->validator = $validator;
    }

    public function getValidator(): Validator
    {
        return $this->validator;
    }

    public function getErrors(): array
    {
        return $this->validator->errors();
    }
}
