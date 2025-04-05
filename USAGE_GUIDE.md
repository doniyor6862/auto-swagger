# Laravel Auto Swagger - Detailed Usage Guide

This guide provides detailed examples and advanced usage patterns for the Laravel Auto Swagger package.

## Table of Contents

- [Basic Implementation](#basic-implementation)
- [Advanced Examples](#advanced-examples)
- [Best Practices](#best-practices)
- [Troubleshooting](#troubleshooting)

## Basic Implementation

### Installation and Setup

After installing the package via Composer and publishing the configuration, you need to set up your Laravel application to use the package:

1. Make sure the `AutoSwaggerServiceProvider` is registered in your application (it should be auto-discovered by Laravel).
2. Configure the package in `config/auto-swagger.php` according to your needs.
3. Create a directory to store the generated Swagger documentation:

```bash
mkdir -p public/swagger
```

### Simple Controller Example

Here's a simple example of documenting a controller:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Laravel\AutoSwagger\Attributes\ApiTag;
use Laravel\AutoSwagger\Attributes\ApiOperation;

#[ApiTag(name: 'Products', description: 'Product management')]
class ProductController extends Controller
{
    #[ApiOperation(
        summary: 'List all products',
        description: 'Returns a list of all available products',
        responses: [
            '200' => [
                'description' => 'A list of products',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'array',
                            'items' => [
                                '$ref' => '#/components/schemas/Product'
                            ]
                        ]
                    ]
                ]
            ]
        ]
    )]
    public function index()
    {
        return Product::all();
    }
}
```

### Simple Model Example

Here's a simple example of documenting a model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\AutoSwagger\Attributes\ApiModel;
use Laravel\AutoSwagger\Attributes\ApiProperty;

#[ApiModel(description: 'Product information')]
class Product extends Model
{
    #[ApiProperty(type: 'integer', description: 'The unique identifier')]
    protected $id;

    #[ApiProperty(type: 'string', description: 'The name of the product', example: 'iPhone 14 Pro')]
    protected $name;

    #[ApiProperty(type: 'number', format: 'float', description: 'The price of the product', example: 999.99)]
    protected $price;

    #[ApiProperty(type: 'string', description: 'The description of the product', nullable: true)]
    protected $description;
}
```

## Advanced Examples

### Complex Controller with Request Body

```php
<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Laravel\AutoSwagger\Attributes\ApiTag;
use Laravel\AutoSwagger\Attributes\ApiOperation;

#[ApiTag(name: 'Orders', description: 'Order management')]
class OrderController extends Controller
{
    #[ApiOperation(
        summary: 'Create a new order',
        description: 'Create a new order with provided items',
        responses: [
            '201' => [
                'description' => 'Order created successfully',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/Order'
                        ]
                    ]
                ]
            ],
            '400' => [
                'description' => 'Invalid input'
            ],
            '422' => [
                'description' => 'Validation error'
            ]
        ],
        tags: ['Orders', 'Payments']
    )]
    public function store(Request $request)
    {
        // Implementation
    }

    #[ApiOperation(
        summary: 'Get order by ID',
        description: 'Returns the order with the specified ID',
        parameters: [
            [
                'name' => 'id',
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => 'integer'
                ],
                'description' => 'Order ID'
            ],
            [
                'name' => 'include',
                'in' => 'query',
                'required' => false,
                'schema' => [
                    'type' => 'string',
                    'enum' => ['items', 'customer', 'payment']
                ],
                'description' => 'Related resources to include'
            ]
        ],
        responses: [
            '200' => [
                'description' => 'Successful operation',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/Order'
                        ]
                    ]
                ]
            ],
            '404' => [
                'description' => 'Order not found'
            ]
        ]
    )]
    public function show($id, Request $request)
    {
        // Implementation
    }
}
```

### Complex Model with Relationships

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\AutoSwagger\Attributes\ApiModel;
use Laravel\AutoSwagger\Attributes\ApiProperty;

#[ApiModel(description: 'Order information')]
class Order extends Model
{
    #[ApiProperty(type: 'integer', description: 'The unique identifier')]
    protected $id;

    #[ApiProperty(type: 'integer', description: 'The customer ID', required: true)]
    protected $customer_id;

    #[ApiProperty(type: 'string', description: 'The status of the order', enum: ['pending', 'processing', 'completed', 'cancelled'])]
    protected $status;

    #[ApiProperty(type: 'number', format: 'float', description: 'The total amount', example: 123.45)]
    protected $total;

