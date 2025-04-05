<?php

namespace Laravel\AutoSwagger\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;

class SwaggerController extends Controller
{
    /**
     * Display the Swagger UI page.
     */
    public function index()
    {
        $swaggerJsonUrl = url('swagger/swagger.json');
        
        return view('auto-swagger::index', [
            'title' => config('auto-swagger.title', 'API Documentation'),
            'swaggerJsonUrl' => $swaggerJsonUrl,
            'uiSettings' => config('auto-swagger.ui'),
        ]);
    }

    /**
     * Get the Swagger JSON file.
     */
    public function json()
    {
        $jsonPath = config('auto-swagger.output_file');
        
        if (!File::exists($jsonPath)) {
            return response()->json(['error' => 'Swagger documentation not generated yet.'], 404);
        }
        
        $content = File::get($jsonPath);
        
        return response($content)->header('Content-Type', 'application/json');
    }
}
