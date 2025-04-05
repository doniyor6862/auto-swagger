# Laravel Auto Swagger

<p align="center">
  <img src="https://raw.githubusercontent.com/swagger-api/swagger-ui/master/docs/images/logo_blue.png" alt="Swagger Logo" width="200">
</p>

An automatic OpenAPI/Swagger documentation generator for Laravel applications inspired by NestJS's @nestjs/swagger package. This package uses PHP 8 attributes to annotate your controllers and models, while also automatically extracting information from your Laravel Form Requests and API Resources.

## Features

### Core Features
- ✅ Automatically generate OpenAPI 3.0 documentation from PHP 8 attributes
- ✅ Beautiful Swagger UI for browsing and testing your API
- ✅ Artisan command for generating documentation
- ✅ Custom route definition with middleware protection

### Smart Auto-Detection
- ✅ **Automatic extraction of parameters from Laravel Form Request classes**
- ✅ **Automatic extraction of response schema from Laravel API Resources**
- ✅ **Custom API Resource schemas with the ApiResource attribute**
- ✅ **Documentation of Eloquent relationships in resources**
- ✅ **PHPDoc extraction from models (compatible with Laravel IDE Helper)**
- ✅ **Route analysis and selective documentation with ApiSwagger attribute**
- ✅ Intelligent schema inference from models and type hints

### Documentation Features
- ✅ Support for documenting endpoints, parameters, request bodies, and responses
- ✅ Security scheme integration (JWT, OAuth, etc.)
- ✅ Customizable Swagger UI with theme options

## Requirements

- PHP 8.0 or higher
- Laravel 10.x
- Composer

## Why Laravel Auto Swagger?

Laravel Auto Swagger stands out from other documentation packages because:

1. **Zero Extra Work Mode** - If you're already using Laravel Form Requests, API Resources, or IDE Helper annotations, you get documentation for free with no additional work needed

2. **Multiple Documentation Styles** - Choose what works for you:
   - PHP 8 Attributes (similar to NestJS decorators)
   - Laravel Form Request validation rules
   - PHPDoc comments (compatible with Laravel IDE Helper)
   - API Resources for response schemas

3. **Laravel IDE Helper Compatible** - Reuse your existing Laravel IDE Helper PHPDoc annotations for generating API documentation

4. **Developer Experience First** - Designed to minimize the documentation burden while maximizing the quality of your API documentation

## Installation

Install the package via Composer:

```bash
composer require laravel/auto-swagger
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=auto-swagger-config
```

Create the directory for your Swagger documentation:

```bash
mkdir -p public/swagger
```

## Quick Start

1. **Configure your API information** in `config/auto-swagger.php`
2. **Use Laravel Form Requests and API Resources** for your endpoints (automatic extraction)
3. **Generate documentation**:
   ```bash
   php artisan swagger:generate
   ```
4. **View your documentation** at `/api/documentation` (or your configured route)

## Configuration

After publishing the configuration file, you can modify it at `config/auto-swagger.php`. Key configuration options include:

### Basic Information

```php
'title' => env('APP_NAME', 'Laravel') . ' API',
'description' => 'API Documentation',
'version' => '1.0.0',
```

### Output Settings

```php
'output_file' => public_path('swagger/swagger.json'),
'output_folder' => public_path('swagger'),
```

### Route Settings

```php
'route_prefix' => 'api/documentation',
'middleware' => [
    'web',
    // Add any additional middleware here (e.g., 'auth')
],
```

### Scanning Settings

```php
'scan' => [
    'controllers_path' => app_path('Http/Controllers'),
    'models_path' => app_path('Models'),
    'include_patterns' => [
        app_path('Http/Controllers/*.php'),
        app_path('Http/Controllers/**/*.php'),
    ],
    'exclude_patterns' => [],
    'use_phpdoc' => true, // Enable or disable PHPDoc scanning
],
```

### UI Settings

