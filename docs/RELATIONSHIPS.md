# Laravel Auto Swagger - Documenting Relationships

This guide explains how to document Laravel Eloquent relationships in your API resources.

## Introduction

Laravel Auto Swagger now provides the ability to include relationship information in your API documentation. This is useful when your API resources include related models that should be documented.

## Using the ApiResource Attribute for Relationships

The `ApiResource` attribute has been enhanced with two new properties for relationship documentation:

1. `relations`: An array defining specific relationships to include in the documentation
2. `includeAllRelations`: A boolean flag that enables automatic detection of all relationships

### Explicitly Defining Relationships

To explicitly define the relationships for a resource, use the `relations` parameter:

```php
use App\Models\User;
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
    // Resource implementation
}
```

The `relations` parameter accepts an associative array where:
- Keys are the relation names as they appear in your resource
- Values can be either:
  - A string with the resource class name (for single relationships)
  - An array with `resource` and `isCollection` properties (for more control)

### Automatic Relationship Detection

If you prefer not to manually list all relationships, you can use the `includeAllRelations` parameter:

```php
use App\Models\User;
use Laravel\AutoSwagger\Attributes\ApiResource;

#[ApiResource(
    model: User::class,
    includeAllRelations: true
)]
class UserResource extends JsonResource
{
    // Resource implementation
}
```

When `includeAllRelations` is set to `true`, Laravel Auto Swagger will:

1. Examine the model class specified in the `model` parameter
2. Detect all public methods that return Eloquent relationship types
3. Analyze the PHPDoc for each relationship method to determine the related model
4. Include these relationships in the OpenAPI schema

For this to work effectively, your model should:
- Have properly typed relationship methods (using return type declarations)
- Include PHPDoc annotations specifying the relation type and model

Example of a well-documented model relationship:

```php
/**
 * User can have many posts
 * 
 * @return HasMany<\App\Models\Post>
 */
public function posts(): HasMany
{
    return $this->hasMany(Post::class);
}
```

## Combining Approaches

You can combine both approaches by using `includeAllRelations` along with specific `relations` definitions:

```php
#[ApiResource(
    model: User::class,
    relations: [
        'posts' => PostResource::class  // Override with specific resource
    ],
    includeAllRelations: true  // Auto-detect all other relations
)]
```

This gives you fine-grained control over specific relationships while still automatically detecting others.

## Relationship Detection Logic

The relationship detection logic looks for:

1. Public methods on the model class
2. Methods with return types that extend Laravel's relationship classes
3. PHPDoc comments that describe the related model

The following relationship types are supported:
- HasOne
- HasMany
- BelongsTo
- BelongsToMany
- MorphTo
- MorphOne
- MorphMany
- MorphToMany
- HasOneThrough
- HasManyThrough

## Usage in Controllers

When using these enhanced resources in your controllers, the swagger documentation will automatically include the relationship information:

```php
use App\Http\Resources\UserResource;

public function show($id)
{
    $user = User::with(['department', 'posts'])->findOrFail($id);
    return new UserResource($user);
}
```

## Examples

For complete examples of using relationships in your API resources, see the [RelationshipsExample](../examples/RelationshipsExample) directory.

## Best Practices

1. **Be selective about relationships**: Including all relationships can make your API documentation very large. Only include relationships that are actually returned in your API responses.

2. **Use explicit typing**: Always use return type declarations and PHPDoc annotations in your model relationship methods.

3. **Consider nesting depth**: Deep nesting of relationships can make your OpenAPI schema complex. Consider limiting the depth of relationship inclusion.

4. **Resource consistency**: Make sure all related resources are also documented with their own `ApiResource` attributes for consistent documentation.

5. **Conditional loading**: Remember that documenting relationships doesn't mean they're always included in responses. Use Laravel's `whenLoaded` method to conditionally include relationship data based on what was actually loaded.
