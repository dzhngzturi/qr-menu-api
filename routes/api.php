<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DishController;
use App\Http\Controllers\Api\AllergenController; // ⬅️ ВАЖНО: добавен импорт

// Платформа (супер-админ)
use App\Http\Controllers\Api\Platform\RestaurantController as PlatformRestaurantController;
use App\Http\Controllers\Api\Platform\RestaurantUserController as PlatformRestaurantUserController;

/*
|--------------------------------------------------------------------------
| Public (no auth) — изискват ?restaurant=slug
|--------------------------------------------------------------------------
*/
Route::middleware(['resolve.restaurant', 'throttle:60,1'])->group(function () {
    // Комбинирано меню (категории + ястия)
    Route::get('menu', [MenuController::class, 'index']);
    Route::get('/menu/{restaurant:slug}', [MenuController::class, 'index']);
    Route::get('/menu/{restaurant:slug}/categories', [MenuController::class, 'categories']);
    Route::get('/menu/{restaurant:slug}/dishes', [MenuController::class, 'dishes']);
    Route::get('/menu/{restaurant:slug}/allergens', [AllergenController::class, 'index']);

    // Отделни публични четения
    Route::get('categories',            [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);

    Route::get('dishes',                [DishController::class, 'index']);
    Route::get('dishes/{dish}',         [DishController::class, 'show']);

    // Публичен списък с алергени за конкретния ресторант (четене)
    Route::get('allergens', [AllergenController::class, 'index']);

});

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/
Route::post('auth/login',  [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get ('auth/me',     [AuthController::class, 'me']);      // четене на профил
    Route::patch('auth/me',    [AuthController::class, 'update']);  // обновяване на профил
});

/*
|--------------------------------------------------------------------------
| Admin CRUD за конкретен ресторант
| (нужни са Bearer token + ?restaurant=slug + право restaurant.admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'resolve.restaurant', 'restaurant.admin'])->group(function () {
    // Категории
    Route::post   ('categories',                 [CategoryController::class, 'store']);
    Route::patch  ('categories/{category}',      [CategoryController::class, 'update']);
    Route::delete ('categories/{category}',      [CategoryController::class, 'destroy']);
    Route::post   ('categories/reorder',         [CategoryController::class, 'reorder']);

    // Ястия
    Route::post   ('dishes',                     [DishController::class, 'store']);
    Route::patch  ('dishes/{dish}',              [DishController::class, 'update']);
    Route::delete ('dishes/{dish}',              [DishController::class, 'destroy']);
    Route::post   ('/dishes/reorder',            [DishController::class, 'reorder']);

    // Алергени (CRUD)
    Route::post   ('allergens',                  [AllergenController::class, 'store']);
    Route::patch  ('allergens/{allergen}',       [AllergenController::class, 'update']);
    Route::delete ('allergens/{allergen}',       [AllergenController::class, 'destroy']);
    Route::post   ('/allergens/reorder',         [AllergenController::class, 'reorder']);

});

/*
|--------------------------------------------------------------------------
| Платформа (супер-админ)
|--------------------------------------------------------------------------
*/
Route::prefix('platform')
    ->middleware(['auth:sanctum', 'superadmin'])
    ->group(function () {
        Route::apiResource('restaurants', PlatformRestaurantController::class);

        Route::get   ('restaurants/{restaurant:id}/users',        [PlatformRestaurantUserController::class, 'index'])
            ->whereNumber('restaurant');
        Route::post  ('restaurants/{restaurant:id}/users',        [PlatformRestaurantUserController::class, 'attach'])
            ->whereNumber('restaurant');
        Route::delete('restaurants/{restaurant:id}/users/{user}', [PlatformRestaurantUserController::class, 'detach'])
            ->whereNumber('restaurant');
    });
