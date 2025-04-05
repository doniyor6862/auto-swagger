<?php

namespace Laravel\AutoSwagger\Services;

use Illuminate\Support\Str;
use Laravel\AutoSwagger\Helpers\PathParameterExtractor;

/**
 * Helper service to fix path parameter declarations in Swagger documentation
 */
class PathParameterFixer
{
    /**
     * Fix path parameters in the OpenAPI document
     *
     * @param array &$openApiDoc The OpenAPI document being built
     * @return void
     */
    public static function fixPathParameters(array &$openApiDoc): void
    {
        if (empty($openApiDoc['paths'])) {
            return;
        }
        
        foreach ($openApiDoc['paths'] as $path => &$pathItem) {
            // Skip paths without parameters
            if (!Str::contains($path, '{')) {
                continue;
            }
            
            // Process each operation (GET, POST, etc.)
            foreach ($pathItem as &$operation) {
                if (!is_array($operation)) {
                    continue;
                }
                
                // Use our helper to add missing path parameters
                PathParameterExtractor::addMissingPathParameters($path, $operation);
            }
        }
    }
}
