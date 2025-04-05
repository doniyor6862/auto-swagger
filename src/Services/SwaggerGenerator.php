<?php

namespace Laravel\AutoSwagger\Services;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\AutoSwagger\Attributes\ApiException;
use Laravel\AutoSwagger\Attributes\ApiModel;
use Laravel\AutoSwagger\Attributes\ApiOperation;
use Laravel\AutoSwagger\Attributes\ApiParameter;
use Laravel\AutoSwagger\Attributes\ApiProperty;
use Laravel\AutoSwagger\Attributes\ApiRequestBody;
use Laravel\AutoSwagger\Attributes\ApiResponse;
use Laravel\AutoSwagger\Attributes\ApiResource;
use Laravel\AutoSwagger\Attributes\ApiSecurity;
use Laravel\AutoSwagger\Attributes\ApiSwagger;
use Laravel\AutoSwagger\Attributes\ApiTag;
use Laravel\AutoSwagger\Helpers\PathParameterExtractor;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Finder\Finder;

class SwaggerGenerator
{
    /**
     * Configuration array for the Swagger generator
     */
    protected array $config;

    /**
     * The OpenAPI document being built
     */
    protected array $openApiDoc = [];

    /**
     * Map of HTTP methods to OpenAPI operation methods
     */
    protected array $httpMethodsMap = [
        'GET' => 'get',
        'POST' => 'post',
        'PUT' => 'put',
        'PATCH' => 'patch',
        'DELETE' => 'delete',
        'OPTIONS' => 'options',
        'HEAD' => 'head',
    ];
    
    /**
     * Map of PHP types to OpenAPI types
     */
    protected array $typeMap = [
        'int' => ['type' => 'integer', 'format' => 'int32'],
        'integer' => ['type' => 'integer', 'format' => 'int32'],
        'float' => ['type' => 'number', 'format' => 'float'],
        'double' => ['type' => 'number', 'format' => 'double'],
        'string' => ['type' => 'string'],
        'bool' => ['type' => 'boolean'],
        'boolean' => ['type' => 'boolean'],
        'array' => ['type' => 'array'],
        'object' => ['type' => 'object'],
        'mixed' => ['type' => 'object'],
        'date' => ['type' => 'string', 'format' => 'date'],
        'datetime' => ['type' => 'string', 'format' => 'date-time'],
        'file' => ['type' => 'string', 'format' => 'binary'],
    ];

