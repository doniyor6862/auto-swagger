<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductStoreRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Laravel\AutoSwagger\Attributes\ApiTag;

#[ApiTag(name: 'Products', description: 'Product management endpoints')]
class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     *
     * This endpoint retrieves a paginated list of all products.
     * You can optionally filter by category and sort by different fields.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $query = Product::query();
        
        // Apply optional filters
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        
        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }
        
        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);
        
        // Paginate results
        $products = $query->paginate($request->input('per_page', 15));
        
        return ProductResource::collection($products);
    }

    /**
     * Store a newly created product in storage.
     *
     * This endpoint creates a new product using the provided data.
     *
     * @param  \App\Http\Requests\ProductStoreRequest  $request
     * @return \App\Http\Resources\ProductResource
     */
    public function store(ProductStoreRequest $request)
    {
        $product = new Product();
        $product->fill($request->validated());
        
        // Handle image upload if provided
        if ($request->hasFile('image')) {
            $product->image = $request->file('image')->store('products', 'public');
        }
        
        $product->save();
        
        return new ProductResource($product);
    }

    /**
     * Display the specified product.
     *
     * This endpoint retrieves a specific product by its ID.
     *
     * @param  int  $id
     * @return \App\Http\Resources\ProductResource
     */
    public function show($id)
    {
        $product = Product::with(['category', 'tags'])->findOrFail($id);
        
        return new ProductResource($product);
    }

    /**
     * Update the specified product in storage.
     *
     * This endpoint updates an existing product with the provided data.
     *
     * @param  \App\Http\Requests\ProductStoreRequest  $request
     * @param  int  $id
     * @return \App\Http\Resources\ProductResource
     */
    public function update(ProductStoreRequest $request, $id)
    {
        $product = Product::findOrFail($id);
        $product->fill($request->validated());
        
        // Handle image upload if provided
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            
            $product->image = $request->file('image')->store('products', 'public');
        }
        
        $product->save();
        
        return new ProductResource($product);
    }

    /**
     * Remove the specified product from storage.
     *
     * This endpoint deletes a product by its ID.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        
        // Delete image if exists
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        
        $product->delete();
        
        return response()->noContent();
    }
}
