# Laravel Auto Swagger - Example Implementation

This example demonstrates how to integrate Laravel Auto Swagger into a simple blogging application. 

## Example Blog Application

Consider a simple blog application with the following resources:
- Posts
- Comments
- Users

## Project Structure

```
app/
├── Http/
│   └── Controllers/
│       ├── PostController.php
│       ├── CommentController.php
│       └── UserController.php
├── Models/
│   ├── Post.php
│   ├── Comment.php
│   └── User.php
```

## Step 1: Install Laravel Auto Swagger

```bash
composer require laravel/auto-swagger
php artisan vendor:publish --tag=auto-swagger-config
```

## Step 2: Configure Auto Swagger

Edit `config/auto-swagger.php`:

```php
return [
    'title' => 'Blog API',
    'description' => 'API for a simple blog application',
    'version' => '1.0.0',
    
    'output_file' => public_path('swagger/swagger.json'),
    'output_folder' => public_path('swagger'),
    
    'route_prefix' => 'api-docs',
    'middleware' => ['web'],
    
    'scan' => [
        'controllers_path' => app_path('Http/Controllers'),
        'models_path' => app_path('Models'),
    ],
];
```

## Step 3: Document Your Models

### User Model (app/Models/User.php)

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\AutoSwagger\Attributes\ApiModel;
use Laravel\AutoSwagger\Attributes\ApiProperty;

#[ApiModel(description: 'User account information')]
class User extends Authenticatable
{
    use Notifiable;

    #[ApiProperty(type: 'integer', description: 'The unique identifier for the user')]
    public $id;

    #[ApiProperty(type: 'string', description: 'The name of the user', example: 'John Doe')]
    public $name;

    #[ApiProperty(type: 'string', description: 'The email address of the user', example: 'john@example.com')]
    public $email;

    #[ApiProperty(type: 'string', description: 'The hashed password', example: 'password123', format: 'password')]
    protected $password;

    #[ApiProperty(type: 'string', format: 'date-time', description: 'When the user was created')]
    public $created_at;

    #[ApiProperty(type: 'string', format: 'date-time', description: 'When the user was last updated')]
    public $updated_at;
}
```

### Post Model (app/Models/Post.php)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\AutoSwagger\Attributes\ApiModel;
use Laravel\AutoSwagger\Attributes\ApiProperty;

#[ApiModel(description: 'Blog post information')]
class Post extends Model
{
    #[ApiProperty(type: 'integer', description: 'The unique identifier for the post')]
    public $id;

    #[ApiProperty(type: 'integer', description: 'The ID of the user who created the post')]
    public $user_id;

    #[ApiProperty(type: 'string', description: 'The title of the post', example: 'My Awesome Post')]
    public $title;

    #[ApiProperty(type: 'string', description: 'The content of the post', example: 'This is the content of my awesome post.')]
    public $content;

    #[ApiProperty(type: 'boolean', description: 'Whether the post is published or draft', example: true)]
    public $is_published;

    #[ApiProperty(type: 'string', format: 'date-time', description: 'When the post was created')]
    public $created_at;

    #[ApiProperty(type: 'string', format: 'date-time', description: 'When the post was last updated')]
    public $updated_at;
}
```

### Comment Model (app/Models/Comment.php)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\AutoSwagger\Attributes\ApiModel;
use Laravel\AutoSwagger\Attributes\ApiProperty;

#[ApiModel(description: 'Comment information')]
class Comment extends Model
{
    #[ApiProperty(type: 'integer', description: 'The unique identifier for the comment')]
    public $id;

    #[ApiProperty(type: 'integer', description: 'The ID of the post this comment belongs to')]
    public $post_id;

    #[ApiProperty(type: 'integer', description: 'The ID of the user who wrote the comment')]
    public $user_id;

    #[ApiProperty(type: 'string', description: 'The content of the comment', example: 'Great post!')]
    public $content;

    #[ApiProperty(type: 'string', format: 'date-time', description: 'When the comment was created')]
    public $created_at;

