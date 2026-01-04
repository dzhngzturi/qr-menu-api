<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use Closure;
use Illuminate\Http\Request;

class RestaurantAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // resolve.restaurant ТРЯБВА да е пуснат преди този middleware
        $restaurant = $request->attributes->get('restaurant');

        if (!$restaurant instanceof Restaurant) {
            // fallback (ако някъде си забравил resolve.restaurant)
            $slug = (string) $request->query('restaurant', '');
            if ($slug === '') {
                return response()->json(['message' => 'Restaurant context is required.'], 422);
            }

            $restaurant = Restaurant::where('slug', $slug)->first();
            if (!$restaurant) {
                return response()->json(['message' => 'Restaurant not found.'], 404);
            }

            // за всеки случай сетни атрибутите, за да са консистентни
            $request->attributes->set('restaurant', $restaurant);
            $request->attributes->set('restaurant_id', $restaurant->id);
        }

        // супер-админ
        if ((bool) $user->is_admin) {
            return $next($request);
        }

        // roles в pivot: owner / manager
        $hasAccess = $user->restaurants()
            ->whereKey($restaurant->id)
            ->wherePivotIn('role', ['owner', 'manager'])
            ->exists();

        if (!$hasAccess) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
