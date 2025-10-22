<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use Closure;
use Illuminate\Http\Request;

class ResolveRestaurant
{
    public function handle(Request $request, Closure $next)
    {
        $rid = null;
        $restaurant = null;

        // 1) /api/menu/{restaurant:slug}/...  (route model binding по slug)
        $routeRestaurant = $request->route('restaurant');
        if ($routeRestaurant instanceof Restaurant) {
            $restaurant = $routeRestaurant;
            $rid = $restaurant->id;
        }

        // 2) /api/...?...&restaurant=<slug>  (query параметър)
        if (!$rid && $request->filled('restaurant')) {
            $slug = (string) $request->query('restaurant');
            $restaurant = Restaurant::where('slug', $slug)->first();
            $rid = $restaurant?->id;
        }

        // 3) (по желание) /api/...?...&restaurant_id=123
        if (!$rid && $request->filled('restaurant_id')) {
            $rid = (int) $request->query('restaurant_id');
            $restaurant = Restaurant::find($rid);
            $rid = $restaurant?->id; // валидирай
        }

        if (!$rid) {
            // не пращаме 422, защото често идва от забравен параметър на публичен екран
            return response()->json(['message' => 'Restaurant not found'], 404);
        }

        // закачаме към заявката
        $request->attributes->set('restaurant_id', $rid);
        $request->attributes->set('restaurant', $restaurant);

        return $next($request);
    }
}
