<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelemetryEvent;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TelemetryController extends Controller
{
    /**
     * Overview за dashboard-а за конкретен ресторант:
     * GET /api/admin/telemetry/overview?days=7
     * (restaurant се resolve-ва от middleware resolve.restaurant)
     */
    public function overview(Request $request)
    {
        $rid = (int) $request->attributes->get('restaurant_id');

        if (!$rid) {
            return response()->json(['message' => 'Restaurant not resolved'], 400);
        }

        $restaurant = Restaurant::find($rid);

        // ако няма такъв ресторант или телеметрията му е изключена → 404
        if (!$restaurant || !($restaurant->telemetry_enabled ?? false)) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // дни: 1–90 (по подразбиране 7)
        $days = (int) $request->query('days', 7);
        if ($days < 1 || $days > 90) $days = 7;

        /**
         * Календарен диапазон:
         * при days=7 => от (днес-6) 00:00:00 до днес 23:59:59
         * ВАЖНО: филтрираме по occurred_at (реално време на евента), не по created_at
         */
        $to   = Carbon::now()->endOfDay();
        $from = Carbon::now()->subDays($days - 1)->startOfDay();

        $base = TelemetryEvent::query()
            ->where('restaurant_id', $rid)
            ->whereBetween('occurred_at', [$from, $to]);

        // ----- totals (COUNT на евенти) -----
        // (ако искаш unique session counts -> кажи, ще сменим логиката)
        $totalsByType = (clone $base)
            ->selectRaw('type, COUNT(*) as cnt')
            ->groupBy('type')
            ->pluck('cnt', 'type');

        $totals = [
            'all'       => (int) $totalsByType->sum(),
            'qr_scan'   => (int) ($totalsByType['qr_scan'] ?? 0),
            'menu_open' => (int) ($totalsByType['menu_open'] ?? 0),
            'search'    => (int) ($totalsByType['search'] ?? 0),
        ];

        // ----- групирано по ден -----
        // В MySQL SUM(type='x') работи (връща 0/1). Ако някой ден смениш DB -> може да го сменим на SUM(CASE WHEN ... THEN 1 ELSE 0 END)
        $rows = (clone $base)
            ->selectRaw("
                DATE(occurred_at) as d,
                SUM(type = 'qr_scan')   as qr_scan,
                SUM(type = 'menu_open') as menu_open,
                SUM(type = 'search')    as search
            ")
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy('d');

        $eventsByDay = [];
        $cursor = $from->copy();

        // точно days дни (inclusive)
        for ($i = 0; $i < $days; $i++) {
            $key = $cursor->toDateString();
            $row = $rows->get($key);

            $eventsByDay[] = [
                'date'      => $key,
                'qr_scan'   => (int) ($row->qr_scan ?? 0),
                'menu_open' => (int) ($row->menu_open ?? 0),
                'search'    => (int) ($row->search ?? 0),
            ];

            $cursor->addDay();
        }

        // ----- популярни търсения -----
        $popular = (clone $base)
            ->where('type', 'search')
            ->whereNotNull('search_term')
            ->selectRaw("search_term, COUNT(*) as count")
            ->groupBy('search_term')
            ->orderByDesc('count')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'term'  => $row->search_term,
                'count' => (int) $row->count,
            ])
            ->values();

        return response()->json([
            'range' => [
                // за UI е по-удобно date-only (за да не се бърка с часови зони)
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
                'days' => $days,
            ],
            'totals'           => $totals,
            'events_by_day'    => $eventsByDay,
            'popular_searches' => $popular,
        ]);
    }

    /**
     * Запис на едно събитие телеметрия.
     * POST /api/telemetry
     */
    public function store(Request $request)
    {
        $rid = (int) $request->attributes->get('restaurant_id');

        if (!$rid) {
            return response()->json(['message' => 'Restaurant not resolved'], 400);
        }

        $restaurant = Restaurant::find($rid);

        // ако ресторантът не съществува или телеметрията е изключена → не записваме
        if (!$restaurant || !($restaurant->telemetry_enabled ?? false)) {
            return response()->json(['status' => 'telemetry_disabled'], 204);
        }

        $data = $request->validate([
            'type'        => ['required', 'string', 'max:50'],
            'occurred_at' => ['nullable', 'date'],
            'session_id'  => ['nullable', 'string', 'max:100'],
            'payload'     => ['nullable', 'array'],
        ]);

        $payload = $data['payload'] ?? [];
        $searchTerm = null;

        if ($data['type'] === 'search' && is_array($payload)) {
            $raw = mb_strtolower(trim((string)($payload['term'] ?? '')));
            if ($raw !== '') {
                $searchTerm = mb_substr($raw, 0, 191);
            }
        }

        $event = TelemetryEvent::create([
            'restaurant_id' => $rid,
            'type'          => $data['type'],
            'occurred_at'   => $data['occurred_at'] ?? now(),
            'session_id'    => $data['session_id'] ?? null,
            'ip'            => $request->ip(),
            'user_agent'    => substr($request->userAgent() ?? '', 0, 1000),
            'search_term'   => $searchTerm,
            'payload'       => !empty($payload) ? $payload : null,
        ]);

        return response()->json([
            'id'     => $event->id,
            'status' => 'ok',
        ], 201);
    }

    /**
     * Batch запис: няколко събития в една заявка (по-ефективно).
     * POST /api/telemetry/batch
     */
    public function batch(Request $request)
    {
        $rid = (int) $request->attributes->get('restaurant_id');

        if (!$rid) {
            return response()->json(['message' => 'Restaurant not resolved'], 400);
        }

        $restaurant = Restaurant::find($rid);
        if (!$restaurant || !($restaurant->telemetry_enabled ?? false)) {
            return response()->json(['status' => 'telemetry_disabled'], 200);
        }

        $validated = $request->validate([
            'events'               => ['required', 'array', 'max:100'],
            'events.*.type'        => ['required', 'string', 'max:50'],
            'events.*.occurred_at' => ['nullable', 'date'],
            'events.*.session_id'  => ['nullable', 'string', 'max:100'],
            'events.*.payload'     => ['nullable', 'array'],
        ]);

        $rows = [];
        $now = now();

        foreach ($validated['events'] as $item) {
            $payload = $item['payload'] ?? [];

            $searchTerm = null;
            if ($item['type'] === 'search' && is_array($payload)) {
                $raw = mb_strtolower(trim((string)($payload['term'] ?? '')));
                if ($raw !== '') {
                    $searchTerm = mb_substr($raw, 0, 191);
                }
            }

            $rows[] = [
                'restaurant_id' => $rid,
                'type'          => $item['type'],
                'occurred_at'   => $item['occurred_at'] ?? $now,
                'session_id'    => $item['session_id'] ?? null,
                'ip'            => $request->ip(),
                'user_agent'    => substr($request->userAgent() ?? '', 0, 1000),
                'search_term'   => $searchTerm,
                'payload'       => !empty($payload) ? $payload : null,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        if (!empty($rows)) {
            TelemetryEvent::insert($rows);
        }

        return response()->json([
            'status' => 'ok',
            'count'  => count($rows),
        ], 201);
    }
}
