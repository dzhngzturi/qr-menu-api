<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DishResource;
use App\Models\Category;
use App\Models\Dish;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MenuController extends Controller
{
    /**
     * Комбинирано публично меню:
     * GET /api/menu?restaurant=slug
     * - Връща активни категории + активни ястия (с category:id,name)
     * - Полетата за снимки са image_url (готови за <img src>)
     * - Кеш: 60 мин. по restaurant + URL (за да уважим параметри)
     */
    public function index(Request $request)
    {
        $rid = $request->attributes->get('restaurant_id');
        $key = 'menu:' . $rid . ':' . sha1($request->fullUrl());

        return Cache::remember($key, 60, function () use ($rid) {
            // само активни категории за публичния изглед
            $categories = Category::query()
                ->where('restaurant_id', $rid)
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('name')
                ->get(['id','name','slug','position','image_path','is_active'])
                ->map(function (Category $c) {
                    return [
                        'id'         => $c->id,
                        'name'       => $c->name,
                        'slug'       => $c->slug,
                        'position'   => $c->position,
                        'is_active'  => (bool) $c->is_active,
                        'image_url'  => $c->image_path ? asset($c->image_path) : null,
                    ];
                });

            $dishes = Dish::query()
                ->where('restaurant_id', $rid)
                ->where('is_active', true)
                ->with('category:id,name')
                ->orderBy('name')
                ->get(['id','category_id','name','description','price','image_path','is_active'])
                ->map(fn (Dish $d) => (new DishResource($d->loadMissing('category:id,name')))->toArray(request()));

            return compact('categories', 'dishes');
        });
    }

    /**
     * Списък категории (админ/публично)
     * GET /api/categories?restaurant=...&only_active=1&sort=position,name
     */
    public function categories(Request $request)
    {
        $rid = $request->attributes->get('restaurant_id');

        $q = Category::query()
            ->where('restaurant_id', $rid);

        if ($request->boolean('only_active')) {
            $q->where('is_active', true);
        }

        // сортиране: ?sort=position,name
        if ($request->filled('sort')) {
            foreach (explode(',', $request->string('sort')) as $field) {
                $dir = str_starts_with($field, '-') ? 'desc' : 'asc';
                $col = ltrim($field, '-');
                if (in_array($col, ['name','position','created_at'])) {
                    $q->orderBy($col, $dir);
                }
            }
        } else {
            $q->orderBy('position')->orderBy('name');
        }

        return $q->get(['id','name','slug','position','image_path','is_active'])
            ->map(function (Category $c) {
                return [
                    'id'         => $c->id,
                    'name'       => $c->name,
                    'slug'       => $c->slug,
                    'position'   => $c->position,
                    'is_active'  => (bool) $c->is_active,
                    'image_url'  => $c->image_path ? asset($c->image_path) : null,
                ];
            });
    }

    /**
     * Списък ястия (админ/публично)
     * GET /api/dishes?restaurant=...&category_id=ID&only_active=1&search=...
     * Поддържа ?per_page=-1 за всички.
     */
    public function dishes(Request $request)
    {
        $rid = $request->attributes->get('restaurant_id');

        $q = Dish::query()
            ->where('restaurant_id', $rid)
            ->with('category:id,name');

        if ($request->boolean('only_active')) {
            $q->where('is_active', true);
        }
        if ($cid = $request->query('category_id')) {
            $q->where('category_id', (int) $cid);
        }
        if ($request->filled('search')) {
            $s = $request->string('search');
            $q->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        // сортиране: ?sort=name,-price
        if ($request->filled('sort')) {
            foreach (explode(',', $request->string('sort')) as $field) {
                $dir = str_starts_with($field, '-') ? 'desc' : 'asc';
                $col = ltrim($field, '-');
                if (in_array($col, ['name','price','created_at'])) {
                    $q->orderBy($col, $dir);
                }
            }
        } else {
            $q->orderBy('name');
        }

        $cols = ['id','category_id','name','description','price','image_path','is_active'];

        // per_page=-1 => без странициране
        $perPage = (int) $request->input('per_page', 15);
        if ($perPage === -1) {
            return DishResource::collection($q->get($cols));
        }

        return DishResource::collection($q->paginate($perPage, $cols));
    }
}
