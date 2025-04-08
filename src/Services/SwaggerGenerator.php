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
        $this->scanRoutes();
        return $this->openApiDoc;
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
        $apiModelAttributes = $reflectionClass->getAttributes(ApiModel::class, ReflectionAttribute::IS_INSTANCEOF);

        // If no ApiModel attributes found, try extracting from PHPDoc
        if (empty($apiModelAttributes)) {
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
            $propertyName = $property->getName();

            $propertySchema = $this->getPropertySchema($apiProperty);

            $schema['properties'][$propertyName] = $propertySchema;

            if ($apiProperty->required) {
                $required[] = $propertyName;
            }
        }

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        $this->openApiDoc['components']['schemas'][class_basename($className)] = $schema;
    }

    /**
     * Process a model class using PHPDoc comments.
     */
    protected function processModelFromPhpDoc(string $className): void
    {
        $phpDocParser = new PhpDocParser();
        $schema = $phpDocParser->parseModelDocBlock($className);

        if (empty($schema) || empty($schema['properties'])) {
            return;
        }

        // Extract required properties
        $required = [];
        foreach ($schema['properties'] as $propertyName => $propertySchema) {
            if (isset($propertySchema['required']) && $propertySchema['required'] === true) {
                $required[] = $propertyName;
                unset($schema['properties'][$propertyName]['required']);
            }
        }

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        $this->openApiDoc['components']['schemas'][class_basename($className)] = $schema;
    }

    /**
     * Get schema definition for a property based on ApiProperty attribute.
     */
    protected function getPropertySchema(ApiProperty $apiProperty): array
    {
        $typeInfo = $this->typeMap[$apiProperty->type] ?? ['type' => $apiProperty->type];

        $schema = [
            'type' => $typeInfo['type'],
            'description' => $apiProperty->description,
        ];

        if (isset($typeInfo['format']) || $apiProperty->format) {
            $schema['format'] = $apiProperty->format ?? $typeInfo['format'];
        }

        if ($apiProperty->nullable) {
            $schema['nullable'] = true;
        }

        if ($apiProperty->enum) {
            $schema['enum'] = $apiProperty->enum;
        }

        if ($apiProperty->example !== null) {
            $schema['example'] = $apiProperty->example;
        }

        return $schema;
    }

    /**
     * Scan controller classes for API endpoint definitions.
     */
    protected function scanRoutes(): void
    {
        $this->analyzeRoutes();
    }

    /**
     * Process a controller class to extract endpoint information.
     */
    protected function processControllerClass(string $className): void
    {
        // Skip processing if the class name is not a controller
        if (!class_exists($className) || !str_contains($className, 'Controller')) {
            return;
        }

        $reflectionClass = new ReflectionClass($className);

        if ($reflectionClass->isAbstract()) {
            return;
        }

        // Check if class has ApiSwagger(false) to exclude it
        $apiSwaggerAttrs = $reflectionClass->getAttributes(ApiSwagger::class, ReflectionAttribute::IS_INSTANCEOF);
        if (!empty($apiSwaggerAttrs)) {
            $apiSwagger = $apiSwaggerAttrs[0]->newInstance();
            if (!$apiSwagger->include) {
                return; // Skip this class if explicitly excluded
            }
        } else {
            // If ApiSwagger is required for inclusion and class doesn't have it, skip
            if (Config::get('auto-swagger.scan.require_api_swagger', false)) {
                return;
            }
        }

        // Process class level tags
        $tags = $this->processClassTags($reflectionClass);

        // Process class level security schemes
        $security = $this->processSecuritySchemes($reflectionClass);

        // Process methods
        $methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            // Skip methods that are inherited from a parent class
            if ($method->class !== $className) {
                continue;
            }

            // Skip magic methods
            if (str_starts_with($method->name, '__')) {
                continue;
            }

            // Check method level ApiSwagger attribute
            $methodApiSwaggerAttrs = $method->getAttributes(ApiSwagger::class, ReflectionAttribute::IS_INSTANCEOF);
            if (!empty($methodApiSwaggerAttrs)) {
                $methodApiSwagger = $methodApiSwaggerAttrs[0]->newInstance();
                if (!$methodApiSwagger->include) {
                    continue; // Skip this method if explicitly excluded
                }
            } else {
                // If ApiSwagger is required for inclusion and method doesn't have it, skip
                // Also check if class has ApiSwagger - if it does, we still include the method
                if (Config::get('auto-swagger.scan.require_api_swagger', false) && empty($apiSwaggerAttrs)) {
                    continue;
                }
            }

            $this->processControllerMethod($method, $tags, $security);
        }
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

        // Get the first (and typically only) ApiOperation attribute
        $apiOperation = $apiOperationAttributes[0]->newInstance();

        // Use route info if provided, otherwise guess
        $httpMethod = $routeMethod ?? $this->guessHttpMethod($method->name);
        $path = $routePath ?? $this->guessPath($method->name, $method->class);

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

        if ($apiOperation->deprecated) {
            $operation['deprecated'] = true;
        }

        // Add parameters from attributes
        if (!empty($apiOperation->parameters)) {
            $operation['parameters'] = $apiOperation->parameters;
        }

        // Look for parameter attributes
        $this->processParameterAttributes($method, $operation);

        // Look for request body attributes
        $this->processRequestBodyAttributes($method, $operation);

        // Auto-detect FormRequest classes from type hints
        $this->processTypeHintedFormRequests($method, $operation);

        // Process responses from attributes
        $this->processResponseAttributes($method, $operation);

        // Auto-detect Resources in return statements
        $this->processResourceResponses($method, $operation);

        // Process business exceptions
        $this->processExceptionAttributes($method, $operation);

        // If still no responses, add default ones
        if (empty($operation['responses'])) {
            // Default responses if none specified
            $operation['responses'] = [
                '200' => [
                    'description' => 'Successful operation',
                ],
                '400' => [
                    'description' => 'Bad request',
                ],
                '401' => [
                    'description' => 'Unauthorized',
                ],
                '500' => [
                    'description' => 'Server error',
                ],
            ];
        }

        // Add the operation to the path
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
            $statusCode = (string)$apiException->statusCode;

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

        $request = null;

        array_map(function (\ReflectionParameter $parameter) use (&$request) {
            if ($parameter->name === 'request') {
                $request = $parameter->getType();
            }
        }, $method->getParameters());

        $requestClass = new ReflectionClass($request->getName());

        if (!$requestClass->isSubclassOf(FormRequest::class)) {
            $requestBody = [];
        } else {
            $rules = $requestClass->newInstance()->rules();

            $requestBody = $this->buildRequestSchema($request->getName(), $rules);
        }

        $requestBodyData['content'] = [
            'application/json' => [
                'schema' => $requestBody
            ]
        ];

        $operation['requestBody'] = $requestBodyData;

