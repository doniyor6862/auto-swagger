<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Swagger Basic Info
    |--------------------------------------------------------------------------
    */
    'title' => env('APP_NAME', 'Laravel') . ' API',
    'description' => 'API Documentation',
    'version' => '1.0.0',
    
    /*
    |--------------------------------------------------------------------------
    | Output Settings
    |--------------------------------------------------------------------------
    */
    'output_file' => public_path('swagger/swagger.json'),
    'output_folder' => public_path('swagger'),
    
    /*
    |--------------------------------------------------------------------------
    | Route Settings
    |--------------------------------------------------------------------------
    */
    'route_prefix' => 'api/documentation',
    'middleware' => [
        'web',
        // Add any additional middleware here (e.g., 'auth')
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Scanning Settings
    |--------------------------------------------------------------------------
    */
    'scan' => [
        'controllers_path' => app_path('Http/Controllers'),
        'models_path' => app_path('Models'),
        'include_patterns' => [
            app_path('Http/Controllers/*.php'),
            app_path('Http/Controllers/**/*.php'),
        ],
        'exclude_patterns' => [],
        'use_phpdoc' => true, // Enable or disable PHPDoc scanning
        'require_api_swagger' => false, // If true, only document methods/classes with ApiSwagger attribute
        'analyze_routes' => true, // If true, analyze Laravel routes to extract API documentation
    ],
    
    /*
    |--------------------------------------------------------------------------
    | API Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix used for API routes. This is used when analyzing routes to
    | determine which ones should be included in the documentation.
    |
    */
    'api_prefix' => 'api',
    
    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    */
    'ui' => [
        'enabled' => true,
        'theme' => 'default', // 'default', 'dark', 'light'
        'persist_authorization' => true,
        'display_request_duration' => true,
        'doc_expansion' => 'list', // 'list', 'full', 'none'
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Additional Settings
    |--------------------------------------------------------------------------
    */
    'securityDefinitions' => [
        'bearerAuth' => [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ],
    ],
    
    'security' => [
        ['bearerAuth' => []],
    ],
    
    'servers' => [
        [
            'url' => env('APP_URL', 'http://localhost'),
            'description' => 'Default Server',
        ],
    ],
];
