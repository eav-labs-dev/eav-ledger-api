<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogItemController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', HealthController::class)->name('health');

Route::prefix('v1/auth')->name('auth.')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});

Route::middleware('auth:sanctum')
    ->prefix('v1')
    ->group(function (): void {
        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('catalog-items', CatalogItemController::class)
            ->parameters(['catalog-items' => 'catalog_item']);
    });
