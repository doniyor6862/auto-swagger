<?php

namespace Laravel\AutoSwagger\Services;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class RequestParser
{
    /**
     * Map of Laravel validation rules to OpenAPI types
     */
    protected array $typeMap = [
        'integer' => ['type' => 'integer'],
        'numeric' => ['type' => 'number'],
        'string' => ['type' => 'string'],
        'boolean' => ['type' => 'boolean'],
        'array' => ['type' => 'array'],
        'json' => ['type' => 'object'],
        'date' => ['type' => 'string', 'format' => 'date'],
        'date_format' => ['type' => 'string', 'format' => 'date-time'],
        'email' => ['type' => 'string', 'format' => 'email'],
        'url' => ['type' => 'string', 'format' => 'uri'],
        'ip' => ['type' => 'string', 'format' => 'ipv4'],
        'uuid' => ['type' => 'string', 'format' => 'uuid'],
        'file' => ['type' => 'string', 'format' => 'binary'],
        'image' => ['type' => 'string', 'format' => 'binary'],
    ];

    /**
     * Extract validation rules from a FormRequest class
     */
    public function parseRequest(string $requestClass): array
    {
        if (!class_exists($requestClass)) {
            return [];
        }

        // Check if the class extends FormRequest
        if (!is_subclass_of($requestClass, FormRequest::class)) {
            return [];
        }

        // Get rules from the class
        $reflectionClass = new ReflectionClass($requestClass);
        
        // Try to find the rules method
        if (!$reflectionClass->hasMethod('rules')) {
            return [];
        }

        $rulesMethod = $reflectionClass->getMethod('rules');
        $rulesMethod->setAccessible(true);
        
        // Create a temporary instance to call the rules method
        $request = $this->createRequestInstance($requestClass);
        
        if (!$request) {
            return [];
        }

        $rules = $rulesMethod->invoke($request);
        
        if (!is_array($rules)) {
            return [];
        }

        return $this->convertRulesToOpenApi($rules);
    }

    /**
     * Create a temporary instance of the request class
     */
    protected function createRequestInstance(string $requestClass): ?FormRequest
    {
        try {
            return app()->make($requestClass);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Convert Laravel validation rules to OpenAPI schema properties
     */
    protected function convertRulesToOpenApi(array $rules): array
    {
        $properties = [];
        $required = [];

        foreach ($rules as $field => $fieldRules) {
            // Handle dot notation for nested properties
            $isNested = Str::contains($field, '.');
            
            if ($isNested) {
                // Skip processing nested fields for now - could be added in future
                continue;
            }

            // Convert rule to array if it's a string
            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            $property = $this->processFieldRules($field, $fieldRules);
            
            // Add to required fields if the required rule is present
            if ($property['required']) {
                $required[] = $field;
                unset($property['required']);
            }

            $properties[$field] = $property;
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * Process field rules to determine OpenAPI property type
     */
    protected function processFieldRules(string $field, array $rules): array
    {
        $property = [
            'type' => 'string', // Default type
            'description' => Str::title(str_replace('_', ' ', $field)),
            'required' => false,
        ];

        $typeSet = false;
        $enumValues = null;

        foreach ($rules as $rule) {
            // Handle array syntax
            if (is_array($rule)) {
                continue;
            }

            // Handle object syntax for rules
            if (is_object($rule)) {
                $rule = get_class($rule);
            }

            $rule = trim($rule);

            // Handle required rule
            if ($rule === 'required') {
                $property['required'] = true;
                continue;
            }

            // Handle nullable rule
            if ($rule === 'nullable') {
                $property['nullable'] = true;
                continue;
            }

            // Handle rules with parameters (min:x, max:y, etc.)
            if (Str::contains($rule, ':')) {
                [$ruleName, $ruleParams] = explode(':', $rule, 2);
                $params = explode(',', $ruleParams);

                switch ($ruleName) {
                    case 'min':
                        if (isset($property['type']) && $property['type'] === 'string') {
                            $property['minLength'] = (int) $params[0];
                        } elseif (isset($property['type']) && ($property['type'] === 'integer' || $property['type'] === 'number')) {
                            $property['minimum'] = (int) $params[0];
                        }
                        break;

                    case 'max':
                        if (isset($property['type']) && $property['type'] === 'string') {
                            $property['maxLength'] = (int) $params[0];
                        } elseif (isset($property['type']) && ($property['type'] === 'integer' || $property['type'] === 'number')) {
                            $property['maximum'] = (int) $params[0];
                        }
                        break;

                    case 'size':
                        if (isset($property['type']) && $property['type'] === 'string') {
                            $property['minLength'] = $property['maxLength'] = (int) $params[0];
                        }
                        break;

                    case 'in':
                        $enumValues = $params;
                        break;

                    case 'date_format':
                        $property['type'] = 'string';
                        $property['format'] = 'date-time';
                        $property['example'] = date($params[0]);
                        $typeSet = true;
                        break;
                }
                
                continue;
            }

            // Handle basic type rules
            if (isset($this->typeMap[$rule])) {
                $type = $this->typeMap[$rule];
                $property['type'] = $type['type'];
                
                if (isset($type['format'])) {
                    $property['format'] = $type['format'];
                }
                
                $typeSet = true;
            }
        }

        // Apply enum values if found
        if ($enumValues !== null) {
            $property['enum'] = $enumValues;
        }

        // Generate example value based on type
        if (!isset($property['example'])) {
            $property['example'] = $this->generateExampleByType($property);
        }

        return $property;
    }

    /**
     * Generate example values based on property type
     */
    protected function generateExampleByType(array $property): mixed
    {
        $type = $property['type'] ?? 'string';
        $format = $property['format'] ?? null;

        switch ($type) {
            case 'integer':
                return 1;
            case 'number':
                return 1.23;
            case 'boolean':
                return true;
            case 'array':
                return [];
            case 'object':
                return new \stdClass();
            case 'string':
                if ($format === 'date') {
                    return date('Y-m-d');
                }
                if ($format === 'date-time') {
                    return date('Y-m-d H:i:s');
                }
                if ($format === 'email') {
                    return 'user@example.com';
                }
                if ($format === 'uri') {
                    return 'https://example.com';
                }
                if ($format === 'uuid') {
                    return '550e8400-e29b-41d4-a716-446655440000';
                }
                if ($format === 'binary') {
                    return '(binary)';
                }
                return 'string';
            default:
                return null;
        }
    }
}
