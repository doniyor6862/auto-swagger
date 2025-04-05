<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Product model representing items for sale in the store
 *
 * @property int $id The unique identifier for the product
 * @property string $name The name of the product
 * @property string|null $description The detailed description of the product
 * @property float $price The price of the product in USD
 * @property int $category_id The ID of the category this product belongs to
 * @property bool $is_featured Whether this product is featured on the homepage
 * @property string|null $image The path to the product image file
 * @property \Carbon\Carbon $created_at When the product was created
 * @property \Carbon\Carbon $updated_at When the product was last updated
 * 
 * @property-read Category $category The category this product belongs to
 * @property-read Collection|Review[] $reviews The reviews for this product
 * 
 * @method static \Illuminate\Database\Eloquent\Builder|Product featured() Scope for featured products
 * @method static \Illuminate\Database\Eloquent\Builder|Product inCategory($categoryId) Scope for products in a category
 */
class Product extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'price',
        'category_id',
        'is_featured',
        'image',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'float',
        'is_featured' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the category that the product belongs to
     *
     * @return BelongsTo
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the reviews for the product
     *
     * @return HasMany
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Scope for featured products
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for products in a specific category
     */
    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }
}
