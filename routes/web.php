<?php

use Illuminate\Support\Facades\Route;
use Laravel\AutoSwagger\Http\Controllers\SwaggerController;

Route::group([
    'prefix' => config('auto-swagger.route_prefix', 'api/documentation'),
    'middleware' => config('auto-swagger.middleware', ['web']),
], function () {
    Route::get('/', [SwaggerController::class, 'index'])->name('swagger.index');
    Route::get('/json', [SwaggerController::class, 'json'])->name('swagger.json');
});