    /**
     * Constructor.
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initializeOpenApiDoc();
    }

    /**
     * Initialize the OpenAPI document with basic information.
     */
    protected function initializeOpenApiDoc(): void
    {
        $this->openApiDoc = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $this->config['title'],
                'description' => $this->config['description'],
                'version' => $this->config['version'],
            ],
            'servers' => $this->config['servers'],
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => $this->config['securityDefinitions'],
            ],
            'security' => $this->config['security'],
            'tags' => [],
        ];
    }

    /**
     * Generate the OpenAPI document by scanning the codebase.
     */
    public function generate(): array
    {
        $this->scanModels();
        $this->scanControllers($this->config['scan']['controllers_path']);
        
        if (!empty($this->config['scan']['analyze_routes']) && $this->config['scan']['analyze_routes']) {
            $this->analyzeRoutes();
        }
        
        return $this->openApiDoc;
    }

    /**
     * Scan model classes for API schema definitions.
     */
    protected function scanModels(): void
    {
        // Check if models path exists
        if (!file_exists($this->config['scan']['models_path'])) {
            return;
        }

        $modelFinder = new Finder();
        $modelFinder->files()->in($this->config['scan']['models_path'])->name('*.php');

        foreach ($modelFinder as $file) {
            $className = $this->getClassNameFromFile($file->getRealPath());
            
            if (!$className) {
                continue;
            }
            
            // Process model class
            $this->processModelClass($className);
        }
    }

    /**
     * Process a model class to extract schema information.
     */
    protected function processModelClass(string $className): void
    {
        if (!class_exists($className)) {
            return;
        }
        
        $reflectionClass = new ReflectionClass($className);
        
        // Check for ApiModel attribute
        $apiModelAttributes = $reflectionClass->getAttributes(ApiModel::class, ReflectionAttribute::IS_INSTANCEOF);
        
        if (empty($apiModelAttributes)) {
            // No explicit ApiModel attribute, try to extract from PHPDoc
            $this->processModelFromPhpDoc($className);
            return;
        }
        
        $apiModel = $apiModelAttributes[0]->newInstance();
        
        $schema = [
            'type' => 'object',
            'description' => $apiModel->description,
            'properties' => [],
        ];
        
        $properties = $reflectionClass->getProperties();
        $required = [];
        
        foreach ($properties as $property) {
            $apiPropertyAttributes = $property->getAttributes(ApiProperty::class, ReflectionAttribute::IS_INSTANCEOF);
            
            if (empty($apiPropertyAttributes)) {
                continue;
            }
            
            $apiProperty = $apiPropertyAttributes[0]->newInstance();
            
            // Skip hidden properties
            if ($apiProperty->hidden) {
                continue;
            }
            
            // Get property name
            $propertyName = $property->getName();
            
            // Add to schema
            $schema['properties'][$propertyName] = $this->getPropertySchema($apiProperty);
            
            // Add to required properties if needed
            if ($apiProperty->required) {
                $required[] = $propertyName;
            }
        }
        
        // Add required properties if any
        if (!empty($required)) {
            $schema['required'] = $required;
        }
        
        // Add to components schemas
        $schemaName = $apiModel->name ?: class_basename($className);
        $this->openApiDoc['components']['schemas'][$schemaName] = $schema;
    }

    /**
     * Process a model class using PHPDoc comments.
     */
    protected function processModelFromPhpDoc(string $className): void
    {
        // This is where you would parse PHPDoc to extract property information
        // This could use reflection and docblock parsing libraries
        
        // For now, we'll skip this if there's no ApiModel attribute
    }

    /**
     * Get schema definition for a property based on ApiProperty attribute.
     */
    protected function getPropertySchema(ApiProperty $apiProperty): array
    {
        if ($apiProperty->schema) {
            return $apiProperty->schema;
        }
        
        $schema = [];
        
        // Handle type
        if ($apiProperty->type) {
            $typeInfo = $this->typeMap[$apiProperty->type] ?? ['type' => $apiProperty->type];
            $schema = array_merge($schema, $typeInfo);
        }
        
        // Handle other properties
        if ($apiProperty->description) {
            $schema['description'] = $apiProperty->description;
        }
        
        if ($apiProperty->example !== null) {
            $schema['example'] = $apiProperty->example;
        }
        
        if ($apiProperty->enum) {
            $schema['enum'] = $apiProperty->enum;
        }
        
        // Handle items for array type
        if (($apiProperty->type === 'array' || $schema['type'] === 'array') && $apiProperty->items) {
            $schema['items'] = $apiProperty->items;
        }
        
        return $schema;
    }

    /**
     * Scan controller classes for API endpoint definitions.
     */
    protected function scanControllers(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        
        $controllerFinder = new Finder();
        $controllerFinder->files()->in($path)->name('*.php');
        
        foreach ($controllerFinder as $file) {
            $className = $this->getClassNameFromFile($file->getRealPath());
            
            if (!$className) {
                continue;
            }
            
            // Process controller class
            $this->processControllerClass($className);
        }
    }

    /**
     * Process a controller class to extract endpoint information.
     */
    protected function processControllerClass(string $className): void
    {
        if (!class_exists($className)) {
            return;
        }
        
        $reflectionClass = new ReflectionClass($className);
        
        // Check if this controller should be included in the documentation
        if (!$this->shouldIncludeController($reflectionClass)) {
            return;
        }
        
        // Get all the tags defined at class level
        $classTags = $this->processClassTags($reflectionClass);
        
        // Get security schemes defined at class level
        $classSecurity = $this->processSecuritySchemes($reflectionClass);
        
        // Process all public methods in the controller
        $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);
        
        foreach ($methods as $method) {
            // Skip methods inherited from parent classes
            if ($method->class !== $className) {
                continue;
            }
            
            $this->processControllerMethod($method, $classTags, $classSecurity);
        }
    }

    /**
     * Determine if a controller should be included in documentation based on ApiSwagger attribute
     */
    protected function shouldIncludeController(ReflectionClass $reflectionClass): bool
    {
        // Check for ApiSwagger attribute
        $apiSwaggerAttributes = $reflectionClass->getAttributes(ApiSwagger::class, ReflectionAttribute::IS_INSTANCEOF);
        
        // If ApiSwagger is required but not present, skip
        if (!empty($this->config['attributes']['api_swagger_required']) && 
            $this->config['attributes']['api_swagger_required'] && 
            empty($apiSwaggerAttributes)) {
            return false;
        }
        
        // If ApiSwagger is present but set to not include, skip
        if (!empty($apiSwaggerAttributes)) {
            $apiSwagger = $apiSwaggerAttributes[0]->newInstance();
            if (!$apiSwagger->include) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Process a controller method to extract endpoint information.
     */
    protected function processControllerMethod(ReflectionMethod $method, array $classTags = [], array $classSecurity = [], ?string $routePath = null, ?string $routeMethod = null): void
    {
        // Get the method's attributes
        $apiOperationAttributes = $method->getAttributes(ApiOperation::class, ReflectionAttribute::IS_INSTANCEOF);
        
        if (empty($apiOperationAttributes)) {
            return; // Skip methods without ApiOperation attribute
        }
        
        // Check for ApiSwagger attribute at method level
        $apiSwaggerAttributes = $method->getAttributes(ApiSwagger::class, ReflectionAttribute::IS_INSTANCEOF);
        if (!empty($apiSwaggerAttributes)) {
            $apiSwagger = $apiSwaggerAttributes[0]->newInstance();
            if (!$apiSwagger->include) {
                return; // Skip if ApiSwagger->include is false
            }
        }
        
        // Get the first (and typically only) ApiOperation attribute
        $apiOperation = $apiOperationAttributes[0]->newInstance();
        
        // Use route info if provided, otherwise use attributes
        $httpMethod = $routeMethod ?? $apiOperation->method;
        $path = $routePath ?? $apiOperation->path;
        
        // If we couldn't determine the method or path, skip this method
        if (!$httpMethod || !$path) {
            return;
        }
        
        // Build operation object
        $operation = [
            'summary' => $apiOperation->summary ?: $method->getName(),
            'description' => $apiOperation->description,
            'tags' => !empty($apiOperation->tags) ? $apiOperation->tags : $classTags,
            'responses' => [],
        ];
        
        // Add security if specified
        if (!empty($apiOperation->security)) {
            $operation['security'] = $apiOperation->security;
        } elseif (!empty($classSecurity)) {
            $operation['security'] = $classSecurity;
        }
        
        // Add operationId if specified
        if ($apiOperation->operationId) {
            $operation['operationId'] = $apiOperation->operationId;
        }
        
        // Mark as deprecated if specified
        if ($apiOperation->deprecated) {
            $operation['deprecated'] = true;
        }
        
        // Add parameters from attributes
        if (!empty($apiOperation->parameters)) {
            $operation['parameters'] = $apiOperation->parameters;
        }
        
        // Process parameter attributes
        $this->processParameterAttributes($method, $operation);
        
        // Process request body
        $this->processRequestBodyAttributes($method, $operation);
        $this->processTypeHintedFormRequests($method, $operation);
        
        // Process response definitions
        $this->processResponseAttributes($method, $operation);
        $this->processResourceResponses($method, $operation);
        
        // Process exceptions
        $this->processExceptionAttributes($method, $operation);

        // If no path is specified, use the one from the ApiOperation attribute or skip
        if (!$path && !$apiOperation->path) {
            return;
        } elseif (!$path) {
            $path = $apiOperation->path;
        }
        
        // Add missing path parameters - this ensures all URL path params are documented
        PathParameterExtractor::addMissingPathParameters($path, $operation);
        
        // Determine HTTP method
        $httpMethod = $routeMethod ? strtoupper($routeMethod) : $apiOperation->method;
        
        // Add the operation to the path
        if (!isset($this->openApiDoc['paths'][$path])) {
            $this->openApiDoc['paths'][$path] = [];
        }
        $this->openApiDoc['paths'][$path][$this->httpMethodsMap[Str::upper($httpMethod)]] = $operation;
    }
    
    /**
     * Process ApiException attributes to document business exceptions
     */
    protected function processExceptionAttributes(ReflectionMethod $method, array &$operation): void
    {
        // Process class-level exceptions first
        $classExceptions = $this->getClassExceptions($method->getDeclaringClass());
        
        // Then process method-level exceptions (which take precedence)
        $methodExceptions = $method->getAttributes(ApiException::class, ReflectionAttribute::IS_INSTANCEOF);
        
        $exceptions = array_merge($classExceptions, $methodExceptions);
        
        foreach ($exceptions as $exceptionAttribute) {
            $apiException = $exceptionAttribute->newInstance();
            $statusCode = (string) $apiException->statusCode;
            
            // Skip if we already have a response for this status code from ApiResponse
            if (isset($operation['responses'][$statusCode])) {
                continue;
            }
            
            // Create a response for this exception
            $response = [
                'description' => $apiException->description ?: "Exception: {$apiException->exception}",
            ];
            
            // Add content schema if provided
            if (!empty($apiException->schema)) {
                $response['content'] = [
                    'application/json' => [
                        'schema' => $apiException->schema
                    ]
                ];
            } else {
                // Use default error schema
                $response['content'] = [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'message' => ['type' => 'string', 'example' => $apiException->description],
                                'exception' => ['type' => 'string', 'example' => class_basename($apiException->exception)],
                                'status_code' => ['type' => 'integer', 'example' => $apiException->statusCode],
                            ]
                        ]
                    ]
                ];
            }
            
            // Add to responses
            $operation['responses'][$statusCode] = $response;
        }
    }
    
    /**
     * Get ApiException attributes from a class
     */
    protected function getClassExceptions(ReflectionClass $class): array
    {
        return $class->getAttributes(ApiException::class, ReflectionAttribute::IS_INSTANCEOF);
    }

    /**
     * Process ApiParameter attributes from a method
     */
    protected function processParameterAttributes(ReflectionMethod $method, array &$operation): void
    {
        $parameterAttributes = $method->getAttributes(ApiParameter::class, ReflectionAttribute::IS_INSTANCEOF);
        
        if (empty($parameterAttributes)) {
            return;
        }
        
        if (!isset($operation['parameters'])) {
            $operation['parameters'] = [];
        }
        
        foreach ($parameterAttributes as $attribute) {
            $parameter = $attribute->newInstance();
            
            $parameterData = [
                'name' => $parameter->name,
                'in' => $parameter->in,
                'description' => $parameter->description,
                'required' => $parameter->required,
            ];
            
            // Add schema information
            if ($parameter->schema) {
                $parameterData['schema'] = $parameter->schema;
            } else {
                $parameterData['schema'] = [
                    'type' => $parameter->type
                ];
                
                if ($parameter->format) {
                    $parameterData['schema']['format'] = $parameter->format;
                }
                
                if ($parameter->example !== null) {
                    $parameterData['schema']['example'] = $parameter->example;
                }
            }
            
            $operation['parameters'][] = $parameterData;
        }
    }
    
    /**
     * Process ApiRequestBody attributes from a method
     */
    protected function processRequestBodyAttributes(ReflectionMethod $method, array &$operation): void
    {
        $requestBodyAttributes = $method->getAttributes(ApiRequestBody::class, ReflectionAttribute::IS_INSTANCEOF);
        
        if (empty($requestBodyAttributes)) {
            return;
        }
        
        $requestBody = $requestBodyAttributes[0]->newInstance();
        
        $requestBodyData = [
            'description' => $requestBody->description,
            'required' => $requestBody->required,
        ];
        
        if ($requestBody->ref) {
            $requestBodyData['content'] = [
                'application/json' => [
                    'schema' => [
                        '$ref' => $requestBody->ref
                    ]
                ]
            ];
        } elseif (!empty($requestBody->content)) {
            $requestBodyData['content'] = $requestBody->content;
        }
        
        $operation['requestBody'] = $requestBodyData;
    }
    
    /**
     * Process ApiResponse attributes from a method
     */
    protected function processResponseAttributes(ReflectionMethod $method, array &$operation): void
    {
        $responseAttributes = $method->getAttributes(ApiResponse::class, ReflectionAttribute::IS_INSTANCEOF);
        
        if (empty($responseAttributes)) {
            return;
        }
        
        foreach ($responseAttributes as $attribute) {
            $response = $attribute->newInstance();
            
            $responseData = [
                'description' => $response->description,
            ];
            
            if ($response->ref) {
                $responseData['content'] = [
                    'application/json' => [
                        'schema' => [
                            '$ref' => $response->ref
                        ]
                    ]
                ];
            } elseif ($response->type) {
                $responseData['content'] = [
                    'application/json' => [
                        'schema' => [
                            'type' => $response->type
                        ]
                    ]
                ];
            } elseif (!empty($response->content)) {
                $responseData['content'] = $response->content;
            }
            
            $operation['responses'][(string) $response->statusCode] = $responseData;
        }
    }
    
    /**
     * Process type-hinted FormRequest classes to extract request body information
     */
    protected function processTypeHintedFormRequests(ReflectionMethod $method, array &$operation): void
    {
        // Skip if we already have a request body
        if (isset($operation['requestBody'])) {
            return;
        }
        
        $parameters = $method->getParameters();
        
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            
            if (!$type || $type->isBuiltin() || $type->getName() === 'array') {
                continue;
            }
            
            $typeName = $type->getName();
            
            if (!class_exists($typeName)) {
                continue;
            }
            
            $reflectionClass = new ReflectionClass($typeName);
            
            // Check if it's a FormRequest
            if (!$reflectionClass->isSubclassOf(FormRequest::class)) {
                continue;
            }
            
            // Create a request body from the FormRequest
            $formRequest = new $typeName();
            $rules = method_exists($formRequest, 'rules') ? $formRequest->rules() : [];
            
            if (empty($rules)) {
                continue;
            }
            
            $properties = [];
            $required = [];
            
            foreach ($rules as $field => $fieldRules) {
                $fieldRules = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
                
                $property = ['type' => 'string'];
                
                // Check for required rule
                if (in_array('required', $fieldRules)) {
                    $required[] = $field;
                }
                
                // Infer type from rules
                if (in_array('numeric', $fieldRules)) {
                    $property['type'] = 'number';
                } elseif (in_array('integer', $fieldRules)) {
                    $property['type'] = 'integer';
                } elseif (in_array('boolean', $fieldRules)) {
                    $property['type'] = 'boolean';
                } elseif (in_array('array', $fieldRules)) {
                    $property['type'] = 'array';
                    $property['items'] = ['type' => 'string'];
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
            
            $operation['requestBody'] = [
                'description' => class_basename($typeName),
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => $schema
                    ]
                ]
            ];
            
            // Only process the first FormRequest
            break;
        }
    }
    
    /**
     * Process Resource classes used in return statements
     */
    protected function processResourceResponses(ReflectionMethod $method, array &$operation): void
    {
        // Skip if we already have a 200 response
        if (isset($operation['responses']['200'])) {
            return;
        }
        
        $resource = $this->detectResourceInstantiation($method);
        
        if (!$resource) {
            return;
        }
        
        $className = $resource['class'];
        $isCollection = $resource['isCollection'];
        
        // Check if this is a JsonResource
        if (!class_exists($className) || !is_subclass_of($className, JsonResource::class)) {
            return;
        }
        
        // Get ApiResource attribute
        $reflectionClass = new ReflectionClass($className);
        $apiResourceAttributes = $reflectionClass->getAttributes(ApiResource::class, ReflectionAttribute::IS_INSTANCEOF);
        
        $schema = [];
        
        if (!empty($apiResourceAttributes)) {
            $apiResource = $apiResourceAttributes[0]->newInstance();
            
            if (!empty($apiResource->schema)) {
                $schema = $apiResource->schema;
            }
        }
        
        // If no schema is defined, use a default one
        if (empty($schema)) {
            $schema = [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ],
            ];
        }
        
        // If it's a collection, wrap in array
        if ($isCollection || is_subclass_of($className, ResourceCollection::class)) {
            $schema = [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'type' => 'array',
                        'items' => $schema,
                    ],
                ],
            ];
        }
        
        $operation['responses']['200'] = [
            'description' => 'Successful response',
            'content' => [
                'application/json' => [
                    'schema' => $schema
                ]
            ]
        ];
    }
    
    /**
     * Detect resource instantiation in method body
     */
    protected function detectResourceInstantiation(ReflectionMethod $method): ?array
    {
        // This is a simple implementation and might not detect all cases
        // For a more robust solution, a proper PHP parser should be used
        
        if (!$method->getFileName()) {
            return null;
        }
        
        $file = file_get_contents($method->getFileName());
        $source = substr($file, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1);
        
        // Look for new SomeResource or SomeResource::collection
        if (preg_match('/new\s+([\\\w]+Resource)\s*\(/i', $source, $matches)) {
            return [
                'class' => $matches[1],
                'isCollection' => false,
            ];
        }
        
        if (preg_match('/([\\\w]+Resource)::collection\s*\(/i', $source, $matches)) {
            return [
                'class' => $matches[1],
                'isCollection' => true,
            ];
        }
        
        return null;
    }
    
    /**
     * Process class tags from ApiTag attributes
     */
    protected function processClassTags(ReflectionClass $reflectionClass): array
    {
        $tags = [];
        
        $apiTagAttributes = $reflectionClass->getAttributes(ApiTag::class, ReflectionAttribute::IS_INSTANCEOF);
        
        if (empty($apiTagAttributes)) {
            // Use controller name as tag if no ApiTag attributes
            $tags[] = class_basename($reflectionClass->getName());
            return $tags;
        }
        
        foreach ($apiTagAttributes as $attribute) {
            $apiTag = $attribute->newInstance();
            $tags[] = $apiTag->name;
            
            // Add tag to OpenAPI doc if it has a description
            if ($apiTag->description) {
                $existingTags = array_column($this->openApiDoc['tags'], 'name');
                
                if (!in_array($apiTag->name, $existingTags)) {
                    $this->openApiDoc['tags'][] = [
                        'name' => $apiTag->name,
                        'description' => $apiTag->description,
                    ];
                }
            }
        }
        
        return $tags;
    }
    
    /**
     * Process security schemes from ApiSecurity attributes
     */
    protected function processSecuritySchemes(ReflectionClass $reflectionClass): array
    {
        $security = [];
        
        $apiSecurityAttributes = $reflectionClass->getAttributes(ApiSecurity::class, ReflectionAttribute::IS_INSTANCEOF);
        
        foreach ($apiSecurityAttributes as $attribute) {
            $apiSecurity = $attribute->newInstance();
            $security[] = $apiSecurity->security;
        }
        
        return $security;
    }
    
    /**
     * Analyze routes for API documentation
     */
    protected function analyzeRoutes(): void
    {
        $routes = Route::getRoutes();
        $apiPrefix = $this->config['api_prefix'] ?? '';
        
        foreach ($routes as $route) {
            // Skip routes that shouldn't be included
            if (!$this->shouldIncludeRoute($route, $apiPrefix)) {
                continue;
            }
            
            // Get the controller and method
            $action = $route->getAction();
            
            if (!isset($action['controller'])) {
                continue;
            }
            
            // Parse controller@method format
            list($controller, $method) = explode('@', $action['controller']);
            
            // Check if controller exists
            if (!class_exists($controller)) {
                continue;
            }
            
            $reflectionClass = new ReflectionClass($controller);
            
            // Check if method exists
            if (!$reflectionClass->hasMethod($method)) {
                continue;
            }
            
            $reflectionMethod = $reflectionClass->getMethod($method);
            
            // Check if the controller should be included
            if (!$this->shouldIncludeController($reflectionClass)) {
                continue;
            }
            
            // Check for ApiSwagger attribute on method
            $apiSwaggerAttributes = $reflectionMethod->getAttributes(ApiSwagger::class, ReflectionAttribute::IS_INSTANCEOF);
            if (!empty($apiSwaggerAttributes)) {
                $apiSwagger = $apiSwaggerAttributes[0]->newInstance();
                if (!$apiSwagger->include) {
                    continue; // Skip if ApiSwagger is required but not present
                }
            } elseif (!empty($this->config['attributes']['api_swagger_required']) && $this->config['attributes']['api_swagger_required']) {
                continue; // Skip if ApiSwagger is required but not present
            }
            
            // Process class level tags
            $tags = $this->processClassTags($reflectionClass);
            
            // Process class level security schemes
            $security = $this->processSecuritySchemes($reflectionClass);
            
            // Process this route with the actual path and HTTP method
            $routePath = $this->normalizePath($route->uri());
            $routeMethods = $route->methods();
            
            // Use the first HTTP method (typically there's only one)
            $routeMethod = strtolower($routeMethods[0]);
            
            $this->processControllerMethod($reflectionMethod, $tags, $security, $routePath, $routeMethod);
        }
    }
    
    /**
     * Determine if a route should be included in documentation
     */
    protected function shouldIncludeRoute(LaravelRoute $route, string $apiPrefix): bool
    {
        // Skip excluded HTTP methods
        $excludedMethods = ['head', 'options'];
        $routeMethods = array_map('strtolower', $route->methods());
        
        if (count(array_intersect($excludedMethods, $routeMethods)) > 0 && count($routeMethods) === 1) {
            return false;
        }
        
        // Check if route URI matches API prefix
        $uri = $route->uri();
        
        // Skip routes without API prefix
        if (!empty($apiPrefix) && !Str::startsWith($uri, $apiPrefix)) {
            return false;
        }
        
        // Skip internal Laravel routes
        $excludedPrefixes = ['telescope', 'horizon', 'sanctum', '_ignition', '_debugbar'];
        foreach ($excludedPrefixes as $prefix) {
            if (Str::startsWith($uri, $prefix)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Normalize route path for OpenAPI format
     */
    protected function normalizePath(string $path): string
    {
        // Ensure path starts with /
        if (!Str::startsWith($path, '/')) {
            $path = '/' . $path;
        }
        
        // Convert Laravel route parameters to OpenAPI format
        // Laravel: /users/{user} -> OpenAPI: /users/{user}
        // Laravel: /users/{user?} -> OpenAPI: /users/{user}
        return preg_replace('/\{([^\}]+)\?\}/', '{$1}', $path);
    }
    
    /**
     * Extract path parameters from a route path
     * 
     * @param string $path The normalized path
     * @return array Array of parameter names
     */
    protected function extractPathParameters(string $path): array
    {
        $matches = [];
        preg_match_all('/\{([^\}]+)\}/', $path, $matches);
        return $matches[1] ?? [];
    }
    
    /**
     * Save the generated OpenAPI document to a file.
     * 
     * @param string $filePath Path to save the file
     * @param array|null $openApiDoc Optional custom OpenAPI document (uses internal one if not provided)
     * @return bool Success or failure
     */
    public function saveToFile(string $filePath, ?array $openApiDoc = null): bool
    {
        $directory = dirname($filePath);
        $docToSave = $openApiDoc ?? $this->openApiDoc;
        
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
        
        return File::put($filePath, json_encode($docToSave, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
