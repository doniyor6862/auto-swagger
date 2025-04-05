<?php

namespace Laravel\AutoSwagger\Services;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Laravel\AutoSwagger\Attributes\ApiResource;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class ResourceParser
{
    /**
     * Extract schema information from a Laravel API Resource
     */
    public function parseResource(string $resourceClass): array
    {
        if (!class_exists($resourceClass)) {
            return [];
        }

        // Check if the class extends JsonResource or ResourceCollection
        if (!is_subclass_of($resourceClass, JsonResource::class) && !is_subclass_of($resourceClass, ResourceCollection::class)) {
            return [];
        }

        // Get the reflection class
        $reflectionClass = new ReflectionClass($resourceClass);
        
        // Check for ApiResource attribute first
        $apiResourceAttributes = $reflectionClass->getAttributes(ApiResource::class, ReflectionAttribute::IS_INSTANCEOF);
        
        if (!empty($apiResourceAttributes)) {
            $apiResource = $apiResourceAttributes[0]->newInstance();
            return $this->processApiResourceAttribute($apiResource, $resourceClass);
        }

        // Determine if the resource is a collection
        $isCollection = is_subclass_of($resourceClass, ResourceCollection::class);

        // Try to find the toArray method which defines the resource structure
        if (!$reflectionClass->hasMethod('toArray')) {
            // Fall back to parent method if not overridden
            return $this->guessResourceSchema($resourceClass, $isCollection);
        }

        // Analyze the toArray method for schema information
        $toArrayMethod = $reflectionClass->getMethod('toArray');

        // If the method is not overridden in the class, use our guessing mechanism
        if ($toArrayMethod->class !== $resourceClass) {
            return $this->guessResourceSchema($resourceClass, $isCollection);
        }

        // Check if we can extract a model from the resource
        $modelClass = $this->extractModelFromResource($resourceClass);

        // If we have a model class, we can use it to determine the schema
        if ($modelClass) {
            return $this->buildSchemaFromModel($modelClass, $isCollection);
        }

        // Last resort: provide a generic schema
        return $this->buildGenericSchema($isCollection);
    }
    
