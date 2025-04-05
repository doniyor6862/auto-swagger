<?php

namespace Laravel\AutoSwagger\Services;

use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use ReflectionClass;
use ReflectionMethod;

class RouteScanner
{
    /**
     * Get all routes that should be processed for the API documentation.
     */
    public function getRoutes(): Collection
    {
        return collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(function (Route $route) {
                // Filter out routes that don't have a controller action
                if (!$route->getActionName() || $route->getActionName() === 'Closure') {
                    return false;
                }

                // Get controller and method
                $action = $route->getAction();
                
                if (!isset($action['controller'])) {
                    return false;
                }
                
                return true;
            });
    }

    /**
     * Extract controller and method name from a route.
     */
    public function extractControllerAndMethodFromRoute(Route $route): array
    {
        $action = $route->getAction();
        
        if (!isset($action['controller'])) {
            return [null, null];
        }
        
        $parts = explode('@', $action['controller']);
        
        if (count($parts) !== 2) {
            return [null, null];
        }
        
        return [$parts[0], $parts[1]];
    }

    /**
     * Get reflection method for a controller action.
     */
    public function getReflectionMethod(string $controller, string $method): ?ReflectionMethod
    {
        if (!class_exists($controller)) {
            return null;
        }
        
        $reflectionClass = new ReflectionClass($controller);
        
        if (!$reflectionClass->hasMethod($method)) {
            return null;
        }
        
        return $reflectionClass->getMethod($method);
    }

    /**
     * Convert Laravel route parameters to OpenAPI parameters.
     */
    public function getPathParameters(string $routePath): array
    {
        $parameters = [];
        $pattern = '/{([^}]+)}/';
        
        if (preg_match_all($pattern, $routePath, $matches)) {
            foreach ($matches[1] as $match) {
                // Remove optional parameter indicator (?)
                $paramName = rtrim($match, '?');
                
                // Check if parameter has custom regex constraint
                if (strpos($paramName, ':') !== false) {
                    $parts = explode(':', $paramName, 2);
                    $paramName = $parts[0];
                }
                
                $parameters[] = [
                    'name' => $paramName,
                    'in' => 'path',
                    'required' => !str_ends_with($match, '?'),
                    'schema' => [
                        'type' => 'string',
                    ],
                    'description' => 'Route parameter ' . $paramName,
                ];
            }
        }
        
        return $parameters;
    }

    /**
     * Normalize a route path for OpenAPI.
     */
    public function normalizePath(string $path): string
    {
        // Convert Laravel route parameters format to OpenAPI format
        $path = preg_replace('/{([^}]+\?)}/', '{$1}', $path);
        
        // Remove regex constraints from parameters
        $path = preg_replace('/{([^:}]+):[^}]+}/', '{$1}', $path);
        
        // Add leading slash if missing
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        
        return $path;
    }
}
