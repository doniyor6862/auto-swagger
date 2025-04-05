# Laravel Auto Swagger - ApiSwagger Attribute

This guide explains how to use the `ApiSwagger` attribute to selectively include controllers and methods in your API documentation.

## Introduction

The `ApiSwagger` attribute allows you to explicitly mark which controllers and methods should be included or excluded from the Swagger documentation. This is particularly useful when:

1. You only want to document specific parts of your API
2. You want to exclude certain endpoints from the documentation
3. You want more precise control over what gets documented

## Usage

### Basic Usage

Mark specific controllers or methods with the `ApiSwagger` attribute to indicate they should be included in the documentation:

```php
use Laravel\AutoSwagger\Attributes\ApiSwagger;
use Laravel\AutoSwagger\Attributes\ApiOperation;

// Mark the entire controller for inclusion
#[ApiSwagger]
class ProductController extends Controller
{
    // All public methods with ApiOperation will be documented
    #[ApiOperation(summary: 'Get all products')]
    public function index()
    {
        // Method implementation
    }
}

// Or mark specific methods
class OrderController extends Controller
{
    // This method will be included in the documentation
    #[ApiSwagger]
    #[ApiOperation(summary: 'Get all orders')]
    public function index()
    {
        // Method implementation
    }
    
    // This method will NOT be included (unless route analysis is enabled)
    #[ApiOperation(summary: 'Get order details')]
    public function show($id)
    {
        // Method implementation
    }
}
```

### Excluding Methods

You can also use the attribute to explicitly exclude a method:

```php
use Laravel\AutoSwagger\Attributes\ApiSwagger;
use Laravel\AutoSwagger\Attributes\ApiOperation;

#[ApiSwagger] // Include the entire controller
class UserController extends Controller
{
    #[ApiOperation(summary: 'Get all users')]
    public function index()
    {
        // This will be included
    }
    
    #[ApiSwagger(include: false)] // Explicitly exclude this method
    #[ApiOperation(summary: 'Internal method')]
    public function internalMethod()
    {
        // This will NOT be included in documentation
    }
}
```

## Configuration

The `ApiSwagger` attribute can be configured in `config/auto-swagger.php`:

```php
'scan' => [
    // ... other scan settings
    
    // If true, only methods/classes with ApiSwagger will be documented
    'require_api_swagger' => false,
    
    // If true, Laravel routes will be analyzed to extract documentation
    'analyze_routes' => true,
],

// API routes prefix (used for route analysis)
'api_prefix' => 'api',
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `require_api_swagger` | boolean | false | If true, only controllers/methods with the ApiSwagger attribute will be included |
| `analyze_routes` | boolean | true | If true, Laravel routes will be analyzed to extract API documentation |
| `api_prefix` | string | 'api' | The prefix for API routes that should be included in documentation |

## Route Analysis

When `analyze_routes` is enabled, Laravel Auto Swagger will:

1. Scan all registered routes in your application
2. Include routes that match the `api_prefix` configuration
3. Extract controller and method information from the routes
4. Check for `ApiSwagger` attributes if `require_api_swagger` is enabled
5. Document the endpoints using actual route information (HTTP methods, paths, etc.)

This approach ensures that your documentation accurately reflects your actual API routes, including any custom route names, parameter patterns, or nested resource definitions.

## Best Practices

1. **Selective Documentation**: Use `ApiSwagger` to selectively document only stable, public-facing API endpoints.

2. **Route Analysis**: Enable route analysis to ensure documentation matches your actual route definitions.

3. **Class-Level Inclusion**: Mark entire controller classes with `ApiSwagger` when most methods should be documented, and selectively exclude specific methods.

4. **Method-Level Inclusion**: Use method-level `ApiSwagger` attributes when only a few methods in a controller should be documented.

5. **Version Control**: Consider using `ApiSwagger` to differentiate between API versions by marking endpoints for inclusion/exclusion based on version compatibility.

## Examples

### Example Controller with Selected Methods

```php
use Laravel\AutoSwagger\Attributes\ApiSwagger;
use Laravel\AutoSwagger\Attributes\ApiOperation;
use Laravel\AutoSwagger\Attributes\ApiResponse;

#[ApiSwagger]
#[ApiTag(name: 'Products', description: 'Product management')]
class ProductController extends Controller
{
    #[ApiOperation(summary: 'List all products')]
    #[ApiResponse(statusCode: 200, description: 'List of products')]
    public function index()
    {
        // This will be included
    }
    
    #[ApiOperation(summary: 'Get product details')]
    #[ApiResponse(statusCode: 200, description: 'Product details')]
    public function show($id)
    {
        // This will be included
    }
    
    #[ApiSwagger(include: false)]
    #[ApiOperation(summary: 'Internal product check')]
    public function checkInventory($id)
    {
        // This will NOT be included
    }
    
    // No ApiSwagger, but will be included because the controller has ApiSwagger
    #[ApiOperation(summary: 'Create a product')]
    public function store(Request $request)
    {
        // This will be included
    }
}
```
