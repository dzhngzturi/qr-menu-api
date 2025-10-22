<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller; 
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RestaurantController extends Controller
{
    public function index(Request $r)
    {
        $q = Restaurant::query()
            ->when($r->filled('search'), fn($qq) =>
                $qq->where('name', 'like', '%'.$r->string('search').'%')
                   ->orWhere('slug', 'like', '%'.$r->string('search').'%'))
            ->orderBy('name');

        return $r->boolean('paginate', true)
            ? $q->paginate((int)$r->input('per_page', 20))
            : $q->get();
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => ['required','max:120'],
            'slug' => ['required','alpha_dash','max:120','unique:restaurants,slug'],
            'settings' => ['sometimes','array'],
        ]);
        return Restaurant::create($data);
    }

    public function show(Restaurant $restaurant)
    {
        return $restaurant->load('users:id,name,email');
    }

    public function update(Request $r, Restaurant $restaurant)
    {
        $data = $r->validate([
            'name' => ['sometimes','max:120'],
            'slug' => ['sometimes','alpha_dash','max:120', Rule::unique('restaurants','slug')->ignore($restaurant->id)],
            'settings' => ['sometimes','array'],
        ]);
        $restaurant->update($data);
        return $restaurant;
    }

    public function destroy(Restaurant $restaurant)
    {
        $restaurant->delete(); // каскадно ще изтрие зависимото съдържание, ако FK са с cascade
        return response()->noContent();
    }
}
