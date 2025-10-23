<?php
// app/Http/Controllers/Api/AllergenController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAllergenRequest;
use App\Http\Requests\UpdateAllergenRequest;
use App\Http\Resources\AllergenResource;
use App\Models\Allergen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;


class AllergenController extends Controller
{
    /**
     * GET /api/allergens?restaurant=...&only_active=1&per_page=20
     * - per_page = -1 -> връща всички записи (без странициране)
     */
    public function index(Request $request)
    {
        $rid = (int) $request->attributes->get('restaurant_id');

        // проста „версия“ на кеша за този ресторант
        $ver = (int) Cache::get("rest:{$rid}:ver", 1);
        $key = 'allergens:list:' . $rid . ':v' . $ver . ':' . md5($request->fullUrl());

        $payload = Cache::remember($key, 60, function () use ($request, $rid) {
            $q = Allergen::query()
                ->where('restaurant_id', $rid)
                ->when($request->boolean('only_active'), fn ($qq) => $qq->where('is_active', true))
                ->orderBy('position', 'asc')
                ->orderBy('id', 'asc');

            $per = (int) $request->input('per_page', 20);

            if ($per === -1) {
                // масив (не paginator), за да е стабилно за кеш
                return AllergenResource::collection($q->get())->resolve();
            }

            $page = $q->paginate(max(1, $per));
            // преобразуваме ResourceCollection върху paginator към plain масив
            return AllergenResource::collection($page)->response()->getData(true);
        });

        return response()->json($payload);
    }

    /**
     * POST /api/allergens?restaurant=...  (admin)
     */
    public function store(StoreAllergenRequest $request)
    {
        $rid = (int) $request->attributes->get('restaurant_id');

        $a = Allergen::create([
            'restaurant_id' => $rid,
            'code'          => $request->code,
            'name'          => $request->name,
            'is_active'     => (bool) $request->input('is_active', true),
        ]);

        Cache::increment("rest:{$rid}:ver");

        return new AllergenResource($a);
    }

    /**
     * PATCH /api/allergens/{allergen}?restaurant=...  (admin)
     */
    public function update(UpdateAllergenRequest $request, Allergen $allergen)
    {
        $this->guardRestaurant($request, (int) $allergen->restaurant_id);

        $allergen->update($request->validated());

        Cache::increment("rest:{$allergen->restaurant_id}:ver");

        return new AllergenResource($allergen);
    }

    /**
     * DELETE /api/allergens/{allergen}?restaurant=...  (admin)
     */
    public function destroy(Request $request, Allergen $allergen)
    {
        $this->guardRestaurant($request, (int) $allergen->restaurant_id);

        $rid = (int) $allergen->restaurant_id;
        $allergen->delete();

        Cache::increment("rest:{$rid}:ver");

        return response()->noContent();
    }

    /** 404, ако записът не е на текущия ресторант */
    private function guardRestaurant(Request $request, int $ownerRid): void
    {
        $rid = (int) $request->attributes->get('restaurant_id');
        abort_unless($rid === $ownerRid, 404);
    }

    public function reorder(Request $request)
    {
        $rid = $request->attributes->get('restaurant_id');

        $data = $request->validate([
            'ids' => ['required','array','min:1'],
            'ids.*' => ['integer','distinct']
        ]);

        $validIds = \App\Models\Allergen::where('restaurant_id', $rid)
            ->whereIn('id', $data['ids'])
            ->pluck('id')->all();

        if (!count($validIds)) return response()->noContent();

        $order = [];
        $pos = 1;
        foreach ($data['ids'] as $id) {
            if (in_array($id, $validIds, true)) $order[$id] = $pos++;
        }

        DB::transaction(function () use ($order) {
            foreach ($order as $id => $position) {
                \App\Models\Allergen::where('id', $id)->update(['position' => $position]);
            }
        });

        Cache::flush(); // или Cache::tags(["menu:$rid"])->flush();

        return response()->noContent();
    }
}
