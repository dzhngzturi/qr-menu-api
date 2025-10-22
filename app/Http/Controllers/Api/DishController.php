<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Models\Dish;
use App\Http\Resources\DishResource;

class DishController extends Controller
{
    public function index(Request $request)
    {
        $rid = $request->attributes->get('restaurant_id');
        $query = Dish::with('category')
            ->where('restaurant_id', $rid)
            ->orderBy('id', 'desc');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->get('category_id'));
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%");
            });
        }

        return DishResource::collection($query->paginate(20));
    }

    public function store(Request $request)
    {
        $rid = $request->attributes->get('restaurant_id');

        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name'        => ['required', 'string', 'max:100',
                Rule::unique('dishes', 'name')->where(fn($q) => $q->where('restaurant_id', $rid))
            ],
            'description' => ['nullable', 'string'],
            'price'       => ['required', 'numeric', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
            'image'       => ['nullable', 'image', 'max:5120'],
        ]);

        $slug = Str::slug($data['name'], '-');
        $payload = [
            'restaurant_id' => $rid,
            'category_id'   => $data['category_id'],
            'name'          => $data['name'],
            'description'   => $data['description'] ?? null,
            'price'         => $data['price'],
            'is_active'     => (bool)($data['is_active'] ?? true),
        ];

        if ($request->hasFile('image')) {
            $ext = $request->file('image')->extension();
            $dest = "uploads/restaurants/{$rid}/dishes/{$slug}.{$ext}";
            Storage::disk('public')->put($dest, file_get_contents($request->file('image')->getRealPath()));
            $payload['image_path'] = $dest;
        }

        $dish = Dish::create($payload);

        return new DishResource($dish);
    }

    public function update(Request $request, Dish $dish)
    {
        $rid = $request->attributes->get('restaurant_id');

        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name'        => ['required', 'string', 'max:100',
                Rule::unique('dishes', 'name')->ignore($dish->id)
                    ->where(fn($q) => $q->where('restaurant_id', $rid))
            ],
            'description' => ['nullable', 'string'],
            'price'       => ['required', 'numeric', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
            'image'       => ['nullable', 'image', 'max:5120'],
        ]);

        $slug = Str::slug($data['name'], '-');
        $payload = [
            'category_id' => $data['category_id'],
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'price'       => $data['price'],
            'is_active'   => (bool)($data['is_active'] ?? true),
        ];

        if ($request->hasFile('image')) {
            if ($dish->image_path) {
                Storage::disk('public')->delete($dish->image_path);
            }
            $ext = $request->file('image')->extension();
            $dest = "uploads/restaurants/{$rid}/dishes/{$slug}.{$ext}";
            Storage::disk('public')->put($dest, file_get_contents($request->file('image')->getRealPath()));
            $payload['image_path'] = $dest;
        }

        $dish->update($payload);

        return new DishResource($dish);
    }

    public function destroy(Dish $dish)
    {
        if ($dish->image_path) {
            Storage::disk('public')->delete($dish->image_path);
        }
        $dish->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