```php
'ui' => [
    'enabled' => true,
    'theme' => 'default', // 'default', 'dark', 'light'
    'persist_authorization' => true,
    'display_request_duration' => true,
    'doc_expansion' => 'list', // 'list', 'full', 'none'
],
```

### Security Definitions

```php
'securityDefinitions' => [
    'bearerAuth' => [
        'type' => 'http',
        'scheme' => 'bearer',
        'bearerFormat' => 'JWT',
    ],
],

'security' => [
    ['bearerAuth' => []],
],
```

## Usage

Laravel Auto Swagger provides two ways to document your API:

1. **Zero-Config Mode**: Automatic extraction from Laravel Form Requests and API Resources
2. **Attribute Mode**: Explicit documentation using PHP 8 attributes

### Zero-Config Mode

Simply use standard Laravel practices and get documentation for free:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserStoreRequest;
use App\Http\Resources\UserResource;
use App\Models\User;

class UserController extends Controller
{
    /**
     * List all users
     */
    public function index()
    {
        $users = User::paginate();
        return UserResource::collection($users);
    }

    /**
     * Create a new user
     */
    public function store(UserStoreRequest $request)
    {
        $user = User::create($request->validated());
        return new UserResource($user);
    }
}
```

The package will:
1. Extract validation rules from `UserStoreRequest` for request documentation
2. Extract fields from `UserResource` for response documentation
3. Generate OpenAPI schemas automatically

### Using Attributes (Similar to NestJS)

#### Define Your Models

Use `ApiModel` and `ApiProperty` attributes:

```php
<?php

namespace App\Models;

use Laravel\AutoSwagger\Attributes\ApiModel;
use Laravel\AutoSwagger\Attributes\ApiProperty;

#[ApiModel(description: 'User model representation')]
class User extends Model
{
    #[ApiProperty(type: 'integer', description: 'The unique identifier')]
    protected $id;

    #[ApiProperty(type: 'string', description: 'The name', example: 'John Doe')]
    protected $name;

    #[ApiProperty(type: 'string', description: 'The email', example: 'john@example.com')]
    protected $email;

    #[ApiProperty(type: 'string', format: 'date-time', description: 'Creation date')]
    protected $created_at;
}
```

#### Document Your Controllers

Use `ApiTag` and `ApiOperation` attributes:

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Laravel\AutoSwagger\Attributes\ApiTag;
use Laravel\AutoSwagger\Attributes\ApiOperation;
use Laravel\AutoSwagger\Attributes\ApiResponse;
use Laravel\AutoSwagger\Attributes\ApiParameter;

#[ApiTag(name: 'Users', description: 'User management')]
class UserController extends Controller
{
    #[ApiOperation(summary: 'Get all users', description: 'Retrieves all users')]
    #[ApiResponse(
        statusCode: 200,
        description: 'List of users',
        content: [
            'application/json' => [
                'schema' => [
                    'type' => 'array',
                    'items' => ['$ref' => '#/components/schemas/User']
                ]
            ]
        ]
    )]
    public function index()
    {
        return User::all();
    }

    #[ApiOperation(summary: 'Get user by ID', description: 'Retrieves a user')]
    #[ApiParameter(name: 'id', in: 'path', required: true, type: 'integer')]
    #[ApiResponse(statusCode: 200, description: 'User found', ref: '#/components/schemas/User')]
    #[ApiResponse(statusCode: 404, description: 'User not found')]
    public function show($id)
    {
        return User::findOrFail($id);
    }
}
```

## Automatic Extraction Features

### PHPDoc & Laravel IDE Helper Integration

Laravel Auto Swagger automatically extracts model schemas from PHPDoc comments:

```php
/**
 * Product model representing items for sale
 *
 * @property int $id The unique identifier
 * @property string $name The name of the product
 * @property float $price The price of the product in USD
 * @property bool $is_featured Whether this product is featured
 * @property \Carbon\Carbon $created_at When the product was created
 * 
 * @property-read Category $category The category relationship
 */
class Product extends Model
{
    // Your model implementation
}
```

