<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use Closure;
use Illuminate\Http\Request;

class RestaurantAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user(); // Sanctum user
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // 1) Опитай да вземеш ресторанта, който ResolveRestaurant може да е сетнал
        //    в request атрибутите (ако твоят middleware го прави).
        $restaurant = $request->attributes->get('restaurant');

        // 2) Ако го нямаме като атрибут, вземи slug-а от query и зареди модела
        if (!$restaurant) {
            $slug = $request->query('restaurant');
            if (!$slug) {
                return response()->json(['message' => 'Restaurant context is required.'], 422);
            }
            $restaurant = Restaurant::where('slug', $slug)->first();
            if (!$restaurant) {
                return response()->json(['message' => 'Restaurant not found.'], 404);
            }
        }

        // 3) Супер админ винаги има достъп
        if ($user->is_admin) {
            return $next($request);
        }

        // 4) Проверка за owner/manager в pivot таблицата restaurant_user
        $hasAccess = $user->restaurants()
            ->where('restaurants.id', $restaurant->id)
            ->wherePivotIn('role', ['owner', 'manager'])
            ->exists();

        if (!$hasAccess) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
