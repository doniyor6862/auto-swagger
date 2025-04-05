# Laravel Auto Swagger - Attribute Reference

This document provides a comprehensive reference for all PHP 8 attributes available in the Laravel Auto Swagger package.

## Table of Contents

- [Controller Attributes](#controller-attributes)
  - [ApiTag](#apitag)
  - [ApiOperation](#apioperation)
  - [ApiParameter](#apiparameter)
  - [ApiRequestBody](#apirequestbody)
  - [ApiResponse](#apiresponse)
  - [ApiSecurity](#apisecurity)
- [Model Attributes](#model-attributes)
  - [ApiModel](#apimodel)
  - [ApiProperty](#apiproperty)

## Controller Attributes

### ApiTag

Used to categorize API operations into groups in the Swagger UI.

**Target**: Class

**Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| name | string | Yes | Name of the tag |
| description | string | No | Description of the tag |

**Example**:

```php
#[ApiTag(name: 'Users', description: 'User management endpoints')]
class UserController extends Controller
{
    // Controller methods
}
```

### ApiOperation

Provides metadata about an API endpoint.

**Target**: Method

**Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| summary | string | No | Short summary of what the operation does |
| description | string | No | Detailed explanation of the operation |
| tags | array | No | Tags for categorizing the operation |
| deprecated | bool | No | Whether the operation is deprecated |
| parameters | array | No | Operation parameters (path, query, header, cookie) |
| responses | array | No | Possible responses the API can return |

**Example**:

```php
#[ApiOperation(
    summary: 'Get user by ID',
    description: 'Retrieves a specific user by their ID',
    tags: ['Users'],
    deprecated: false
)]
public function show($id)
{
    // Implementation
}
```

### ApiParameter

Defines a parameter for an API operation.

**Target**: Method

**Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| name | string | Yes | Name of the parameter |
| in | string | No | Location of the parameter (query, path, header, cookie) |
| description | string | No | Description of the parameter |
| required | bool | No | Whether the parameter is required |
| type | string | No | Data type of the parameter |
| format | string | No | Format of the parameter |
| schema | array | No | Schema definition for the parameter |
| example | mixed | No | Example value for the parameter |

**Example**:

```php
#[ApiParameter(
    name: 'id',
    in: 'path',
    description: 'User ID',
    required: true,
    type: 'integer',
    example: 1
)]
public function show($id)
{
    // Implementation
}
```

### ApiRequestBody

Defines the request body for an API operation.

**Target**: Method

**Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| description | string | No | Description of the request body |
| required | bool | No | Whether the request body is required |
| content | array | No | Content definition of the request body |
| ref | string | No | Reference to a request body schema |

**Example**:

```php
#[ApiRequestBody(
    description: 'User information',
    required: true,
    content: [
        'application/json' => [
            'schema' => [
                'type' => 'object',
                'required' => ['name', 'email'],
                'properties' => [
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'string', 'format' => 'email']
                ]
            ]
        ]
    ]
)]
public function store(Request $request)
{
    // Implementation
}
```

### ApiResponse

Defines a possible response for an API operation.

**Target**: Method

**Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| statusCode | int | Yes | HTTP status code of the response |
| description | string | Yes | Description of the response |
| type | string | No | Data type of the response |
| ref | string | No | Reference to a response schema |
| content | array | No | Content definition of the response |
| headers | array | No | Headers returned with the response |

**Example**:

```php
#[ApiResponse(
    statusCode: 200,
    description: 'User found',
    content: [
        'application/json' => [
            'schema' => [
                '$ref' => '#/components/schemas/User'
            ]
        ]
    ]
)]
#[ApiResponse(
    statusCode: 404,
    description: 'User not found'
)]
public function show($id)
{
    // Implementation
}
```

### ApiSecurity

Specifies the security requirements for an API operation.

**Target**: Class, Method

**Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| name | string | Yes | Name of the security scheme |
| scopes | array | No | OAuth scopes required for the operation |

**Example**:

```php
#[ApiSecurity(name: 'bearerAuth')]
class UserController extends Controller
{
    // Controller methods
}

// Or on a specific method
#[ApiSecurity(name: 'oauth2', scopes: ['read:users'])]
public function show($id)
{
    // Implementation
}
```

## Model Attributes

### ApiModel

Used to document model classes.

**Target**: Class

**Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| description | string | No | Description of the model |
| properties | array | No | Additional properties definition |

**Example**:

```php
#[ApiModel(description: 'User information')]
class User extends Model
{
    // Model properties and methods
}
```

### ApiProperty

Used to document model properties.

**Target**: Property

**Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| type | string | No | Data type of the property |
| description | string | No | Description of the property |
| required | bool | No | Whether the property is required |
| example | mixed | No | Example value for the property |
| format | string | No | Format of the property |
| enum | array | No | List of possible values for the property |
| nullable | bool | No | Whether the property can be null |

**Example**:

```php
class User extends Model
{
    #[ApiProperty(
        type: 'string',
        description: 'The email address of the user',
        example: 'john@example.com',
        format: 'email'
    )]
    public $email;

    #[ApiProperty(
        type: 'string',
        description: 'User role',
        enum: ['admin', 'user', 'editor'],
        nullable: true
    )]
    public $role;
}
```

## Combining Attributes

Attributes can be combined to create comprehensive API documentation:

```php
#[ApiTag(name: 'Users', description: 'User management endpoints')]
class UserController extends Controller
{
    #[ApiOperation(summary: 'Create a new user', description: 'Creates a user account')]
    #[ApiRequestBody(
        description: 'User information',
        required: true,
        content: [
            'application/json' => [
                'schema' => [
                    'type' => 'object',
                    'required' => ['name', 'email', 'password'],
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'password' => ['type' => 'string', 'format' => 'password']
                    ]
                ]
            ]
        ]
    )]
    #[ApiResponse(
        statusCode: 201,
        description: 'User created successfully',
        content: [
            'application/json' => [
                'schema' => [
                    '$ref' => '#/components/schemas/User'
                ]
            ]
        ]
    )]
    #[ApiResponse(statusCode: 422, description: 'Validation error')]
    public function store(Request $request)
    {
        // Implementation
    }
}
```
