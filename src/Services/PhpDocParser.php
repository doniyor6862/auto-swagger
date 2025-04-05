<?php

namespace Laravel\AutoSwagger\Services;

use ReflectionClass;
use ReflectionProperty;

class PhpDocParser
{
    /**
     * Parse PHPDoc comments for a model class
     */
    public function parseModelDocBlock(string $className): array
    {
        if (!class_exists($className)) {
            return [];
        }

        $reflectionClass = new ReflectionClass($className);
        $docComment = $reflectionClass->getDocComment();
        
        $schema = [
            'type' => 'object',
            'properties' => [],
        ];
        
        // Get class description from PHPDoc
        if ($docComment) {
            $description = $this->extractDescription($docComment);
            if ($description) {
                $schema['description'] = $description;
            }
        }
        
        // Get properties from reflection and parse their PHPDoc comments
        $properties = $reflectionClass->getProperties();
        foreach ($properties as $property) {
            $propertySchema = $this->parsePropertyDocBlock($property);
            if (!empty($propertySchema)) {
                $propertyName = $property->getName();
                $schema['properties'][$propertyName] = $propertySchema;
            }
        }

        // Parse @property annotations
        $this->parsePropertyAnnotations($docComment, $schema['properties']);
        
        return $schema;
    }

    /**
     * Extract description from PHPDoc comment
     */
    protected function extractDescription(string $docComment): string
    {
        $docComment = preg_replace('/^\s*\/\*\*\s*|^\s*\*\s*|\s*\*\/\s*$/m', '', $docComment);
        
        // Remove @tags
        $lines = preg_split('/\r?\n/', $docComment);
        $description = '';
        
        foreach ($lines as $line) {
            if (preg_match('/^\s*@/', $line)) {
                continue;
            }
            $description .= $line . "\n";
        }
        
        return trim($description);
    }

    /**
     * Parse PHPDoc comments for a property
     */
    protected function parsePropertyDocBlock(ReflectionProperty $property): array
    {
        $docComment = $property->getDocComment();
        if (!$docComment) {
            return [];
        }
        
        $schema = [];
        
        // Extract description
        $description = $this->extractDescription($docComment);
        if ($description) {
            $schema['description'] = $description;
        }
        
        // Extract @var type
        if (preg_match('/@var\s+([^\s]+)/', $docComment, $matches)) {
            $type = $matches[1];
            $schema = array_merge($schema, $this->mapTypeToOpenApi($type));
        }
        
        // Extract other annotations
        $this->extractAnnotations($docComment, $schema);
        
        return $schema;
    }

    /**
     * Parse @property annotations from class PHPDoc
     */
    protected function parsePropertyAnnotations(string $docComment, array &$properties): void
    {
        if (!$docComment) {
            return;
        }
        
        // Match @property, @property-read and @property-write annotations
        preg_match_all('/@property(?:-read|-write)?\s+([^\s]+)\s+\$([^\s]+)(?:\s+(.*))?/', $docComment, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $type = $match[1];
            $name = $match[2];
            $description = $match[3] ?? '';
            
            $schema = $this->mapTypeToOpenApi($type);
            
            if ($description) {
                $schema['description'] = trim($description);
            }
            
            // Only add if not already defined through property reflection
            if (!isset($properties[$name])) {
                $properties[$name] = $schema;
            }
        }
    }

    /**
     * Extract additional annotations from PHPDoc comment
     */
    protected function extractAnnotations(string $docComment, array &$schema): void
    {
        // Extract @example
        if (preg_match('/@example\s+(.+)/', $docComment, $matches)) {
            $example = trim($matches[1]);
            $schema['example'] = $this->castExampleValue($example, $schema['type'] ?? 'string');
        }
        
        // Extract @format
        if (preg_match('/@format\s+([^\s]+)/', $docComment, $matches)) {
            $schema['format'] = trim($matches[1]);
        }
        
        // Extract @required
        if (preg_match('/@required/', $docComment)) {
            $schema['required'] = true;
        }
        
        // Extract @nullable
        if (preg_match('/@nullable/', $docComment)) {
            $schema['nullable'] = true;
        }
        
        // Extract @enum
        if (preg_match('/@enum\s+\{([^}]+)\}/', $docComment, $matches)) {
            $enumValues = explode(',', $matches[1]);
            $schema['enum'] = array_map('trim', $enumValues);
        }
    }

