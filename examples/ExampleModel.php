<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\AutoSwagger\Attributes\ApiModel;
use Laravel\AutoSwagger\Attributes\ApiProperty;

#[ApiModel(description: 'Product information and details')]
class Product extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'price',
        'description',
        'category_id',
    ];

    /**
     * The primary key for the model.
     */
    #[ApiProperty(type: 'integer', description: 'The unique identifier for the product')]
    public $id;

    /**
     * The name of the product
     */
    #[ApiProperty(type: 'string', description: 'The name of the product', example: 'iPhone 14 Pro')]
    public $name;

    /**
     * The price of the product
     */
    #[ApiProperty(type: 'number', format: 'float', description: 'The price of the product in USD', example: 999.99)]
    public $price;

    /**
     * The description of the product
     */
    #[ApiProperty(type: 'string', description: 'Detailed description of the product', example: 'The latest iPhone with a stunning camera and powerful performance', nullable: true)]
    public $description;

    /**
     * The category ID of the product
     */
    #[ApiProperty(type: 'integer', description: 'The category ID this product belongs to', example: 1, nullable: true)]
    public $category_id;

    /**
     * The creation timestamp
     */
    #[ApiProperty(type: 'string', format: 'date-time', description: 'When the product was created', nullable: true)]
    public $created_at;

    /**
     * The update timestamp
     */
    #[ApiProperty(type: 'string', format: 'date-time', description: 'When the product was last updated', nullable: true)]
    public $updated_at;
}
