<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Product
 */
class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'formatted_price' => "$" . number_format($this->price, 2),
            'category' => [
                'id' => $this->category_id,
                'name' => $this->whenLoaded('category', function () {
                    return $this->category->name;
                }),
            ],
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->pluck('name');
            }, []),
            'is_featured' => (bool) $this->is_featured,
            'publish_date' => $this->publish_date ? $this->publish_date->toDateString() : null,
            'image_url' => $this->image ? asset('storage/' . $this->image) : null,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