    /**
     * Map PHP type to OpenAPI type
     */
    protected function mapTypeToOpenApi(string $type): array
    {
        // Handle nullable types
        $nullable = false;
        if (strpos($type, '?') === 0) {
            $type = substr($type, 1);
            $nullable = true;
        }
        
        // Handle array types
        $isArray = false;
        if (strpos($type, '[]') !== false) {
            $type = str_replace('[]', '', $type);
            $isArray = true;
        }
        
        // Default schema
        $schema = [
            'type' => 'string',
        ];
        
        // Map basic types
        switch ($type) {
            case 'int':
            case 'integer':
                $schema['type'] = 'integer';
                break;
                
            case 'float':
            case 'double':
            case 'decimal':
                $schema['type'] = 'number';
                break;
                
            case 'bool':
            case 'boolean':
                $schema['type'] = 'boolean';
                break;
                
            case 'array':
                $schema['type'] = 'array';
                $schema['items'] = ['type' => 'string'];
                break;
                
            case 'object':
            case 'mixed':
                $schema['type'] = 'object';
                break;
                
            case 'string':
                $schema['type'] = 'string';
                break;
                
            // Handle common formats
            case 'date':
                $schema['type'] = 'string';
                $schema['format'] = 'date';
                break;
                
            case 'datetime':
            case 'Carbon':
            case 'Carbon\\Carbon':
            case '\\Carbon\\Carbon':
            case 'Illuminate\\Support\\Carbon':
            case '\\Illuminate\\Support\\Carbon':
                $schema['type'] = 'string';
                $schema['format'] = 'date-time';
                break;
                
            case 'email':
                $schema['type'] = 'string';
                $schema['format'] = 'email';
                break;
                
            case 'password':
                $schema['type'] = 'string';
                $schema['format'] = 'password';
                break;
                
            case 'url':
            case 'uri':
                $schema['type'] = 'string';
                $schema['format'] = 'uri';
                break;
                
            case 'ip':
            case 'ipv4':
                $schema['type'] = 'string';
                $schema['format'] = 'ipv4';
                break;
                
            case 'ipv6':
                $schema['type'] = 'string';
                $schema['format'] = 'ipv6';
                break;
                
            case 'uuid':
                $schema['type'] = 'string';
                $schema['format'] = 'uuid';
                break;
                
            // Handle class types as references
            default:
                // Check if the type is a valid class
                if (class_exists($type)) {
                    // If it's a model, reference it as a schema
                    $schema = [
                        '$ref' => '#/components/schemas/' . class_basename($type)
                    ];
                }
                break;
        }
        
        // Handle array type
        if ($isArray) {
            $itemSchema = $schema;
            $schema = [
                'type' => 'array',
                'items' => $itemSchema
            ];
        }
        
        // Add nullable property if needed
        if ($nullable) {
            $schema['nullable'] = true;
        }
        
        return $schema;
    }

    /**
     * Cast example value to the correct type
     */
    protected function castExampleValue(string $value, string $type): mixed
    {
        switch ($type) {
            case 'integer':
                return (int) $value;
                
            case 'number':
                return (float) $value;
                
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                
            case 'array':
                // Try to parse as JSON array
                if (($jsonValue = json_decode($value, true)) !== null && is_array($jsonValue)) {
                    return $jsonValue;
                }
                // Fallback to comma-separated values
                return array_map('trim', explode(',', $value));
                
            case 'object':
                // Try to parse as JSON object
                $jsonValue = json_decode($value, true);
                if ($jsonValue !== null) {
                    return $jsonValue;
                }
                break;
        }
        
        // Default to string
        return $value;
    }
}
