# Automatic Parameter Extraction

Laravel Auto Swagger can automatically extract request and response parameters from your existing Laravel code, saving you time and ensuring your documentation stays in sync with your actual implementation.

## Table of Contents

- [Form Request Extraction](#form-request-extraction)
- [API Resource Extraction](#api-resource-extraction)
- [How It Works](#how-it-works)
- [Best Practices](#best-practices)
- [Troubleshooting](#troubleshooting)

## Form Request Extraction

Laravel Auto Swagger automatically detects and parses Laravel Form Request classes used in your controller methods. It extracts validation rules to create accurate request body schemas in your OpenAPI documentation.

### Example

If you have a Form Request class like this:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'sometimes|integer|exists:roles,id',
        ];
    }
}
```

And a controller method that uses it:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateUserRequest;
use App\Models\User;

class UserController extends Controller
{
    public function store(CreateUserRequest $request)
    {
        // Your implementation
    }
}
```

Laravel Auto Swagger will automatically:

1. Detect the `CreateUserRequest` type-hint
2. Extract validation rules from the `rules()` method
3. Convert these rules to an OpenAPI schema with appropriate types, formats, and validations
4. Add the schema to your API documentation as the request body

The generated schema will look similar to:

```json
{
  "type": "object",
  "required": ["name", "email", "password"],
  "properties": {
    "name": {
      "type": "string",
      "maxLength": 255,
      "description": "Name"
    },
    "email": {
      "type": "string",
      "format": "email",
      "maxLength": 255,
      "description": "Email"
    },
    "password": {
      "type": "string",
      "minLength": 8,
      "description": "Password"
    },
    "role_id": {
      "type": "integer",
      "description": "Role Id"
    }
  }
}
```

### Supported Validation Rules

Laravel Auto Swagger converts many common Laravel validation rules to OpenAPI equivalents:

| Laravel Rule | OpenAPI Equivalent |
|--------------|-------------------|
| `required` | Adds field to `required` array |
| `string` | `type: "string"` |
| `integer` | `type: "integer"` |
| `boolean` | `type: "boolean"` |
| `array` | `type: "array"` |
| `numeric` | `type: "number"` |
| `date` | `type: "string", format: "date"` |
| `email` | `type: "string", format: "email"` |
| `min:x` | `minLength: x` (strings) or `minimum: x` (numbers) |
| `max:x` | `maxLength: x` (strings) or `maximum: x` (numbers) |
| `in:a,b,c` | `enum: ["a", "b", "c"]` |
| `nullable` | `nullable: true` |
| `file` | `type: "string", format: "binary"` |

## API Resource Extraction

Laravel Auto Swagger can also detect Laravel API Resources and ResourceCollections used in your controller methods for responses. It analyzes these classes to create accurate response schemas in your OpenAPI documentation.

### Example

If you have a Resource class like this:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

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
```

And a controller method that returns it:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;

class UserController extends Controller
{
    public function show($id)
    {
        $user = User::findOrFail($id);
        return new UserResource($user);
    }
    
    public function index()
    {
        $users = User::paginate();
        return UserResource::collection($users);
    }
}
```

Laravel Auto Swagger will automatically:

1. Detect the `UserResource` return type or analyze the return statement
2. Extract the structure from the `toArray()` method or infer it from the underlying model
3. Generate appropriate response schemas for both single resources and collections
4. Add the correct schema to your API documentation

### Collection Detection

Laravel Auto Swagger also detects collection returns and generates appropriate pagination metadata in the schema.

## How It Works

### Form Request Extraction Process

1. When parsing a controller method, the package looks for parameters with type hints
2. If a parameter type extends `Illuminate\Foundation\Http\FormRequest`, it's identified as a Form Request
3. The package instantiates the request class and calls its `rules()` method
4. Each validation rule is converted to its OpenAPI equivalent
5. The resulting schema is added to the operation's request body

### API Resource Extraction Process

1. When parsing a controller method, the package examines the return type hint
2. If the return type extends `JsonResource` or `ResourceCollection`, it's identified as an API Resource
3. The package analyzes the resource's `toArray()` method or tries to infer structure from the underlying model
4. For collections, pagination metadata is added to the schema
5. The resulting schema is added to the operation's response body

## Best Practices

### Improving Form Request Extraction

To get the best results from automatic Form Request extraction:

1. Always use clear, descriptive validation rules
2. Use type validation rules (`string`, `integer`, etc.) for all fields
3. Consider adding property-level PHPDoc comments to give more context

### Improving Resource Extraction

To get the best results from automatic API Resource extraction:

1. Always type-hint your controller methods with the specific resource class
2. Use explicit property access in your `toArray()` method rather than the `$this->when()` method when possible
3. Consider using `@mixin` or `@see` PHPDoc annotations to link your resource to its model

## Troubleshooting

### Form Request Issues

If your Form Request is not being properly detected or parsed:

1. Make sure your Form Request class extends `Illuminate\Foundation\Http\FormRequest`
2. Ensure the `rules()` method returns an array of validation rules
3. Verify you're properly type-hinting the request in your controller method

### Resource Issues

If your API Resource is not being properly detected or parsed:

1. Make sure your Resource class extends `Illuminate\Http\Resources\Json\JsonResource`
2. For collections, make sure your Resource extends `Illuminate\Http\Resources\Json\ResourceCollection` or you're using `YourResource::collection()`
3. Try adding an explicit return type hint to your controller method

If you continue to have issues, you can always fall back to using explicit attribute annotations for complete control.
