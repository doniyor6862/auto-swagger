<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Resources\UserResource;
use Laravel\AutoSwagger\Attributes\ApiOperation;
use Laravel\AutoSwagger\Attributes\ApiTag;

#[ApiTag(name: 'Users', description: 'User operations')]
class UserController extends Controller
{
    /**
     * Get a user with its relationships
     */
    #[ApiOperation(summary: 'Get user with relationships', description: 'Returns user data including department and posts')]
    public function show($id)
    {
        // Load the user with its relationships
        $user = User::with(['department', 'posts'])->findOrFail($id);
        
        // Return the resource which includes relationship data
        return new UserResource($user);
    }

    /**
     * Get a user with auto-detected relationships
     */
    #[ApiOperation(summary: 'Get user with auto-detected relationships')]
    public function showWithAutoRelations($id)
    {
        // Load the user with its relationships
        $user = User::with(['department', 'posts'])->findOrFail($id);
        
        // Use a resource with auto-detection of all relations
        return new AutoDetectRelationsResource($user);
    }
}
