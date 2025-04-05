<?php

namespace Laravel\AutoSwagger\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\AutoSwagger\Console\Commands\GenerateSwaggerCommand;
use Laravel\AutoSwagger\Services\PathParameterFixer;
use Laravel\AutoSwagger\Services\SwaggerGenerator;

class AutoSwaggerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/auto-swagger.php', 'auto-swagger'
        );

        $this->app->bind(SwaggerGenerator::class, function ($app) {
            $config = config('auto-swagger');
            return new SwaggerGenerator($config);
        });
        
        // Register the generate:swagger command
        $this->app->bind('command.auto-swagger.generate', function ($app) {
            return new GenerateSwaggerCommand($app->make(SwaggerGenerator::class), $app);
        });
        
        // Hook into the swagger generation process to fix path parameters
        $this->app->resolving(GenerateSwaggerCommand::class, function ($command, $app) {
            $command->onAfterGenerate(function ($openApiDoc) {
                // Fix path parameters in the OpenAPI document
                PathParameterFixer::fixPathParameters($openApiDoc);
                return $openApiDoc;
            });
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateSwaggerCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../../config/auto-swagger.php' => config_path('auto-swagger.php'),
            ], 'auto-swagger-config');

            $this->publishes([
                __DIR__ . '/../../resources/views' => resource_path('views/vendor/auto-swagger'),
            ], 'auto-swagger-views');
        }

        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'auto-swagger');
    }
}
