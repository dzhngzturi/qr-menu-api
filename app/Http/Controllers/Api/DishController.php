<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Models\Dish;
use App\Http\Resources\DishResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DishController extends Controller
{
    
     public function index(Request $request)
    {
        $rid = $request->attributes->get('restaurant_id');

        $allowedSort = ['id', 'name', 'price', 'position', 'created_at'];

        $query = Dish::with(['category:id,name'])
            ->where('restaurant_id', $rid);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // ✅ по подразбиране – position, после име (видимо пренареждане)
        if ($sort = $request->get('sort')) {
            foreach (explode(',', $sort) as $piece) {
                $dir = 'asc';
                $col = $piece;
                if (str_starts_with($piece, '-')) {
                    $dir = 'desc';
                    $col = ltrim($piece, '-');
                }
                if (in_array($col, $allowedSort, true)) {
                    $query->orderBy($col, $dir);
                }
            }
        } else {
            $query->orderBy('position')->orderBy('name');
        }

        $perPage = (int) $request->get('per_page', 20);
        if ($perPage === -1) {
            return DishResource::collection($query->get());
        }

        return DishResource::collection($query->paginate($perPage));
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

  
    public function reorder(Request $request)
{
    $rid = (int) $request->attributes->get('restaurant_id');
    if (!$rid) {
        return response()->json(['error' => 'Missing restaurant_id (middleware)'], 422);
    }

    $data = $request->validate([
        'ids'         => ['required','array','min:1'],
        'ids.*'       => ['integer','distinct'],
        'category_id' => ['nullable','integer'],
    ]);

    $q = Dish::where('restaurant_id', $rid);
    if (!empty($data['category_id'])) {
        $q->where('category_id', $data['category_id']);
    }
    $validIds = $q->whereIn('id', $data['ids'])->pluck('id')->all();

    if (!$validIds) {
        return response()->json(['updated' => 0, 'reason' => 'no valid ids for this restaurant/category'], 200);
    }

    $updated = 0;
    $pos = 1;

    DB::transaction(function () use ($data, $validIds, &$updated, &$pos) {
        foreach ($data['ids'] as $id) {
            if (in_array($id, $validIds, true)) {
                $affected = Dish::where('id', $id)->update(['position' => $pos++]);
                $updated += $affected;
            }
        }
    });

    return response()->json(['updated' => $updated], 200);
}



}
