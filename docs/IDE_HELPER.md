# Laravel Auto Swagger - IDE Helper Integration

This document explains how Laravel Auto Swagger integrates with Laravel IDE Helper to automatically generate API documentation from your existing model PHPDoc annotations.

## Overview

[Laravel IDE Helper](https://github.com/barryvdh/laravel-ide-helper) is a popular package that generates helper files to improve IDE auto-completion. One of its key features is generating comprehensive PHPDoc annotations for models that describe all attributes, relationships, and methods.

Laravel Auto Swagger can now read these PHPDoc annotations and use them to generate OpenAPI documentation automatically, saving you from having to duplicate your model definitions.

## How It Works

When enabled, Laravel Auto Swagger will:

1. Scan all models in your configured models path
2. Look for PHPDoc annotations in the class and property docblocks
3. Extract property types, descriptions, and metadata
4. Convert these to OpenAPI schema definitions
5. Include them in your API documentation

## Supported PHPDoc Annotations

The following PHPDoc annotations are automatically processed:

### Standard Laravel IDE Helper Annotations

```php
/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property float $price
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Category $category
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Tag[] $tags
 */
```

All these property types are automatically converted to the appropriate OpenAPI types and formats.

### Additional Supported Annotations

You can enhance your PHPDoc annotations with additional metadata for better OpenAPI documentation:

```php
/**
 * @property int $id The unique identifier
 * @property string $name The product name @example "iPhone 14 Pro"
 * @property string $status Product status @enum {active,draft,archived}
 * @property float $price @format float @example 999.99
 * @property string $email @format email
 * @property string $uuid @format uuid
 * @property string $description Product details @nullable
 * @property int $category_id @required
 */
```

## Special Annotations

### Type Enhancement

- **Format**: `@format {format}`  
  Specify the OpenAPI format for a property (e.g., `@format date-time`, `@format email`, `@format uuid`)

- **Example**: `@example {value}`  
  Provide an example value for a property (e.g., `@example "John Doe"`, `@example 42`)

- **Enum**: `@enum {value1,value2,value3}`  
  Define allowed values for a property (e.g., `@enum {pending,processing,completed}`)

### Property Constraints

- **Required**: `@required`  
  Mark a property as required in the schema

- **Nullable**: `@nullable`  
  Mark a property as nullable in the schema

## IDE Helper Integration

For best results, use Laravel IDE Helper to generate model annotations:

```bash
# Install Laravel IDE Helper if you haven't already
composer require --dev barryvdh/laravel-ide-helper

# Generate model annotations
php artisan ide-helper:models
```

Once you've generated the model annotations, Laravel Auto Swagger will automatically use them when generating your OpenAPI documentation.

## Configuration

The PHPDoc scanning feature is enabled by default in your `config/auto-swagger.php` file:

```php
'scan' => [
    // Other scan settings...
    'use_phpdoc' => true, // Set to false to disable
],
```

## Type Mapping

The following PHP types from PHPDoc are automatically mapped to OpenAPI types:

| PHP Type | OpenAPI Type | OpenAPI Format |
|----------|--------------|----------------|
| `int` / `integer` | integer | int32 |
| `float` / `double` / `decimal` | number | float |
| `string` | string | |
| `bool` / `boolean` | boolean | |
| `array` | array | |
| `object` / `mixed` | object | |
| `date` | string | date |
| `\Carbon\Carbon` | string | date-time |
| `datetime` | string | date-time |
| `email` | string | email |
| `password` | string | password |
| `url` / `uri` | string | uri |
| `ip` / `ipv4` | string | ipv4 |
| `ipv6` | string | ipv6 |
| `uuid` | string | uuid |

## Class References

If your PHPDoc references other model classes, Laravel Auto Swagger will automatically create references to their OpenAPI schemas.

```php
/**
 * @property \App\Models\Category $category
 */
```

This will be converted to:

```json
{
  "$ref": "#/components/schemas/Category"
}
```

## Best Practices

1. **Use Laravel IDE Helper** - Let it generate the base annotations for your models
2. **Enhance with Descriptions** - Add descriptions to your properties
3. **Add Constraints** - Use `@required`, `@nullable`, and other annotations to add constraints
4. **Provide Examples** - Use `@example` to make your API documentation more helpful
5. **Define Enums** - Use `@enum` for properties with a fixed set of values

## Troubleshooting

If your PHPDoc annotations aren't being properly recognized:

1. Make sure PHPDoc scanning is enabled in your config
2. Check that your models are in the correct path
3. Verify that your PHPDoc syntax is correct (no missing asterisks, proper spacing, etc.)
4. Run `php artisan ide-helper:models` to regenerate model annotations
5. Run `php artisan swagger:generate` with the `--verbose` flag to see detailed output
