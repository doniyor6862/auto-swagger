# Laravel Auto Swagger - API Resource Documentation

This document explains how to use the `ApiResource` attribute to customize the documentation for your Laravel API Resources.

## Introduction

Laravel Auto Swagger automatically extracts response schemas from your Laravel API Resources. While this works well in most cases, sometimes you need more control over how your resources are documented. The `ApiResource` attribute provides this flexibility by allowing you to:

1. Explicitly link a resource to a specific model
2. Define a custom schema when your resource doesn't represent a model
3. Control pagination and collection formatting

## Basic Usage

### Linking to a Model

The most common use case is explicitly specifying which model a resource represents:

```php
use App\Models\Product;
use Laravel\AutoSwagger\Attributes\ApiResource;

#[ApiResource(model: Product::class)]
class ProductResource extends JsonResource
{
    // Your resource implementation
}
```

This tells Laravel Auto Swagger to use the `Product` model's schema when documenting responses that use this resource.

### Custom Schema Definition

When your resource doesn't map directly to a model or you want complete control over the schema:

```php
use Laravel\AutoSwagger\Attributes\ApiResource;

#[ApiResource(
    schema: [
        'type' => 'object',
        'properties' => [
            'status' => [
                'type' => 'string',
                'enum' => ['success', 'error'],
                'example' => 'success'
            ],
            'message' => [
                'type' => 'string',
                'example' => 'Operation completed successfully'
            ],
            'data' => [
                'type' => 'object',
                'properties' => [
                    // Your custom properties here
                ]
            ]
        ]
    ]
)]
class CustomResource extends JsonResource
{
    // Your resource implementation
}
```

## Collection Resources

For resources that represent collections, you can use the `isCollection` and `isPaginated` parameters:

```php
use App\Models\Product;
use Laravel\AutoSwagger\Attributes\ApiResource;

#[ApiResource(
    model: Product::class,
    isCollection: true,
    isPaginated: true
)]
class ProductCollection extends ResourceCollection
{
    // Your collection resource implementation
}
```

If `isPaginated` is true (default for collections), the schema will include Laravel's standard pagination format with `data`, `links`, and `meta` sections.

If `isPaginated` is false, the schema will be a simple array of items:

```php
use App\Models\Tag;
use Laravel\AutoSwagger\Attributes\ApiResource;

#[ApiResource(
    model: Tag::class,
    isCollection: true,
    isPaginated: false
)]
class TagCollection extends ResourceCollection
{
    // A simple non-paginated collection
}
```

## API Reference

### ApiResource Attribute

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| model | string\|null | null | The fully qualified class name of the model this resource represents |
| schema | array | [] | Custom schema definition (used when no model is specified) |
| isPaginated | bool | false | Whether this resource is paginated (for collections) |
| isCollection | bool | false | Whether this resource represents a collection |
| description | string\|null | null | Description of the resource |

## Examples

### Single Resource with Model

```php
use App\Models\User;
use Laravel\AutoSwagger\Attributes\ApiResource;

#[ApiResource(
    model: User::class,
    description: 'User resource with profile information'
)]
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at,
        ];
    }
}
```

### Custom Analytics Response

```php
use Laravel\AutoSwagger\Attributes\ApiResource;

#[ApiResource(
    schema: [
        'type' => 'object',
        'properties' => [
            'visitors' => ['type' => 'integer', 'example' => 12500],
            'pageviews' => ['type' => 'integer', 'example' => 47000],
            'bounce_rate' => ['type' => 'number', 'format' => 'float', 'example' => 0.45],
            'average_session' => ['type' => 'number', 'format' => 'float', 'example' => 3.5],
            'top_pages' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => ['type' => 'string'],
                        'visits' => ['type' => 'integer']
                    ]
                ]
            ]
        ]
    ],
    description: 'Analytics dashboard statistics'
)]
class AnalyticsResource extends JsonResource
{
    // Resource implementation
}
```

### Paginated Collection

```php
use App\Models\Post;
use Laravel\AutoSwagger\Attributes\ApiResource;

#[ApiResource(
    model: Post::class,
    isCollection: true,
    isPaginated: true,
    description: 'Paginated collection of blog posts'
)]
class PostCollection extends ResourceCollection
{
    // Collection implementation
}
```

## Controller Usage

When using these resources in your controllers, Laravel Auto Swagger will automatically detect them and use the defined schemas:

```php
use App\Http\Resources\UserResource;
use Laravel\AutoSwagger\Attributes\ApiOperation;

class UserController extends Controller
{
    #[ApiOperation(summary: 'Get user profile')]
    public function show($id)
    {
        $user = User::findOrFail($id);
        return new UserResource($user);
    }
}
```

The returned resource's schema will automatically be used in the API documentation.

## Priority Order

Laravel Auto Swagger uses the following order of precedence when determining response schemas:

1. Explicit `ApiResponse` attributes on controller methods
2. Returned resource with `ApiResource` attribute
3. Automatically analyzed resource structure
4. Default response schema based on return type hints
