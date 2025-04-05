# Laravel Auto Swagger - Documenting Business Exceptions

This guide explains how to document business exceptions in your API documentation.

## Introduction

API endpoints often throw different types of business exceptions which need to be properly documented for API consumers. Laravel Auto Swagger provides the `ApiException` attribute to document these exceptions in your OpenAPI documentation.

## Using the ApiException Attribute

The `ApiException` attribute can be applied to controller methods or classes to document the business exceptions that they might throw.

### Basic Usage

Add the `ApiException` attribute to your controller methods alongside other API documentation attributes:

```php
use App\Exceptions\ProductNotFoundException;
use Laravel\AutoSwagger\Attributes\ApiException;
use Laravel\AutoSwagger\Attributes\ApiOperation;

class ProductController extends Controller
{
    #[ApiOperation(summary: 'Get product details')]
    #[ApiException(
        exception: ProductNotFoundException::class,
        statusCode: 404,
        description: 'The requested product does not exist'
    )]
    public function show($id)
    {
        // Method implementation
    }
}
```

### Multiple Exceptions

You can document multiple exceptions for a single endpoint by adding multiple `ApiException` attributes:

```php
#[ApiOperation(summary: 'Add product to cart')]
#[ApiException(
    exception: ProductNotFoundException::class,
    statusCode: 404,
    description: 'The requested product does not exist'
)]
#[ApiException(
    exception: InsufficientStockException::class,
    statusCode: 422,
    description: 'There is not enough product in stock to fulfill this request'
)]
public function addToCart($id, Request $request)
{
    // Method implementation
}
```

### Customizing Response Schema

You can provide a custom schema for the exception response:

```php
#[ApiException(
    exception: InsufficientStockException::class,
    statusCode: 422,
    description: 'There is not enough product in stock to fulfill this request',
    schema: [
        'type' => 'object',
        'properties' => [
            'message' => ['type' => 'string', 'example' => 'Insufficient stock for product 123'],
            'product_id' => ['type' => 'integer', 'example' => 123],
            'requested_quantity' => ['type' => 'integer', 'example' => 10],
            'available_quantity' => ['type' => 'integer', 'example' => 5],
        ]
    ]
)]
```

### Class-Level Exceptions

You can also apply exceptions at the class level, which will apply to all methods in the controller:

```php
#[ApiTag(name: 'Products')]
#[ApiException(
    exception: UnauthorizedException::class,
    statusCode: 401,
    description: 'User is not authorized to access product information'
)]
class ProductController extends Controller
{
    // All methods in this controller will include the UnauthorizedException in their documentation
}
```

## ApiException Attribute Reference

| Parameter | Type | Description |
|-----------|------|-------------|
| exception | string | The fully qualified exception class name |
| statusCode | int | The HTTP status code returned when this exception occurs (default: 422) |
| description | string | Human-readable description of when this exception occurs |
| schema | array | Custom schema for the error response (optional) |

## Default Error Response Schema

If you don't provide a custom schema, Laravel Auto Swagger will generate a default schema that includes:

```json
{
  "type": "object",
  "properties": {
    "message": {
      "type": "string",
      "example": "Exception description"
    },
    "exception": {
      "type": "string",
      "example": "ExceptionClassName"
    },
    "status_code": {
      "type": "integer",
      "example": 422
    }
  }
}
```

## Differentiating Multiple Exceptions with the Same Status Code

When multiple business exceptions return the same HTTP status code (like 409 Conflict), you need a way to distinguish between them. The recommended approach is to use an `error_code` field that contains a unique string identifier for each type of exception:

```php
class ProductNotFoundException extends Exception
{
    // Unique error code for this exception type
    const ERROR_CODE = 'PRODUCT_NOT_FOUND';
    
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->message,
            'error_code' => self::ERROR_CODE,  // Include the error_code
            'product_id' => $this->productId
        ], 409);
    }
}
```

Then, in your API documentation, make sure to include this error_code in your schema:

```php
#[ApiException(
    exception: ProductNotFoundException::class,
    statusCode: 409,
    description: 'The requested product does not exist',
    schema: [
        'type' => 'object',
        'properties' => [
            'message' => ['type' => 'string'],
            'error_code' => ['type' => 'string', 'example' => 'PRODUCT_NOT_FOUND'],
            'product_id' => ['type' => 'integer']
        ]
    ]
)]
```

API consumers can then check the `error_code` field to differentiate between different types of errors, even when they share the same HTTP status code.

## Complete Example

Here's a complete example of a controller with business exception documentation:

```php
namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductNotFoundException;
use App\Models\Product;
use Laravel\AutoSwagger\Attributes\ApiException;
use Laravel\AutoSwagger\Attributes\ApiOperation;
use Laravel\AutoSwagger\Attributes\ApiResponse;

class ProductController extends Controller
{
    #[ApiOperation(summary: 'Get product details')]
    #[ApiResponse(statusCode: 200, description: 'Product details retrieved successfully')]
    #[ApiException(
        exception: ProductNotFoundException::class,
        statusCode: 404,
        description: 'The requested product does not exist'
    )]
    public function show($id)
    {
        $product = Product::find($id);
        
        if (!$product) {
            throw new ProductNotFoundException($id);
        }
        
        return new ProductResource($product);
    }
    
    #[ApiOperation(summary: 'Add product to cart')]
    #[ApiResponse(statusCode: 200, description: 'Product added to cart successfully')]
    #[ApiException(
        exception: ProductNotFoundException::class,
        statusCode: 404,
        description: 'The requested product does not exist'
    )]
    #[ApiException(
        exception: InsufficientStockException::class,
        statusCode: 422,
        description: 'There is not enough product in stock to fulfill this request'
    )]
    public function addToCart($id, Request $request)
    {
        // Implementation...
    }
}
```

## Best Practices

1. **Be specific** - Provide clear descriptions of when exceptions occur
2. **Include all possible exceptions** - Document every business exception that might be thrown
3. **Use realistic examples** - Provide example response data that matches real-world scenarios
4. **Match implementation** - Ensure the documented exceptions match what's actually thrown in your code
5. **Use appropriate status codes** - Follow HTTP conventions for status codes (404 for not found, 422 for validation errors, etc.)
6. **Use unique error codes** - When multiple exceptions share the same HTTP status code, include a unique `error_code` field to differentiate them
7. **Document error response schemas** - Always provide a schema for your error responses, especially when they include custom fields

## Viewing in Swagger UI

In the Swagger UI, your documented exceptions will appear in the "Responses" section of each endpoint. Each status code will be clearly displayed with its description and schema, making it easy for API consumers to understand what errors might occur.
