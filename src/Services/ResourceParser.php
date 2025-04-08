<?php

namespace Laravel\AutoSwagger\Services;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

class ResourceParser
{
    /**
     * Extract schema information from a Laravel API Resource
     */
    public function parseResource(ReflectionAttribute $reflectionAttribute): array
    {

        $response = $reflectionAttribute->getArguments()['model'];

        if (class_exists($response)) {
            $data = $this->detectAndAddAllRelations($response);
            return [
                "type" => "object",
                "properties" => $data
            ];
        }

        if (is_array($response)) {
            return [
                "type" => "object",
                "properties" => $response
            ];
        }

        return [
            "type" => "object",
            "properties" => []
        ];
    }


    /**
     * Detect and add all relations from a model by parsing docblock properties
     */
    protected function detectAndAddAllRelations(string $modelClass): ?array
    {
        if (!class_exists($modelClass)) {
            return null;
        }

        try {
            // Create an instance of the model
            $reflectionClass = new ReflectionClass($modelClass);
            $docComment = $reflectionClass->getDocComment();

            if (!$docComment) {
                return null;
            }

            $lines = explode("\n", $docComment);

            $docProperties = [];
            $methods = [];
            $phpDocType = [];

            // Extract properties and methods from docblock
            foreach ($lines as $line) {
                $line = trim($line, " \t\n\r\0\x0B*"); // clean up line
                if (str_starts_with($line, '@property')) {
                    $docProperties[] = $line;
                } elseif (str_starts_with($line, '@method')) {
                    $methods[] = $line;
                }
            }

            // Process properties from docblock
            foreach ($docProperties as $property) {
                preg_match('/@property(-read)?\s+([\w\\\\|]+)\s+\$(\w+)/', $property, $matches);
                preg_match('/@property-(read)\s+Collection<([^,]+),\s*([^>]+)>\s+\$(\w+)/', $property, $collectionMatches);

                if ($matches || $collectionMatches) {

                    $isColection = false;
                    if ($matches) {
                        $propName = $matches[3];
                        $propType = $matches[2];
                        $isReadOnly = false; // Determine if it's a read-only property

                        $phpDocType[] = [
                            'name' => $propName,
                            'type' => $propType,
                            'readonly' => $isReadOnly
                        ];

                    } else {
                        $propName = $collectionMatches[4];
                        $propType = $collectionMatches[3];
                        $isReadOnly = false;
                        $isColection = true;

                        $phpDocType[] = [
                            'name' => $collectionMatches[4],
                            'type' => $collectionMatches[3],
                            'readonly' => false
                        ];
                    }

                    try {
                        $model = new ReflectionClass('App\\Models\\' . $propType);
                        $isModel = true;
                    } catch (\Throwable $exception) {
                        $isModel = false;
                        $model = null;
                    }


                    if ((strpos($propType, '\\') !== false || $isModel) && $isColection === false) {
                        // This looks like a class name
                        if ($model) {
                            $properties[$propName] = $this->buildSchemaFromModel('App\\Models\\' . $propType, false);
                        } else {
                            $properties[$propName] = ['type' => 'object'];
                        }
                    } elseif ($isColection === true) {
                        $data = $this->detectAndAddAllRelations('App\\Models\\' . $propType);

                        $properties[$propName] = [
                            "type" => "array",
                            "items" => [
                                "type" => "object",
                                "properties" => $data
                            ]
                        ];
                    } else {
                        // Convert PHP types to JSON Schema types
                        $schemaType = 'string';
                        $format = null;

                        switch ($propType) {
                            case 'int':
                            case 'integer':
                                $schemaType = 'integer';
                                break;
                            case 'float':
                            case 'double':
                                $schemaType = 'number';
                                break;
                            case 'bool':
                            case 'boolean':
                                $schemaType = 'boolean';
                                break;
                            case 'array':
                                $schemaType = 'array';
                                break;
                            case 'object':
                                $schemaType = 'object';
                                break;
                            case 'string':
                                // Check for common date fields
                                if (str_contains($propName, 'date') || str_contains($propName, 'time') ||
                                    str_ends_with($propName, 'at') || $propName === 'created_at' || $propName === 'updated_at') {
                                    $format = 'date-time';
                                }
                                break;
                        }

                        $property = ['type' => $schemaType];
                        if ($format) {
                            $property['format'] = $format;
                        }

                        $properties[$propName] = $property;
                    }
                }
            }

            return $properties;
        } catch (\Exception $e) {
            // If anything goes wrong, just continue
            return [];
        }
    }


    /**
     * Build a schema based on a model's properties
     */
    protected function buildSchemaFromModel(string $modelClass, bool $isCollection, bool $isPaginated = true): array
    {
        $reflectionClass = new ReflectionClass($modelClass);
        $properties = $reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC);

        // Try to get fillable properties if available
        $fillableProperties = $this->getFillableProperties($reflectionClass);

