<?php

namespace Laravel\AutoSwagger\Helpers;

use Illuminate\Support\Str;

/**
 * Helper class for extracting and managing path parameters from route URIs
 */
class PathParameterExtractor
{
    /**
     * Extract path parameters from a route path
     *
     * @param string $path The normalized path string (e.g. /users/{id})
     * @return array Array of parameter names (e.g. ['id'])
     */
    public static function extractPathParameters(string $path): array
    {
        $matches = [];
        preg_match_all('/\{([^\}]+)\}/', $path, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Create path parameter schema for OpenAPI documentation
     *
     * @param string $paramName The parameter name
     * @return array OpenAPI parameter definition
     */
    public static function createPathParameterSchema(string $paramName): array
    {
        return [
            'name' => $paramName,
            'in' => 'path',
            'required' => true,
            'description' => 'ID of the ' . Str::singular($paramName),
            'schema' => ['type' => 'string']
        ];
    }

    /**
     * Add missing path parameters to an operation
     *
     * @param string $path The path with parameters like /users/{id}
     * @param array &$operation The operation array to add parameters to
     * @return void
     */
    public static function addMissingPathParameters(string $path, array &$operation): void
    {
        // Extract path parameters from the path
        $pathParams = self::extractPathParameters($path);
        if (empty($pathParams)) {
            return;
        }
        
        // Initialize parameters array if not set
        if (!isset($operation['parameters'])) {
            $operation['parameters'] = [];
        }
        
        // Get existing parameter names
        $existingParamNames = [];
        foreach ($operation['parameters'] as $param) {
            if (isset($param['in']) && $param['in'] === 'path' && isset($param['name'])) {
                $existingParamNames[] = $param['name'];
            }
        }
        
        // Add missing path parameters
        foreach ($pathParams as $paramName) {
            if (!in_array($paramName, $existingParamNames)) {
                $operation['parameters'][] = self::createPathParameterSchema($paramName);
            }
        }
    }
}
