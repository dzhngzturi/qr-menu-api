<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Http\Resources\CategoryResource;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $rid = $request->attributes->get('restaurant_id');

        $categories = Category::where('restaurant_id', $rid)
            ->orderBy('position')
            ->withCount('dishes')
            ->paginate(20);

        return CategoryResource::collection($categories);
    }

    public function store(Request $request)
    {
        $rid = $request->attributes->get('restaurant_id');

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100',
                Rule::unique('categories', 'name')->where(fn($q) => $q->where('restaurant_id', $rid))
            ],
            'slug'      => ['nullable', 'string', 'max:120',
                Rule::unique('categories', 'slug')->where(fn($q) => $q->where('restaurant_id', $rid))
            ],
            'position'  => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'image'     => ['nullable', 'image', 'max:5120'],
        ]);

        $slug = $data['slug'] ?? Str::slug($data['name'], '-');

        $payload = [
            'restaurant_id' => $rid,
            'name'          => $data['name'],
            'slug'          => $slug,
            'position'      => $data['position'] ?? 0,
            'is_active'     => (bool)($data['is_active'] ?? true),
        ];

        // качване на снимка
        if ($request->hasFile('image')) {
            $ext = $request->file('image')->extension();
            $dest = "uploads/restaurants/{$rid}/categories/{$slug}.{$ext}";
            Storage::disk('public')->put($dest, file_get_contents($request->file('image')->getRealPath()));
            $payload['image_path'] = $dest;
        }

        $category = Category::create($payload)->loadCount('dishes');

        return new CategoryResource($category);
    }

    public function update(Request $request, Category $category)
    {
        $rid = $request->attributes->get('restaurant_id');

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100',
                Rule::unique('categories', 'name')
                    ->ignore($category->id)
                    ->where(fn($q) => $q->where('restaurant_id', $rid))
            ],
            'slug'      => ['nullable', 'string', 'max:120',
                Rule::unique('categories', 'slug')
                    ->ignore($category->id)
                    ->where(fn($q) => $q->where('restaurant_id', $rid))
            ],
            'position'  => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'image'     => ['nullable', 'image', 'max:5120'],
        ]);

        $slug = $data['slug'] ?? $category->slug ?? Str::slug($data['name'], '-');
        $payload = [
            'name'      => $data['name'],
            'slug'      => $slug,
            'position'  => $data['position'] ?? $category->position,
            'is_active' => (bool)($data['is_active'] ?? true),
        ];

        if ($request->hasFile('image')) {
            if ($category->image_path) {
                Storage::disk('public')->delete($category->image_path);
            }
            $ext = $request->file('image')->extension();
            $dest = "uploads/restaurants/{$rid}/categories/{$slug}.{$ext}";
            Storage::disk('public')->put($dest, file_get_contents($request->file('image')->getRealPath()));
            $payload['image_path'] = $dest;
        }

        $category->update($payload);

        return new CategoryResource($category->loadCount('dishes'));
    }

    public function destroy(Category $category)
    {
        if ($category->image_path) {
            Storage::disk('public')->delete($category->image_path);
        }
        $category->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function reorder(Request $request)
    {
        $ids = $request->input('ids'); // очаква масив: [3,1,2,...]

        if (!is_array($ids)) {
            return response()->json(['error' => 'Invalid data'], 422);
        }

        foreach ($ids as $i => $id) {
            Category::where('id', $id)->update(['position' => $i + 1]);
        }

        return response()->json(['message' => 'Order updated']);
    }

}