    /**
     * Process the ApiResource attribute to build a schema
     */
    protected function processApiResourceAttribute(ApiResource $apiResource, string $resourceClass): array
    {
        // Check if we have an explicit model
        if ($apiResource->model && class_exists($apiResource->model)) {
            $schema = $this->buildSchemaFromModel(
                $apiResource->model, 
                $apiResource->isCollection || is_subclass_of($resourceClass, ResourceCollection::class),
                $apiResource->isPaginated
            );
            
            // If we have relations defined, process them
            if (!empty($apiResource->relations) || $apiResource->includeAllRelations) {
                $schema = $this->includeRelations($schema, $apiResource);
            }
            
            return $schema;
        }
        
        // If we have a custom schema defined, use that
        if (!empty($apiResource->schema)) {
            $schema = $apiResource->schema;
            
            // Add description if available
            if ($apiResource->description) {
                $schema['description'] = $apiResource->description;
            }
            
            // Wrap in collection if needed
            if ($apiResource->isCollection || is_subclass_of($resourceClass, ResourceCollection::class)) {
                if ($apiResource->isPaginated) {
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
        
        // Fall back to default parsing logic
        $isCollection = $apiResource->isCollection || is_subclass_of($resourceClass, ResourceCollection::class);
        $modelClass = $this->extractModelFromResource($resourceClass);
        
        if ($modelClass) {
            $schema = $this->buildSchemaFromModel($modelClass, $isCollection, $apiResource->isPaginated);
            
            // If we have relations defined, process them
            if (!empty($apiResource->relations) || $apiResource->includeAllRelations) {
                $schema = $this->includeRelations($schema, $apiResource);
            }
            
            return $schema;
        }
        
        return $this->buildGenericSchema($isCollection, $apiResource->isPaginated);
    }
    
    /**
     * Include relations in the schema
     */
    protected function includeRelations(array $schema, ApiResource $apiResource): array
    {
        // Only add relations to object schemas in the correct place
        if (!isset($schema['properties']) && isset($schema['items']['properties'])) {
            // For collections, we need to add to the items schema
            return $this->addRelationsToProperties($schema, $schema['items']['properties'], $apiResource);
        } elseif (isset($schema['properties'])) {
            // For single resources
            return $this->addRelationsToProperties($schema, $schema['properties'], $apiResource);
        } elseif (isset($schema['properties']['data']['items']['properties'])) {
            // For paginated collections
            return $this->addRelationsToProperties($schema, $schema['properties']['data']['items']['properties'], $apiResource);
        }
        
        return $schema;
    }
    
    /**
     * Add relations to the properties of a schema
     */
    protected function addRelationsToProperties(array $schema, array &$properties, ApiResource $apiResource): array
    {
        // Add explicitly defined relations
        foreach ($apiResource->relations as $relationName => $relationInfo) {
            // Handle both string format and array format
            if (is_string($relationInfo)) {
                $resourceClass = $relationInfo;
                $isCollection = false;
            } else {
                $resourceClass = $relationInfo['resource'] ?? null;
                $isCollection = $relationInfo['isCollection'] ?? false;
            }
            
            if (!$resourceClass || !class_exists($resourceClass)) {
                continue;
            }
            
            // Parse the resource to get its schema
            $relationSchema = $this->parseResource($resourceClass);
            
            if (empty($relationSchema)) {
                continue;
            }
            
            // If the relation resource is a collection but not marked as such, wrap it
            if ($isCollection && !isset($relationSchema['type']) && !isset($relationSchema['items'])) {
                $relationSchema = [
                    'type' => 'array',
                    'items' => $relationSchema
                ];
            }
            
            // Add to properties
            $properties[$relationName] = $relationSchema;
        }
        
        // If includeAllRelations is true and we have a model, try to detect all relations
        if ($apiResource->includeAllRelations && $apiResource->model && class_exists($apiResource->model)) {
            $this->detectAndAddAllRelations($properties, $apiResource->model);
        }
        
        return $schema;
    }
    
    /**
     * Detect and add all relations from a model
     */
    protected function detectAndAddAllRelations(array &$properties, string $modelClass): void
    {
        if (!class_exists($modelClass)) {
            return;
        }
        
        try {
            // Create an instance of the model
            $model = new $modelClass();
            $reflectionClass = new ReflectionClass($modelClass);
            
            // Get all public methods
            $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);
            
            foreach ($methods as $method) {
                // Skip constructor, non-instance methods and methods with parameters
                if ($method->isConstructor() || $method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
                    continue;
                }
                
                $methodName = $method->getName();
                
                // Try to detect if this is a relation method
                // We're looking for methods that return relationship objects
                $returnType = $method->getReturnType();
                if ($returnType) {
                    $returnTypeName = $returnType->getName();
                    
                    // Check if the return type is a relation class
                    $relationClasses = [
                        'Illuminate\Database\Eloquent\Relations\HasOne',
                        'Illuminate\Database\Eloquent\Relations\HasMany',
                        'Illuminate\Database\Eloquent\Relations\BelongsTo',
                        'Illuminate\Database\Eloquent\Relations\BelongsToMany',
                        'Illuminate\Database\Eloquent\Relations\MorphTo',
                        'Illuminate\Database\Eloquent\Relations\MorphOne',
                        'Illuminate\Database\Eloquent\Relations\MorphMany',
                        'Illuminate\Database\Eloquent\Relations\MorphToMany',
                        'Illuminate\Database\Eloquent\Relations\HasOneThrough',
                        'Illuminate\Database\Eloquent\Relations\HasManyThrough',
                    ];
                    
                    $isRelation = false;
                    foreach ($relationClasses as $relationClass) {
                        if (is_a($returnTypeName, $relationClass, true)) {
                            $isRelation = true;
                            break;
                        }
                    }
                    
                    if ($isRelation) {
                        // This method returns a relation
                        $isToMany = $this->isToManyRelation($returnTypeName);
                        
                        // Try to guess the related model from PHPDoc
                        $docComment = $method->getDocComment();
                        $relatedModel = $this->extractModelFromDocComment($docComment);
                        
                        if ($relatedModel && class_exists($relatedModel)) {
                            $relationSchema = $this->buildSchemaFromModel($relatedModel, false);
                            
                            if ($isToMany) {
                                $properties[$methodName] = [
                                    'type' => 'array',
                                    'items' => $relationSchema
                                ];
                            } else {
                                $properties[$methodName] = $relationSchema;
                            }
                        } else {
                            // If we couldn't determine the model, add a generic schema
                            if ($isToMany) {
                                $properties[$methodName] = [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object'
                                    ]
                                ];
                            } else {
                                $properties[$methodName] = [
                                    'type' => 'object'
                                ];
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // If anything goes wrong, just continue
        }
    }
    
    /**
     * Check if a relation type is a "to-many" relation
     */
    protected function isToManyRelation(string $relationClass): bool
    {
        $toManyRelations = [
            'Illuminate\Database\Eloquent\Relations\HasMany',
            'Illuminate\Database\Eloquent\Relations\BelongsToMany',
            'Illuminate\Database\Eloquent\Relations\MorphMany',
            'Illuminate\Database\Eloquent\Relations\MorphToMany',
            'Illuminate\Database\Eloquent\Relations\HasManyThrough',
        ];
        
        foreach ($toManyRelations as $toManyRelation) {
            if (is_a($relationClass, $toManyRelation, true)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract model class from PHPDoc comment
     */
    protected function extractModelFromDocComment(?string $docComment): ?string
    {
        if (!$docComment) {
            return null;
        }
        
        // Look for common patterns in PHPDoc for relation return types
        // @return HasMany|BelongsToMany|etc<App\Models\SomeModel>
        if (preg_match('/@return\s+[\\\w\|]+<([\\\w]+)>/', $docComment, $matches)) {
            return $matches[1];
        }
        
        // @return Collection|SomeModel[]
        if (preg_match('/@return\s+[\\\w\|]+\|([\\\w]+)\[]/', $docComment, $matches)) {
            return $matches[1];
        }
        
        // @return SomeModel|null
        if (preg_match('/@return\s+([\\\w]+)\|null/', $docComment, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Extract the model class that a resource is typically used for
     */
    protected function extractModelFromResource(string $resourceClass): ?string
    {
        // Check class documentation for model hints
        $reflectionClass = new ReflectionClass($resourceClass);
        $docComment = $reflectionClass->getDocComment();

        if ($docComment) {
            // Look for @mixin or @see tags that might indicate the model
            if (preg_match('/@mixin\s+([^\s]+)/', $docComment, $matches)) {
                $potentialModelClass = $matches[1];
                if (class_exists($potentialModelClass)) {
                    return $potentialModelClass;
                }
            }

            if (preg_match('/@see\s+([^\s]+)/', $docComment, $matches)) {
                $potentialModelClass = $matches[1];
                if (class_exists($potentialModelClass)) {
                    return $potentialModelClass;
                }
            }
        }

        // Try to guess model from resource name convention
        $resourceClassName = class_basename($resourceClass);
        $potentialModelName = str_replace(['Resource', 'Collection'], '', $resourceClassName);
        
        // Check common model namespaces
        $potentialNamespaces = [
            'App\\Models\\',
            'App\\',
        ];

        foreach ($potentialNamespaces as $namespace) {
            $potentialModelClass = $namespace . $potentialModelName;
            if (class_exists($potentialModelClass)) {
                return $potentialModelClass;
            }
        }

        return null;
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
     * Guess a resource schema when we can't determine it from the class
     */
    protected function guessResourceSchema(string $resourceClass, bool $isCollection): array
    {
        $modelClass = $this->extractModelFromResource($resourceClass);
        
        if ($modelClass) {
            return $this->buildSchemaFromModel($modelClass, $isCollection);
        }
        
        return $this->buildGenericSchema($isCollection);
    }

    /**
     * Wrap a schema in a paginated collection format
     */
    protected function wrapInPaginatedCollection(array $schema): array
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