    #[ApiProperty(type: 'string', format: 'date-time', description: 'When the comment was last updated')]
    public $updated_at;
}
```

## Step 4: Document Your Controllers

### PostController (app/Http/Controllers/PostController.php)

```php
<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Laravel\AutoSwagger\Attributes\ApiTag;
use Laravel\AutoSwagger\Attributes\ApiOperation;

#[ApiTag(name: 'Posts', description: 'Blog post management')]
class PostController extends Controller
{
    #[ApiOperation(
        summary: 'List all posts',
        description: 'Retrieve a paginated list of all blog posts',
        responses: [
            '200' => [
                'description' => 'A list of posts',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'data' => [
                                    'type' => 'array',
                                    'items' => [
                                        '$ref' => '#/components/schemas/Post'
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
            ]
        ],
        parameters: [
            [
                'name' => 'page',
                'in' => 'query',
                'schema' => ['type' => 'integer'],
                'description' => 'Page number'
            ],
            [
                'name' => 'per_page',
                'in' => 'query',
                'schema' => ['type' => 'integer'],
                'description' => 'Items per page'
            ]
        ]
    )]
    public function index(Request $request)
    {
        return Post::paginate($request->per_page ?? 15);
    }
    
    #[ApiOperation(
        summary: 'Get a post',
        description: 'Retrieve a specific blog post by its ID',
        responses: [
            '200' => [
                'description' => 'The post',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/Post'
                        ]
                    ]
                ]
            ],
            '404' => [
                'description' => 'Post not found'
            ]
        ],
        parameters: [
            [
                'name' => 'id',
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'integer'],
                'description' => 'Post ID'
            ]
        ]
    )]
    public function show($id)
    {
        return Post::findOrFail($id);
    }
    
    #[ApiOperation(
        summary: 'Create a post',
        description: 'Create a new blog post',
        responses: [
            '201' => [
                'description' => 'Post created successfully',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/Post'
                        ]
                    ]
                ]
            ],
            '422' => [
                'description' => 'Validation error'
            ]
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_published' => 'boolean'
        ]);
        
        return Post::create($validated);
    }
    
    #[ApiOperation(
        summary: 'Update a post',
        description: 'Update an existing blog post',
        responses: [
            '200' => [
                'description' => 'Post updated successfully',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/Post'
                        ]
                    ]
                ]
            ],
            '404' => [
                'description' => 'Post not found'
            ],
            '422' => [
                'description' => 'Validation error'
            ]
        ],
        parameters: [
            [
                'name' => 'id',
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'integer'],
                'description' => 'Post ID'
            ]
        ]
    )]
    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);
        
        $validated = $request->validate([
            'title' => 'string|max:255',
            'content' => 'string',
            'is_published' => 'boolean'
        ]);
        
        $post->update($validated);
        
        return $post;
    }
    
    #[ApiOperation(
        summary: 'Delete a post',
        description: 'Delete an existing blog post',
        responses: [
            '204' => [
                'description' => 'Post deleted successfully'
            ],
            '404' => [
                'description' => 'Post not found'
            ]
        ],
        parameters: [
            [
                'name' => 'id',
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'integer'],
                'description' => 'Post ID'
            ]
        ]
    )]
    public function destroy($id)
    {
        $post = Post::findOrFail($id);
        $post->delete();
        
        return response()->noContent();
    }
}
```

## Step 5: Generate the Documentation

```bash
php artisan swagger:generate
```

## Step 6: View the Documentation

Visit `http://your-app-url/api-docs` in your browser to see the generated Swagger UI with your API documentation.

## Complete Routes File (routes/api.php)

```php
<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::apiResource('posts', PostController::class);
Route::apiResource('posts.comments', CommentController::class)->shallow();
Route::apiResource('users', UserController::class);
```

## Testing Your API with Swagger UI

1. Open your browser and navigate to `http://your-app-url/api-docs`
2. You'll see the Swagger UI interface with your documented endpoints
3. Click on any endpoint to expand it and view its details
4. Try out endpoints directly from the UI by clicking the "Try it out" button
5. Fill in any required parameters and click "Execute" to make a real API call

This example demonstrates how to document a simple RESTful API using Laravel Auto Swagger. You can extend this example with more complex responses, request bodies, and security schemes as needed for your application.
