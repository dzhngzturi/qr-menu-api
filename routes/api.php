<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DishController;
use App\Http\Controllers\Api\AllergenController;
use App\Http\Middleware\BlockLockedLogin;
use App\Http\Controllers\Api\TelemetryController;

// Платформа (супер-админ)
use App\Http\Controllers\Api\Platform\RestaurantController as PlatformRestaurantController;
use App\Http\Controllers\Api\Platform\RestaurantUserController as PlatformRestaurantUserController;

/*
|--------------------------------------------------------------------------
| Public (no auth) — restaurant context via resolve.restaurant
|--------------------------------------------------------------------------
*/
Route::middleware(['resolve.restaurant', 'throttle:60,1', 'public.cache'])->group(function () {
    // Menu endpoints (public)
    Route::get('menu', [MenuController::class, 'index']);
    Route::get('menu/{restaurant:slug}', [MenuController::class, 'index']);
    Route::get('menu/{restaurant:slug}/categories', [MenuController::class, 'categories']);
    Route::get('menu/{restaurant:slug}/dishes', [MenuController::class, 'dishes']);
    Route::get('menu/{restaurant:slug}/allergens', [AllergenController::class, 'index']);

    // Public reads (ако ги искаш публични)
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);

    Route::get('dishes', [DishController::class, 'index']);
    Route::get('dishes/{dish}', [DishController::class, 'show']);

    Route::get('allergens', [AllergenController::class, 'index']);

    // Telemetry (public)
    Route::post('telemetry', [TelemetryController::class, 'store']);
    Route::post('telemetry/batch', [TelemetryController::class, 'batch']);
});

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/
Route::post('auth/login', [AuthController::class, 'login'])->middleware(BlockLockedLogin::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::patch('auth/me', [AuthController::class, 'update']);
});

/*
|--------------------------------------------------------------------------
| Admin (restaurant-scoped + protected)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->middleware(['auth:sanctum', 'resolve.restaurant', 'restaurant.admin'])
    ->group(function () {
        Route::get('auth/check-restaurant', [AuthController::class, 'checkRestaurant']);


        // ✅ Admin GET (за да не ползваш public endpoints в админ панела)
        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('dishes', [DishController::class, 'index']);
        Route::get('allergens', [AllergenController::class, 'index']);

        // Categories CRUD
        Route::post('categories', [CategoryController::class, 'store']);
        Route::patch('categories/{category}', [CategoryController::class, 'update']);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
        Route::post('categories/reorder', [CategoryController::class, 'reorder']);

        // Dishes CRUD
        Route::post('dishes', [DishController::class, 'store']);
        Route::patch('dishes/{dish}', [DishController::class, 'update']);
        Route::delete('dishes/{dish}', [DishController::class, 'destroy']);
        Route::post('dishes/reorder', [DishController::class, 'reorder']);

        // Allergens CRUD
        Route::post('allergens', [AllergenController::class, 'store']);
        Route::patch('allergens/{allergen}', [AllergenController::class, 'update']);
        Route::delete('allergens/{allergen}', [AllergenController::class, 'destroy']);
        Route::post('allergens/reorder', [AllergenController::class, 'reorder']);

        Route::get('telemetry/overview', [TelemetryController::class, 'overview']);



    });

/*
|--------------------------------------------------------------------------
| Platform (superadmin)
|--------------------------------------------------------------------------
*/
Route::prefix('platform')
    ->middleware(['auth:sanctum', 'superadmin'])
    ->group(function () {
        Route::apiResource('restaurants', PlatformRestaurantController::class);

        Route::get('restaurants/{restaurant:id}/users', [PlatformRestaurantUserController::class, 'index'])
            ->whereNumber('restaurant');

        Route::post('restaurants/{restaurant:id}/users', [PlatformRestaurantUserController::class, 'attach'])
            ->whereNumber('restaurant');

        Route::delete('restaurants/{restaurant:id}/users/{user}', [PlatformRestaurantUserController::class, 'detach'])
            ->whereNumber('restaurant');
    });