    #[ApiProperty(type: 'array', description: 'Order items', items: [
        '$ref' => '#/components/schemas/OrderItem'
    ])]
    protected $items;

    #[ApiProperty(type: 'object', description: 'Customer information', properties: [
        'id' => ['type' => 'integer'],
        'name' => ['type' => 'string'],
        'email' => ['type' => 'string', 'format' => 'email']
    ])]
    protected $customer;

    #[ApiProperty(type: 'string', format: 'date-time', description: 'When the order was created')]
    protected $created_at;

    #[ApiProperty(type: 'string', format: 'date-time', description: 'When the order was last updated')]
    protected $updated_at;
}
```

### Using Enum Values

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\AutoSwagger\Attributes\ApiModel;
use Laravel\AutoSwagger\Attributes\ApiProperty;

#[ApiModel(description: 'Subscription information')]
class Subscription extends Model
{
    #[ApiProperty(type: 'string', description: 'The type of subscription', enum: ['free', 'basic', 'premium', 'enterprise'])]
    protected $type;

    #[ApiProperty(type: 'string', description: 'The billing cycle', enum: ['monthly', 'quarterly', 'annually'])]
    protected $billing_cycle;
}
```

### Documenting File Uploads

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\AutoSwagger\Attributes\ApiTag;
use Laravel\AutoSwagger\Attributes\ApiOperation;

#[ApiTag(name: 'Files', description: 'File upload endpoints')]
class FileController extends Controller
{
    #[ApiOperation(
        summary: 'Upload a file',
        description: 'Upload a new file to the system',
        responses: [
            '201' => [
                'description' => 'File uploaded successfully'
            ],
            '400' => [
                'description' => 'Invalid file'
            ]
        ],
        parameters: [
            [
                'name' => 'file',
                'in' => 'formData',
                'required' => true,
                'type' => 'file',
                'description' => 'The file to upload'
            ],
            [
                'name' => 'description',
                'in' => 'formData',
                'required' => false,
                'type' => 'string',
                'description' => 'File description'
            ]
        ]
    )]
    public function upload(Request $request)
    {
        // Implementation
    }
}
```

## Best Practices

### 1. Group Related APIs with Tags

Use consistent tags to group related endpoints. This improves the organization in the Swagger UI.

```php
#[ApiTag(name: 'User Management', description: 'APIs for managing users')]
class UserController extends Controller
{
    // Controller methods
}
```

### 2. Provide Detailed Descriptions

Include comprehensive descriptions for both your APIs and models to help other developers understand how to use them.

```php
#[ApiOperation(
    summary: 'Create user',
    description: 'Creates a new user account with the provided information. An email will be sent to verify the account.'
)]
public function store(Request $request)
{
    // Implementation
}
```

### 3. Document All Possible Responses

Document all possible response types, including error responses, to provide a complete picture of your API behavior.

```php
#[ApiOperation(
    summary: 'Delete user',
    responses: [
        '204' => [
            'description' => 'User deleted successfully'
        ],
        '404' => [
            'description' => 'User not found'
        ],
        '403' => [
            'description' => 'Forbidden - Insufficient permissions'
        ]
    ]
)]
public function destroy($id)
{
    // Implementation
}
```

### 4. Use Examples

Provide examples for request bodies and responses to make your API documentation more helpful.

```php
#[ApiProperty(type: 'string', description: 'User email address', example: 'john.doe@example.com')]
protected $email;
```

### 5. Keep Documentation in Sync with Code

Make updating the documentation part of your development process to ensure it stays in sync with your code.

## Troubleshooting

### Issue: Documentation Not Generating

If your documentation isn't generating properly, check the following:

1. Ensure you have PHP 8.0+ installed (required for attributes)
2. Make sure your controllers and models are in the paths specified in the config
3. Check that your routes are defined properly in Laravel
4. Try using absolute paths in your config file

### Issue: Missing Endpoints

If endpoints are missing from your documentation:

1. Ensure the controller has the `ApiTag` attribute
2. Ensure the method has the `ApiOperation` attribute
3. Check that the route is defined in Laravel's routes
4. Verify that the route's controller action matches the method name

### Issue: Missing Models

If models are missing from your documentation:

1. Ensure the model has the `ApiModel` attribute
2. Ensure model properties have the `ApiProperty` attribute
3. Check that the model is in the paths specified in the config

### Issue: Custom Formatting Not Working

If your custom formatting isn't working:

1. Check the OpenAPI 3.0 specification for correct format strings
2. Make sure you're using the right attribute parameters
3. Regenerate the documentation after making changes
