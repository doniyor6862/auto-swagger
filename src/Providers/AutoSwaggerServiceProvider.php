<?php

namespace Laravel\AutoSwagger\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\AutoSwagger\Console\Commands\GenerateSwaggerCommand;
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

        $this->app->singleton(SwaggerGenerator::class, function ($app) {
            return new SwaggerGenerator(config('auto-swagger'));
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