        // If we have fillable properties, use those instead of public properties
        if (!empty($fillableProperties)) {
            $propertyNames = $fillableProperties;
        } else {
            // Otherwise use public properties
            $propertyNames = array_map(function ($prop) {
                return $prop->getName();
            }, $properties);

            // Add commonly expected model properties
            $commonProps = ['id', 'created_at', 'updated_at'];
            foreach ($commonProps as $prop) {
                if (!in_array($prop, $propertyNames)) {
                    $propertyNames[] = $prop;
                }
            }
        }

        // Build schema properties
        $schemaProperties = [];
        foreach ($propertyNames as $propName) {
            $schemaProperties[$propName] = $this->guessPropertyType($propName);
        }

        // Build the final schema
        $schema = [
            'type' => 'object',
            'properties' => $schemaProperties,
        ];

        if ($isCollection) {
            if ($isPaginated) {
                return $this->wrapInPaginatedCollection($schema);
            } else {
                return [
                    'type' => 'array',
                    'items' => $schema
                ];
            }
        }

        return $schema;
    }

    /**
     * Get fillable properties from a model
     */
    protected function getFillableProperties(ReflectionClass $reflectionClass): array
    {
        $fillable = [];

        // Check if the class has a fillable property
        if ($reflectionClass->hasProperty('fillable')) {
            $fillableProperty = $reflectionClass->getProperty('fillable');
            $fillableProperty->setAccessible(true);

            // We need to create an instance of the model to get the property value
            try {
                $model = $reflectionClass->newInstanceWithoutConstructor();
                $fillable = $fillableProperty->getValue($model);
            } catch (\Exception $e) {
                // If we can't create an instance, we'll just use an empty array
                $fillable = [];
            }
        }

        return $fillable;
    }

    /**
     * Guess a property's type based on its name
     */
    protected function guessPropertyType(string $propertyName): array
    {
        // Common property types based on name patterns
        if ($propertyName === 'id') {
            return ['type' => 'integer', 'example' => 1];
        }

        if (str_ends_with($propertyName, '_id')) {
            return ['type' => 'integer', 'example' => 1];
        }

        if (str_ends_with($propertyName, '_at') || str_ends_with($propertyName, '_date')) {
            return ['type' => 'string', 'format' => 'date-time', 'example' => date('Y-m-d H:i:s')];
        }

        if ($propertyName === 'email') {
            return ['type' => 'string', 'format' => 'email', 'example' => 'user@example.com'];
        }

        if (str_contains($propertyName, 'url') || str_contains($propertyName, 'link')) {
            return ['type' => 'string', 'format' => 'uri', 'example' => 'https://example.com'];
        }

        if (str_starts_with($propertyName, 'is_') || str_starts_with($propertyName, 'has_')) {
            return ['type' => 'boolean', 'example' => true];
        }

        if (str_contains($propertyName, 'amount') || str_contains($propertyName, 'price') || str_contains($propertyName, 'cost')) {
            return ['type' => 'number', 'format' => 'float', 'example' => 99.99];
        }

        // Default to string type
        return ['type' => 'string', 'example' => 'Example ' . str_replace('_', ' ', $propertyName)];
    }


    /**
     * Wrap a schema in a paginated collection format
     */
    public function wrapInPaginatedCollection(array $schema): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'array',
                    'items' => $schema
                ],
                'links' => [
                    'type' => 'object',
                    'properties' => [
                        'first' => ['type' => 'string', 'format' => 'uri'],
                        'last' => ['type' => 'string', 'format' => 'uri'],
                        'prev' => ['type' => 'string', 'format' => 'uri', 'nullable' => true],
                        'next' => ['type' => 'string', 'format' => 'uri', 'nullable' => true],
                    ]
                ],
                'meta' => [
                    'type' => 'object',
                    'properties' => [
                        'current_page' => ['type' => 'integer'],
                        'from' => ['type' => 'integer', 'nullable' => true],
                        'last_page' => ['type' => 'integer'],
                        'links' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'url' => ['type' => 'string', 'format' => 'uri', 'nullable' => true],
                                    'label' => ['type' => 'string'],
                                    'active' => ['type' => 'boolean']
                                ]
                            ]
                        ],
                        'path' => ['type' => 'string', 'format' => 'uri'],
                        'per_page' => ['type' => 'integer'],
                        'to' => ['type' => 'integer', 'nullable' => true],
                        'total' => ['type' => 'integer']
                    ]
                ]
            ]
        ];
    }

    /**
     * Build a generic schema for when we can't determine resource structure
     */
    protected function buildGenericSchema(bool $isCollection, bool $isPaginated = true): array
    {
        $baseSchema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'example' => 1],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'example' => date('Y-m-d H:i:s')],
                'updated_at' => ['type' => 'string', 'format' => 'date-time', 'example' => date('Y-m-d H:i:s')],
            ]
        ];

        if ($isCollection) {
            if ($isPaginated) {
                return $this->wrapInPaginatedCollection($baseSchema);
            } else {
                return [
                    'type' => 'array',
                    'items' => $baseSchema
                ];
            }
        }

        return $baseSchema;
    }
}
