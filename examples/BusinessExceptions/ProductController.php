<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductNotFoundException;
use App\Models\Product;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Laravel\AutoSwagger\Attributes\ApiException;
use Laravel\AutoSwagger\Attributes\ApiOperation;
use Laravel\AutoSwagger\Attributes\ApiResponse;
use Laravel\AutoSwagger\Attributes\ApiTag;

/**
 * Example controller demonstrating business exception documentation
 */
#[ApiTag(name: 'Products', description: 'Product operations')]
class ProductController extends Controller
{
    /**
     * Show product details
     *
     * @param int $id Product ID
     * @throws ProductNotFoundException If the product doesn't exist
     */
    #[ApiOperation(summary: 'Get product details', description: 'Get detailed information about a specific product')]
    #[ApiResponse(statusCode: 200, description: 'Product details retrieved successfully')]
    #[ApiException(
        exception: ProductNotFoundException::class,
        statusCode: 409,
        description: 'The requested product does not exist',
        schema: [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'example' => 'Product with ID 123 not found'],
                'error_code' => ['type' => 'string', 'example' => 'PRODUCT_NOT_FOUND'],
                'exception' => ['type' => 'string', 'example' => 'ProductNotFoundException'],
                'product_id' => ['type' => 'integer', 'example' => 123]
            ]
        ]
    )]
    public function show($id)
    {
        $product = Product::find($id);
        
        if (!$product) {
            throw new ProductNotFoundException($id);
        }
        
        return new ProductResource($product);
    }
    
    /**
     * Add product to cart
     *
     * @param int $id Product ID
     * @param Request $request Contains 'quantity' parameter
     * @throws ProductNotFoundException If the product doesn't exist
     * @throws InsufficientStockException If there isn't enough stock available
     */
    #[ApiOperation(summary: 'Add product to cart', description: 'Add a specific quantity of a product to the shopping cart')]
    #[ApiResponse(statusCode: 200, description: 'Product added to cart successfully')]
    #[ApiException(
        exception: ProductNotFoundException::class,
        statusCode: 409,
        description: 'The requested product does not exist',
        schema: [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'example' => 'Product with ID 123 not found'],
                'error_code' => ['type' => 'string', 'example' => 'PRODUCT_NOT_FOUND'],
                'exception' => ['type' => 'string', 'example' => 'ProductNotFoundException'],
                'product_id' => ['type' => 'integer', 'example' => 123]
            ]
        ]
    )]
    #[ApiException(
        exception: InsufficientStockException::class,
        statusCode: 409,
        description: 'There is not enough product in stock to fulfill this request',
        schema: [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'example' => 'Insufficient stock for product 123. Requested: 10, Available: 5'],
                'error_code' => ['type' => 'string', 'example' => 'INSUFFICIENT_STOCK'],
                'exception' => ['type' => 'string', 'example' => 'InsufficientStockException'],
                'product_id' => ['type' => 'integer', 'example' => 123],
                'requested_quantity' => ['type' => 'integer', 'example' => 10],
                'available_quantity' => ['type' => 'integer', 'example' => 5],
            ]
        ]
    )]
    public function addToCart($id, Request $request)
    {
        $quantity = $request->input('quantity', 1);
        $product = Product::find($id);
        
        if (!$product) {
            throw new ProductNotFoundException($id);
        }
        
        if ($product->stock < $quantity) {
            throw new InsufficientStockException(
                $id,
                $quantity,
                $product->stock
            );
        }
        
        // Add to cart logic here...
        
        return response()->json([
            'success' => true,
            'message' => "{$quantity} units of product {$id} added to cart"
        ]);
    }
}
