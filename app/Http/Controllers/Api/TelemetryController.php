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
     * GET /api/admin/restaurants/{restaurant}/telemetry/overview?days=7
     */
    public function overview(Request $request)
    {
        $rid = $request->attributes->get('restaurant_id');

        if (!$rid) {
            return response()->json([
                'message' => 'Restaurant not resolved',
            ], 400);
        }

        // зареждаме ресторанта
        $restaurant = Restaurant::find($rid);

        // ако няма такъв ресторант или телеметрията му е изключена → 404
        if (!$restaurant || !($restaurant->telemetry_enabled ?? false)) {
            return response()->json([
                'message' => 'Not found',
            ], 404);
        }

        // дни: 1–90 (по подразбиране 7)
        $days = (int) $request->input('days', 7);
        if ($days < 1 || $days > 90) {
            $days = 7;
        }

        // Диапазон [from; to] по occurred_at
        $to   = Carbon::now()->endOfDay();
        $from = (clone $to)->subDays($days - 1)->startOfDay();

        $base = TelemetryEvent::where('restaurant_id', $rid)
            ->whereBetween('occurred_at', [$from, $to]);

        // ----- totals -----
        $totals = [
            'all'       => (clone $base)->count(),
            'qr_scan'   => (clone $base)->where('type', 'qr_scan')->count(),
            'menu_open' => (clone $base)->where('type', 'menu_open')->count(),
            'search'    => (clone $base)->where('type', 'search')->count(),
        ];

        // ----- групирано по ден -----
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

        while ($cursor->lte($to)) {
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

        // ----- популярни търсения (по search_term колоната) -----
        $popular = TelemetryEvent::where('restaurant_id', $rid)
            ->where('type', 'search')
            ->whereBetween('occurred_at', [$from, $to])
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
                'from' => $from->toDateTimeString(),
                'to'   => $to->toDateTimeString(),
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
        $rid = $request->attributes->get('restaurant_id');

        if (!$rid) {
            return response()->json([
                'message' => 'Restaurant not resolved',
            ], 400);
        }

        $restaurant = Restaurant::find($rid);

        // ако ресторантът не съществува или телеметрията е изключена → не записваме
        if (!$restaurant || !$restaurant->telemetry_enabled) {
            return response()->json([
                'status' => 'telemetry_disabled',
            ], 204);
        }

        $data = $request->validate([
            'type'        => ['required', 'string', 'max:50'],
            'occurred_at' => ['nullable', 'date'],
            'session_id'  => ['nullable', 'string', 'max:100'],
            'payload'     => ['nullable', 'array'],
        ]);

        // изкарваме search_term от payload-а
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
            'search_term'   => $searchTerm,          //  НОВО
            'payload'       => $payload ?: null,     // може да е null ако е празен
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
        $rid = $request->attributes->get('restaurant_id');

        if (!$rid) {
            return response()->json([
                'message' => 'Restaurant not resolved',
            ], 400);
        }

        // същата защита – ако телеметрията е изключена → не записваме
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
                'user_agent'    => substr($request->userAgent() ?? '',0, 500),
                'search_term'   => $searchTerm,
                'payload'       => $payload ?: null,
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
