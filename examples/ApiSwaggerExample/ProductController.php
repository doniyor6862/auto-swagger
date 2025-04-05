<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Http\Resources\ProductResource;
use App\Http\Requests\StoreProductRequest;
use Illuminate\Http\Request;
use Laravel\AutoSwagger\Attributes\ApiSwagger;
use Laravel\AutoSwagger\Attributes\ApiOperation;
use Laravel\AutoSwagger\Attributes\ApiResponse;
use Laravel\AutoSwagger\Attributes\ApiTag;

/**
 * Example controller showing the ApiSwagger attribute usage
 */
#[ApiSwagger]
#[ApiTag(name: 'Products', description: 'Product management')]
class ProductController extends Controller
{
    /**
     * List all products 
     */
    #[ApiOperation(summary: 'List all products', description: 'Get a paginated list of all products')]
    #[ApiResponse(statusCode: 200, description: 'List of products retrieved successfully')]
    public function index()
    {
        // This method will be included in documentation because:
        // 1. The controller has ApiSwagger attribute
        // 2. This method has ApiOperation attribute
        
        return ProductResource::collection(Product::paginate(15));
    }
    
    /**
     * Show product details
     */
    #[ApiOperation(summary: 'Get product details', description: 'Get detailed information about a specific product')]
    #[ApiResponse(statusCode: 200, description: 'Product found')]
    #[ApiResponse(statusCode: 404, description: 'Product not found')]
    public function show($id)
    {
        // This method will be included in documentation
        
        $product = Product::findOrFail($id);
        return new ProductResource($product);
    }
    
    /**
     * Create a new product
     */
    #[ApiOperation(summary: 'Create a product', description: 'Create a new product with the provided data')]
    #[ApiResponse(statusCode: 201, description: 'Product created successfully')]
    #[ApiResponse(statusCode: 422, description: 'Validation error')]
    public function store(StoreProductRequest $request)
    {
        // This method will be included in documentation
        
        $product = Product::create($request->validated());
        return new ProductResource($product);
    }
    
    /**
     * Internal method that shouldn't be included in documentation
     */
    #[ApiSwagger(include: false)]
    #[ApiOperation(summary: 'Check inventory levels', description: 'Internal method to verify product inventory')]
    public function checkInventory($id)
    {
        // This method will NOT be included in documentation because:
        // It has ApiSwagger(include: false)
        
        return ['in_stock' => true, 'quantity' => 100];
    }
    
    /**
     * Method with no ApiOperation attribute
     */
    public function internalStats()
    {
        // This method will NOT be included in documentation because:
        // It doesn't have an ApiOperation attribute
        
        return ['total_products' => 1250, 'categories' => 15];
    }
}
