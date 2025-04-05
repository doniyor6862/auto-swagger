<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * User model representing a system user
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $role
 * @property int $department_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class User extends Model
{
    /**
     * User can have many posts
     * 
     * @return HasMany<\App\Models\Post>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
    
    /**
     * User belongs to a department
     * 
     * @return BelongsTo<\App\Models\Department>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
