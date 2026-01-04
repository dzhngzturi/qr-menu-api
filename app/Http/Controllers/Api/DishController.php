<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Models\Dish;
use App\Models\Category;
use App\Http\Resources\DishResource;
use Illuminate\Support\Facades\DB;

class DishController extends Controller
{
    public function index(Request $request)
    {
        $rid = (int) $request->attributes->get('restaurant_id');

        $allowedSort = ['id', 'name', 'price', 'position', 'created_at'];

        $query = Dish::with(['category:id,name'])
            ->where('restaurant_id', $rid);

        // ✅ ако има category_id, увери се че категорията е от същия ресторант
        if ($request->filled('category_id')) {
            $categoryId = $request->integer('category_id');

            $categoryOk = Category::where('restaurant_id', $rid)
                ->where('id', $categoryId)
                ->exists();

            if (!$categoryOk) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            $query->where('category_id', $categoryId);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // ✅ по подразбиране – position, после име
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
        $rid = (int) $request->attributes->get('restaurant_id');

        $data = $request->validate([
            // ⛳️ първо валидираме, че е число; после ще проверим ownership
            'category_id' => ['required', 'integer'],
            'name'        => [
                'required', 'string', 'max:100',
                Rule::unique('dishes', 'name')->where(fn ($q) => $q->where('restaurant_id', $rid)),
            ],
            'description' => ['nullable', 'string'],
            'price'       => ['required', 'numeric', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
            'image'       => ['nullable', 'image', 'max:5120'],
        ]);

        // ✅ category трябва да е от същия ресторант
        $categoryOk = Category::where('restaurant_id', $rid)
            ->where('id', (int) $data['category_id'])
            ->exists();

        if (!$categoryOk) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $slug = Str::slug($data['name'], '-');

        $payload = [
            'restaurant_id' => $rid,
            'category_id'   => (int) $data['category_id'],
            'name'          => $data['name'],
            'description'   => $data['description'] ?? null,
            'price'         => $data['price'],
            'is_active'     => (bool) ($data['is_active'] ?? true),
        ];

        if ($request->hasFile('image')) {
            $ext  = $request->file('image')->extension();
            $dest = "uploads/restaurants/{$rid}/dishes/{$slug}.{$ext}";
            Storage::disk('public')->put($dest, file_get_contents($request->file('image')->getRealPath()));
            $payload['image_path'] = $dest;
        }

        $dish = Dish::create($payload);

        return new DishResource($dish);
    }

    public function update(Request $request, Dish $dish)
    {
        $rid = (int) $request->attributes->get('restaurant_id');

        // ✅ Tenant isolation: не позволявай update на чужд ресторант
        if ((int) $dish->restaurant_id !== $rid) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'category_id' => ['required', 'integer'],
            'name'        => [
                'required', 'string', 'max:100',
                Rule::unique('dishes', 'name')
                    ->ignore($dish->id)
                    ->where(fn ($q) => $q->where('restaurant_id', $rid)),
            ],
            'description' => ['nullable', 'string'],
            'price'       => ['required', 'numeric', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
            'image'       => ['nullable', 'image', 'max:5120'],
        ]);

        // ✅ category трябва да е от същия ресторант
        $categoryOk = Category::where('restaurant_id', $rid)
            ->where('id', (int) $data['category_id'])
            ->exists();

        if (!$categoryOk) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $slug = Str::slug($data['name'], '-');

        $payload = [
            'category_id'   => (int) $data['category_id'],
            'name'          => $data['name'],
            'description'   => $data['description'] ?? null,
            'price'         => $data['price'],
            'is_active'     => (bool) ($data['is_active'] ?? true),
        ];

        if ($request->hasFile('image')) {
            if ($dish->image_path) {
                Storage::disk('public')->delete($dish->image_path);
            }

            $ext  = $request->file('image')->extension();
            $dest = "uploads/restaurants/{$rid}/dishes/{$slug}.{$ext}";
            Storage::disk('public')->put($dest, file_get_contents($request->file('image')->getRealPath()));
            $payload['image_path'] = $dest;
        }

        $dish->update($payload);

        return new DishResource($dish);
    }

    public function destroy(Request $request, Dish $dish)
    {
        $rid = (int) $request->attributes->get('restaurant_id');

        // ✅ Tenant isolation: не позволявай delete на чужд ресторант
        if ((int) $dish->restaurant_id !== $rid) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

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
            'ids'         => ['required', 'array', 'min:1'],
            'ids.*'       => ['integer', 'distinct'],
            'category_id' => ['nullable', 'integer'],
        ]);

        // ✅ ако има category_id, тя трябва да е от текущия ресторант
        if (!empty($data['category_id'])) {
            $categoryOk = Category::where('restaurant_id', $rid)
                ->where('id', (int) $data['category_id'])
                ->exists();

            if (!$categoryOk) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $q = Dish::where('restaurant_id', $rid);

        if (!empty($data['category_id'])) {
            $q->where('category_id', (int) $data['category_id']);
        }

        $validIds = $q->whereIn('id', $data['ids'])->pluck('id')->all();

        // 🔒 по-строго: ако има подадени чужди IDs → 403
        if (count($validIds) !== count($data['ids'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $updated = 0;
        $pos = 1;

        DB::transaction(function () use ($rid, $data, &$updated, &$pos) {
            foreach ($data['ids'] as $id) {
                $affected = Dish::where('restaurant_id', $rid)
                    ->where('id', $id)
                    ->update(['position' => $pos++]);

                $updated += $affected;
            }
        });

        return response()->json(['updated' => $updated], 200);
    }
}