The package will automatically convert these PHPDoc annotations into an OpenAPI schema. This is particularly useful if you're using Laravel IDE Helper which generates these annotations automatically.

Special PHPDoc annotations supported:
- `@required` - Mark a property as required
- `@format {format}` - Specify an OpenAPI format 
- `@enum {value1,value2,value3}` - Define possible enum values
- `@example {value}` - Provide an example value
- `@nullable` - Mark a property as nullable

### Form Request Extraction

Laravel Auto Swagger automatically extracts validation rules from your Form Request classes:

```php
// In your Form Request
class UserStoreRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'sometimes|string|in:admin,user,editor'
        ];
    }
}

// In your controller - just use type hinting
public function store(UserStoreRequest $request)
{
    // Your implementation
}
```

The validation rules will be automatically converted to OpenAPI schema properties:

```json
{
  "type": "object",
  "required": ["name", "email", "password"],
  "properties": {
    "name": {
      "type": "string",
      "maxLength": 255
    },
    "email": {
      "type": "string",
      "format": "email"
    },
    "password": {
      "type": "string",
      "minLength": 8
    },
    "role": {
      "type": "string",
      "enum": ["admin", "user", "editor"]
    }
  }
}
```

### API Resource Extraction

Laravel Auto Swagger automatically analyzes your API Resources:

```php
// In your Resource - automatic extraction
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

// Or explicitly define model and schema with the ApiResource attribute
use Laravel\AutoSwagger\Attributes\ApiResource;
use App\Models\Product;

#[ApiResource(
    model: Product::class,
    description: 'Product resource with detailed information'
)]
class ProductResource extends JsonResource
{
    // Your resource implementation
}

// Custom schema without a model
#[ApiResource(
    schema: [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean', 'example' => true],
            'message' => ['type' => 'string', 'example' => 'Operation successful'],
            'data' => ['type' => 'object', 'properties' => []]
        ]
    ],
    description: 'Dashboard statistics resource'
)]
class DashboardResource extends JsonResource
{
    // Your resource implementation
}

// In your controller - just return the resource
public function show($id)
{
    return new UserResource(User::findOrFail($id));
}
```

The resource structure will be automatically converted to an OpenAPI response schema. For more details on API Resource documentation, see [API Resource Documentation](docs/API_RESOURCE.md).

### Eloquent Relationship Documentation

Laravel Auto Swagger can automatically document Eloquent relationships:

```php
// In your Model
class User extends Model
{
    /**
     * User has many posts
     * 
     * @return HasMany<\App\Models\Post>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}

// In your Resource - explicitly define relationships
use Laravel\AutoSwagger\Attributes\ApiResource;

#[ApiResource(
    model: User::class,
    relations: [
        'posts' => PostResource::class,
        'department' => [
            'resource' => DepartmentResource::class,
            'isCollection' => false
        ]
    ]
)]
class UserResource extends JsonResource
{
    // Your resource implementation with relationships
}

// Or automatically detect all relationships
#[ApiResource(
    model: User::class,
    includeAllRelations: true
)]
class UserWithAllRelationsResource extends JsonResource
{
    // Your resource implementation
}
```

For more details on documenting relationships, see [Relationships Documentation](docs/RELATIONSHIPS.md).

### Selective Documentation with ApiSwagger

You can mark specific controllers and methods for inclusion in the documentation:

```php
use Laravel\AutoSwagger\Attributes\ApiSwagger;
use Laravel\AutoSwagger\Attributes\ApiOperation;

// Mark the entire controller
#[ApiSwagger]
class ProductController extends Controller
{
    #[ApiOperation(summary: 'List all products')]
    public function index()
    {
        // Will be included in documentation
    }
    
    // Explicitly exclude a method
    #[ApiSwagger(include: false)]
    #[ApiOperation(summary: 'Internal method')]
    public function internalMethod()
    {
        // Will NOT be included in documentation
    }
}
```