//        $operation['requestBody'] = $requestBody;
//        $requestBody = $requestBodyAttributes[0]->newInstance();
//
//        $requestBodyData = [
//            'description' => $requestBody->description,
//            'required' => $requestBody->required,
//        ];
//
//        if ($requestBody->ref) {
//            $requestBodyData['content'] = [
//                'application/json' => [
//                    'schema' => [
//                        '$ref' => $requestBody->ref
//                    ]
//                ]
//            ];
//        } elseif (!empty($requestBody->content)) {
//            $requestBodyData['content'] = $requestBody->content;
//        }
//
//        $operation['requestBody'] = $requestBodyData;
    }


    private function buildRequestSchema(string $requestName, array $rules): array
    {
        $requestName = last(explode('\\', $requestName));
        $schema = [
            'type' => 'object',
            'properties' => []
        ];

        $required = [];

        foreach ($rules as $field => $fieldRules) {
            // Handle dot notation for nested properties
            if (strpos($field, '.') !== false) {
                // For simplicity, we'll skip nested properties in this example
                continue;
            }

            // Convert to array if it's a string
            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            $property = [
                'type' => 'string' // Default type
            ];

            // Check if field is required
            if (in_array('required', $fieldRules)) {
                $required[] = $field;
            }

            // Determine type from rules
            foreach ($fieldRules as $rule) {
                $rule = is_string($rule) ? $rule : '';

                if (strpos($rule, 'integer') === 0 || strpos($rule, 'numeric') === 0) {
                    $property['type'] = 'integer';
                } elseif (strpos($rule, 'boolean') === 0) {
                    $property['type'] = 'boolean';
                } elseif (strpos($rule, 'array') === 0) {
                    $property['type'] = 'array';
                    $property['items'] = ['type' => 'string'];
                } elseif (strpos($rule, 'date') === 0) {
                    $property['type'] = 'string';
                    $property['format'] = 'date-time';
                } elseif (strpos($rule, 'email') === 0) {
                    $property['format'] = 'email';
                } elseif (preg_match('/max:(\d+)/', $rule, $matches)) {
                    if ($property['type'] === 'string') {
                        $property['maxLength'] = (int)$matches[1];
                    } else {
                        $property['maximum'] = (int)$matches[1];
                    }
                } elseif (preg_match('/min:(\d+)/', $rule, $matches)) {
                    if ($property['type'] === 'string') {
                        $property['minLength'] = (int)$matches[1];
                    } else {
                        $property['minimum'] = (int)$matches[1];
                    }
                } elseif (preg_match('/in:(.+)/', $rule, $matches)) {
                    $property['enum'] = explode(',', $matches[1]);
                }
            }

            $schema['properties'][$field] = $property;
        }

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
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

            if (!empty($response->headers)) {
                $responseData['headers'] = $response->headers;
            }

            $operation['responses'][(string)$response->statusCode] = $responseData;
        }
    }

    /**
     * Process type-hinted FormRequest classes to extract request body information
     */
    protected function processTypeHintedFormRequests(ReflectionMethod $method, array &$operation): void
    {
        // Skip if we already have a request body defined
        if (isset($operation['requestBody'])) {
            return;
        }

        $params = $method->getParameters();

        foreach ($params as $param) {
            $paramType = $param->getType();

            // Skip parameters without type hints
            if ($paramType === null) {
                continue;
            }

            // Get the type name
            if ($paramType instanceof \ReflectionNamedType) {
                $typeName = $paramType->getName();

                // Skip built-in types
                if ($paramType->isBuiltin()) {
                    continue;
                }

                // Check if this parameter is a FormRequest
                if (!class_exists($typeName)) {
                    continue;
                }

                if (!is_subclass_of($typeName, FormRequest::class)) {
                    continue;
                }

                // We found a FormRequest, extract schema from it
                $requestParser = new RequestParser();
                $schema = $requestParser->parseRequest($typeName);

                if (!empty($schema)) {
                    $operation['requestBody'] = [
                        'description' => 'Request body',
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => $schema
                            ]
                        ]
                    ];

                    // Stop after finding the first FormRequest
                    break;
                }
            }
        }
    }

    /**
     * Process Resource classes used in return statements
     */
    protected function processResourceResponses(ReflectionMethod $method, array &$operation): void
    {
        // Skip if we already have responses defined
        if (!empty($operation['responses']) && isset($operation['responses']['200']['content'])) {
            return;
        }
        if (empty($method->getAttributes(ApiResource::class))) {
            $schema = [
                "type" => "object",
                "properties" => []
            ];
        } else {
            $responseAttribute = $method->getAttributes(ApiResource::class)[0];
            $resourceParser = new ResourceParser();
            $schema = $resourceParser->parseResource($responseAttribute);
            if (isset($responseAttribute->getArguments()['isPaginated']) && $responseAttribute->getArguments()['isPaginated']) {
                $schema = $resourceParser->wrapInPaginatedCollection($schema);
            }
        }

        if (!empty($schema)) {
            if (!isset($operation['responses']['200'])) {
                $operation['responses']['200'] = [
                    'description' => 'Successful operation',
                ];
            }

            $operation['responses']['200']['content'] = [
                'application/json' => [
                    'schema' => $schema
                ]
            ];
        }

    }

    /**
     * Detect resource instantiation in method body
     *
     * @param \ReflectionMethod $method
     * @return array|null
     */
    protected function detectResourceInstantiation(ReflectionMethod $method): ?array
    {
        try {
            // Get method body
            $fileName = $method->getFileName();
            $startLine = $method->getStartLine();
            $endLine = $method->getEndLine();

            if (!$fileName || !file_exists($fileName)) {
                return null;
            }

            $fileContent = file_get_contents($fileName);
            $lines = explode("\n", $fileContent);
            $methodBody = implode("\n", array_slice($lines, $startLine, $endLine - $startLine));

            // Look for patterns like 'return new SomeResource(' or 'return SomeResource::collection('
            if (preg_match('/return\s+new\s+([\\\w]+)\s*\(/', $methodBody, $matches)) {
                $resourceClass = $matches[1];

                // Resolve the class if it's not fully qualified
                if (!class_exists($resourceClass)) {
                    // Try to find the matching import
                    $namespacePattern = '/namespace\s+([\\\w]+)\s*;/';
                    $importPattern = '/use\s+([\\\w]+\\' . basename($resourceClass) . ')\s*;/';

                    if (preg_match($namespacePattern, $fileContent, $nsMatches) &&
                        preg_match($importPattern, $fileContent, $importMatches)) {
                        $resourceClass = $importMatches[1];
                    } else {
                        // Try with namespace
                        preg_match($namespacePattern, $fileContent, $nsMatches);
                        $namespace = $nsMatches[1] ?? '';
                        $possibleClass = $namespace . '\\' . $resourceClass;

                        if (class_exists($possibleClass)) {
                            $resourceClass = $possibleClass;
                        }
                    }
                }

                if (class_exists($resourceClass) && is_subclass_of($resourceClass, JsonResource::class)) {
                    return [
                        'class' => $resourceClass,
                        'isCollection' => is_subclass_of($resourceClass, ResourceCollection::class)
                    ];
                }
            }

            // Check for Resource::collection pattern
            if (preg_match('/return\s+([\\\w]+)::collection\s*\(/', $methodBody, $matches)) {
                $resourceClass = $matches[1];

                // Resolve the class if needed (same as above)
                if (!class_exists($resourceClass)) {
                    // Try to find the matching import
                    $namespacePattern = '/namespace\s+([\\\w]+)\s*;/';
                    $importPattern = '/use\s+([\\\w]+\\' . basename($resourceClass) . ')\s*;/';

                    if (preg_match($namespacePattern, $fileContent, $nsMatches) &&
                        preg_match($importPattern, $fileContent, $importMatches)) {
                        $resourceClass = $importMatches[1];
                    } else {
                        // Try with namespace
                        preg_match($namespacePattern, $fileContent, $nsMatches);
                        $namespace = $nsMatches[1] ?? '';
                        $possibleClass = $namespace . '\\' . $resourceClass;

                        if (class_exists($possibleClass)) {
                            $resourceClass = $possibleClass;
                        }
                    }
                }

                if (class_exists($resourceClass) && is_subclass_of($resourceClass, JsonResource::class)) {
                    return [
                        'class' => $resourceClass,
                        'isCollection' => true
                    ];
                }
            }
        } catch (\Exception $e) {
            // If anything goes wrong, just return null
        }

        return null;
    }

    /**
     * Guess the path based on controller and method name.
     */
    protected function guessPath(string $methodName, string $controllerClass): ?string
    {
        $controllerBaseName = str_replace('Controller', '', class_basename($controllerClass));
        $controllerBaseName = Str::kebab($controllerBaseName);

        // Convert method name to kebab case
        $methodPath = Str::kebab($methodName);

        // Handle common RESTful methods
        $methodPath = match ($methodName) {
            'index' => '',
            'show' => '/{id}',
            'store' => '',
            'update', 'edit' => '/{id}',
            'destroy' => '/{id}',
            default => "/{$methodPath}"
        };

        return "/api/{$controllerBaseName}" . $methodPath;
    }

    /**
     * Process class level API tags
     */
    protected function processClassTags(ReflectionClass $reflectionClass): array
    {
        $tags = [];
        $apiTagAttributes = $reflectionClass->getAttributes(ApiTag::class, ReflectionAttribute::IS_INSTANCEOF);

        if (!empty($apiTagAttributes)) {
            foreach ($apiTagAttributes as $tagAttribute) {
                $apiTag = $tagAttribute->newInstance();
                $tags[] = $apiTag->name;

                // Add tag definition if not exists
                $tagExists = false;
                foreach ($this->openApiDoc['tags'] as $tag) {
                    if ($tag['name'] === $apiTag->name) {
                        $tagExists = true;
                        break;
                    }
                }

                if (!$tagExists) {
                    $this->openApiDoc['tags'][] = [
                        'name' => $apiTag->name,
                        'description' => $apiTag->description,
                    ];
                }
            }
        }

        // If no tags specified, use controller name as tag
        if (empty($tags)) {
            $controllerName = class_basename($reflectionClass->getName());
            $controllerName = str_replace('Controller', '', $controllerName);
            $tags[] = $controllerName;

            // Add tag definition if not exists
            $tagExists = false;
            foreach ($this->openApiDoc['tags'] as $tag) {
                if ($tag['name'] === $controllerName) {
                    $tagExists = true;
                    break;
                }
            }

            if (!$tagExists) {
                $this->openApiDoc['tags'][] = [
                    'name' => $controllerName,
                    'description' => $controllerName . ' operations',
                ];
            }
        }

        return $tags;
    }

    /**
     * Process security schemes from a class
     */
    protected function processSecuritySchemes(ReflectionClass $reflectionClass): array
    {
        $security = [];
        $apiSecurityAttributes = $reflectionClass->getAttributes(ApiSecurity::class, ReflectionAttribute::IS_INSTANCEOF);

        if (!empty($apiSecurityAttributes)) {
            foreach ($apiSecurityAttributes as $securityAttribute) {
                $apiSecurity = $securityAttribute->newInstance();
                $security[] = [$apiSecurity->name => $apiSecurity->scopes];
            }
        }

        return $security;
    }

    /**
     * Analyze Laravel routes to extract API documentation information
     */
    protected function analyzeRoutes(): void
    {
        $routes = Route::getRoutes();
        $apiPrefix = Config::get('auto-swagger.api_prefix', 'api');

        foreach ($routes as $route) {
            // Skip routes that don't match the API prefix
            if (!$this->shouldIncludeRoute($route, $apiPrefix)) {
                continue;
            }

            // Get controller and method information
            $action = $route->getAction();

            if (!isset($action['controller'])) {
                continue; // Skip routes without controllers (closures, etc.)
            }

            // Parse controller action string (Format: \App\Http\Controllers\UserController@show)
            $segments = explode('@', $action['controller']);

            if (count($segments) !== 2) {
                continue; // Invalid controller format
            }

            list($controllerClass, $methodName) = $segments;

            // Skip if controller or method doesn't exist
            if (!class_exists($controllerClass) || !method_exists($controllerClass, $methodName)) {
                continue;
            }

            // Get reflection objects
            $reflectionClass = new ReflectionClass($controllerClass);
            $reflectionMethod = $reflectionClass->getMethod($methodName);

            // Check ApiSwagger at class level
            $apiSwaggerAttrs = $reflectionClass->getAttributes(ApiSwagger::class, ReflectionAttribute::IS_INSTANCEOF);
            $classHasApiSwagger = false;

            if (!empty($apiSwaggerAttrs)) {
                $apiSwagger = $apiSwaggerAttrs[0]->newInstance();
                if (!$apiSwagger->include) {
                    continue; // Skip if explicitly excluded
                }
                $classHasApiSwagger = true;
            }

            // Check ApiSwagger at method level
            $methodApiSwaggerAttrs = $reflectionMethod->getAttributes(ApiSwagger::class, ReflectionAttribute::IS_INSTANCEOF);

            if (!empty($methodApiSwaggerAttrs)) {
                $methodApiSwagger = $methodApiSwaggerAttrs[0]->newInstance();
                if (!$methodApiSwagger->include) {
                    continue; // Skip if explicitly excluded
                }
            } else if (Config::get('auto-swagger.scan.require_api_swagger', false) && !$classHasApiSwagger) {
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
     * Extract class name from file path.
     */
    protected function getClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        $tokens = token_get_all($content);
        $namespace = '';
        $className = '';
        $namespaceFound = false;
        $classFound = false;

        foreach ($tokens as $token) {
            if (is_array($token)) {
                if ($token[0] === T_NAMESPACE) {
                    $namespaceFound = true;
                }

                if ($namespaceFound && $token[0] === T_STRING) {
                    $namespace .= '\\' . $token[1];
                }

                if ($token[0] === T_CLASS) {
                    $classFound = true;
                }

                if ($classFound && $token[0] === T_STRING) {
                    $className = $token[1];
                    break;
                }
            } elseif ($namespaceFound && $token === ';') {
                $namespaceFound = false;
            }
        }

        if (empty($className)) {
            return null;
        }

        return ltrim($namespace, '\\') . '\\' . $className;
    }

    /**
     * Save the generated OpenAPI document to a file.
     */
    public function saveToFile(string $filePath): bool
    {
        $directory = dirname($filePath);

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        return File::put($filePath, json_encode($this->openApiDoc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
