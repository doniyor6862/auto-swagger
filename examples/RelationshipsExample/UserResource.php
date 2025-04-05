<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\AutoSwagger\Attributes\ApiResource;

/**
 * Resource for User model with explicit relations
 */
#[ApiResource(
    model: User::class,
    description: 'User resource with related data',
    relations: [
        'posts' => PostResource::class,
        'department' => [
            'resource' => DepartmentResource::class,
            'isCollection' => false
        ]
    ]
)]
class UserResource extends JsonResource
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
            'email' => $this->email,
            'role' => $this->role,
            // Load relationships when they're requested
            'department' => $this->whenLoaded('department', function() {
                return new DepartmentResource($this->department);
            }),
            'posts' => $this->whenLoaded('posts', function() {
                return PostResource::collection($this->posts);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
