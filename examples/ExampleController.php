<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Laravel\AutoSwagger\Attributes\ApiOperation;
use Laravel\AutoSwagger\Attributes\ApiParameter;
use Laravel\AutoSwagger\Attributes\ApiRequestBody;
use Laravel\AutoSwagger\Attributes\ApiResponse;
use Laravel\AutoSwagger\Attributes\ApiSecurity;
use Laravel\AutoSwagger\Attributes\ApiTag;

#[ApiTag(name: 'Products', description: 'Product management APIs')]
#[ApiSecurity(name: 'bearerAuth')]
class ProductController extends Controller
{
    /**
     * List all products with optional filtering
     */
    #[ApiOperation(
        summary: 'List all products',
        description: 'Returns a paginated list of all products with optional filtering by category',
    )]
    #[ApiParameter(name: 'page', description: 'Page number', type: 'integer', example: 1)]
    #[ApiParameter(name: 'per_page', description: 'Items per page', type: 'integer', example: 15)]
    #[ApiParameter(name: 'category_id', description: 'Filter by category ID', type: 'integer', required: false)]
    #[ApiResponse(
        statusCode: 200,
        description: 'Successfully retrieved products',
        content: [
            'application/json' => [
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => [
                            'type' => 'array',
                            'items' => [
                                '$ref' => '#/components/schemas/Product'
                            ]
                        ],
                        'meta' => [
                            'type' => 'object',
                            'properties' => [
                                'current_page' => ['type' => 'integer'],
                                'last_page' => ['type' => 'integer'],
                                'per_page' => ['type' => 'integer'],
                                'total' => ['type' => 'integer']
                            ]
                        ]
                    ]
                ]
            ]
        ]
    )]
    public function index(Request $request)
    {
        // Implementation logic here
        return response()->json([
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 15,
                'total' => 0
            ]
        ]);
    }

    /**
     * Get a specific product by ID
     */
    #[ApiOperation(
        summary: 'Get product by ID',
        description: 'Returns detailed information about a specific product',
    )]
    #[ApiParameter(name: 'id', in: 'path', description: 'Product ID', type: 'integer', required: true)]
    #[ApiResponse(
        statusCode: 200,
        description: 'Successfully retrieved product',
        content: [
            'application/json' => [
                'schema' => [
                    '$ref' => '#/components/schemas/Product'
                ]
            ]
        ]
    )]
    #[ApiResponse(statusCode: 404, description: 'Product not found')]
    public function show($id)
    {
        // Implementation logic here
        return response()->json([
            'id' => $id,
            'name' => 'Example Product',
            'price' => 99.99,
            'description' => 'This is an example product',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Create a new product
     */
    #[ApiOperation(
        summary: 'Create a new product',
        description: 'Creates a new product with the provided information',
    )]
    #[ApiRequestBody(
        description: 'Product information',
        content: [
            'application/json' => [
                'schema' => [
                    'required' => ['name', 'price'],
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'example' => 'New Product'
                        ],
                        'price' => [
                            'type' => 'number',
                            'format' => 'float',
                            'example' => 99.99
                        ],
                        'description' => [
                            'type' => 'string',
                            'example' => 'Product description'
                        ],
                        'category_id' => [
                            'type' => 'integer',
                            'example' => 1
                        ]
                    ]
                ]
            ]
        ]
    )]
    #[ApiResponse(
        statusCode: 201,
        description: 'Product created successfully',
        content: [
            'application/json' => [
                'schema' => [
                    '$ref' => '#/components/schemas/Product'
                ]
            ]
        ]
    )]
    #[ApiResponse(statusCode: 422, description: 'Validation error')]
    public function store(Request $request)
    {
        // Implementation logic here
        return response()->json([
            'id' => 1,
            'name' => $request->input('name'),
            'price' => $request->input('price'),
            'description' => $request->input('description'),
            'created_at' => now(),
            'updated_at' => now(),
        ], 201);
    }

    /**
     * Update an existing product
     */
    #[ApiOperation(
        summary: 'Update a product',
        description: 'Updates an existing product with the provided information',
    )]
    #[ApiParameter(name: 'id', in: 'path', description: 'Product ID', type: 'integer', required: true)]
    #[ApiRequestBody(
        description: 'Product information to update',
        content: [
            'application/json' => [
                'schema' => [
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'example' => 'Updated Product Name'
                        ],
                        'price' => [
                            'type' => 'number',
                            'format' => 'float',
                            'example' => 129.99
                        ],
                        'description' => [
                            'type' => 'string',
                            'example' => 'Updated product description'
                        ],
                        'category_id' => [
                            'type' => 'integer',
                            'example' => 2
                        ]
                    ]
                ]
            ]
        ]
    )]
    #[ApiResponse(
        statusCode: 200,
        description: 'Product updated successfully',
        content: [
            'application/json' => [
                'schema' => [
                    '$ref' => '#/components/schemas/Product'
                ]
            ]
        ]
    )]
    #[ApiResponse(statusCode: 404, description: 'Product not found')]
    #[ApiResponse(statusCode: 422, description: 'Validation error')]
    public function update(Request $request, $id)
    {
        // Implementation logic here
        return response()->json([
            'id' => $id,
            'name' => $request->input('name', 'Example Product'),
            'price' => $request->input('price', 99.99),
            'description' => $request->input('description', 'This is an example product'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Delete a product
     */
    #[ApiOperation(
        summary: 'Delete a product',
        description: 'Deletes an existing product',
    )]
    #[ApiParameter(name: 'id', in: 'path', description: 'Product ID', type: 'integer', required: true)]
    #[ApiResponse(statusCode: 204, description: 'Product deleted successfully')]
    #[ApiResponse(statusCode: 404, description: 'Product not found')]
    public function destroy($id)
    {
        // Implementation logic here
        return response()->noContent();
    }
}