You can also enable route analysis to automatically document all of your routes:

```php
// In config/auto-swagger.php
'scan' => [
    // ... other options
    'analyze_routes' => true,
    'require_api_swagger' => false, // Set to true to only include ApiSwagger-marked endpoints
],
'api_prefix' => 'api', // Only include routes with this prefix
```

Learn more in the [ApiSwagger Documentation](docs/API_SWAGGER.md).

## Generating Documentation

Run the Artisan command to generate your documentation:

```bash
php artisan swagger:generate
```

This creates a Swagger JSON file at the location specified in your configuration (default: `public/swagger/swagger.json`).

## Viewing Documentation

Visit your configured documentation route (default: `/api/documentation`) to browse your API in the Swagger UI.

## Documentation

### All Documentation

Detailed documentation is available in the `/docs` directory:

- [Auto Extraction Guide](docs/AUTO_EXTRACTION.md) - How the automatic extraction works
- [API Resource Documentation](docs/API_RESOURCE.md) - How to customize API Resources
- [Relationships Documentation](docs/RELATIONSHIPS.md) - How to document model relationships
- [ApiSwagger Documentation](docs/API_SWAGGER.md) - How to selectively document API endpoints
- [Attribute Reference](docs/ATTRIBUTES.md) - All available PHP 8 attributes
- [Example Implementation](EXAMPLE.md) - Complete blog API example
- [Usage Guide](USAGE_GUIDE.md) - Detailed usage instructions

### Available Attributes

#### ApiModel

Used to document model classes.

| Parameter | Type | Description |
|-----------|------|-------------|
| description | string | Description of the model |
| properties | array | Additional properties definition (optional) |

#### ApiResource

Used to customize API Resource documentation.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| model | string\|null | null | The fully qualified class name of the model this resource represents |
| schema | array | [] | Custom schema definition (used when no model is specified) |
| relations | array | [] | Define related resources to include in documentation |
| isPaginated | bool | false | Whether this resource is paginated (for collections) |
| isCollection | bool | false | Whether this resource represents a collection |
| description | string\|null | null | Description of the resource |
| includeAllRelations | bool | false | Whether to automatically include all model relations |

#### ApiSwagger

Used to mark controllers or methods for inclusion in the Swagger documentation.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| include | bool | true | Whether to include this controller/method in the documentation |

### ApiProperty

Used to document model properties.

| Parameter | Type | Description |
|-----------|------|-------------|
| type | string | Property type (string, integer, boolean, etc.) |
| description | string | Description of the property |
| required | boolean | Whether the property is required |
| example | mixed | Example value |
| format | string | Format of the property (date-time, email, etc.) |
| enum | array | List of allowed values |
| nullable | boolean | Whether the property can be null |

### ApiTag

Used to group API operations in the Swagger UI.

| Parameter | Type | Description |
|-----------|------|-------------|
| name | string | Name of the tag |
| description | string | Description of the tag |

### ApiOperation

Used to document API endpoints.

| Parameter | Type | Description |
|-----------|------|-------------|
| summary | string | Short summary of the operation |
| description | string | Detailed description of the operation |
| responses | array | Map of HTTP status codes to response descriptions |
| parameters | array | List of parameters for the operation |
| tags | array | List of tags to assign to the operation |
| deprecated | boolean | Whether the operation is deprecated |

## Available Commands

| Command | Description |
|---------|-------------|
| swagger:generate | Generate the Swagger documentation |

## Routes

| Route | Description |
|-------|-------------|
| /api/documentation | Display the Swagger UI |
| /api/documentation/json | Get the raw Swagger JSON |

## License

This package is open-sourced software licensed under the MIT license.
# laravel-auto-swagger
# laravel-auto-swagger
