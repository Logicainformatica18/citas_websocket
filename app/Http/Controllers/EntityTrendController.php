<?php

namespace App\Http\Controllers;

use App\Models\EntityTrend;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EntityTrendController extends Controller
{
    /**
     * 📄 Listado principal (Inertia)
     */
    public function index(Request $request)
    {
        $search  = $request->get('search');
        $year    = $request->get('year');
        $quarter = $request->get('quarter');

        $trends = EntityTrend::query()
            ->with('marketEntity')
            ->when($search, fn ($q) =>
                $q->where('trend_name', 'like', "%{$search}%")
            )
            ->when($year, fn ($q) =>
                $q->where('year', $year)
            )
            ->when($quarter, fn ($q) =>
                $q->where('quarter', $quarter)
            )
            ->orderByDesc('year')
            ->orderByDesc('quarter')
            ->orderByDesc('trend_score')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('entity-trends/Index', [
            'trends' => $trends->through(fn ($t) => [
                'id'               => $t->id,
                'trend_name'       => $t->trend_name,
                'trend_score'      => $t->trend_score,
                'entity_name'      => optional($t->marketEntity)->name,
                'match_type'       => $t->match_type,
                'confidence_score' => $t->confidence_score,
                'source_title'     => $t->source_title,
                'source_url'       => $t->source_url,
                'year'             => $t->year,
                'quarter'          => $t->quarter,
                'created_at'       => optional($t->created_at)->format('Y-m-d'),
            ]),
            'filters' => [
                'search'  => $search,
                'year'    => $year,
                'quarter' => $quarter,
            ],
        ]);
    }

    /**
     * 📄 API JSON
     */
    public function fetchPaginated(Request $request)
{
    $search  = $request->get('search');
    $year    = $request->get('year');
    $quarter = $request->get('quarter');

    $trends = EntityTrend::query()
        ->with('marketEntity') // 🔥 IMPORTANTE
        ->when($search, fn ($q) =>
            $q->where('trend_name', 'like', "%{$search}%")
        )
        ->when($year, fn ($q) =>
            $q->where('year', $year)
        )
        ->when($quarter, fn ($q) =>
            $q->where('quarter', $quarter)
        )
        ->orderByDesc('year')
        ->orderByDesc('quarter')
        ->orderByDesc('trend_score')
        ->paginate(15)
        ->withQueryString();

    return response()->json(
        $trends->through(fn ($t) => [
            'id'               => $t->id,
            'trend_name'       => $t->trend_name,
            'trend_score'      => $t->trend_score,
            'entity_name'      => optional($t->marketEntity)->name,
            'match_type'       => $t->match_type,
            'confidence_score' => $t->confidence_score,
            'source_title'     => $t->source_title,
            'source_url'       => $t->source_url,
            'year'             => $t->year,
            'quarter'          => $t->quarter,
            'created_at'       => optional($t->created_at)->format('Y-m-d'),
        ])
    );
}

    /**
     * 🗑️ Eliminar trend puntual (auditoría manual)
     */
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {

            $trend = EntityTrend::findOrFail($id);
            $trend->delete();

            return response()->json([
                'message' => '🗑️ Trend eliminado correctamente.'
            ]);
        });
    }
    public function store(Request $request)
{
    $validated = $request->validate([
        'trend_name'       => 'required|string|max:255',
        'trend_score'      => 'nullable|numeric',
        'year'             => 'required|integer',
        'quarter'          => 'required|integer',
        'match_type'       => 'nullable|string|max:100',
        'confidence_score' => 'nullable|numeric',
        'source_title'     => 'nullable|string|max:255',
        'source_url'       => 'nullable|string|max:255',
        'market_entity_id' => 'nullable|integer',
    ]);

    return DB::transaction(function () use ($validated) {

        $validated['created_at'] = now();

        $trend = EntityTrend::create($validated);

        return response()->json([
            'message' => '✅ Trend creado correctamente.',
            'trend'   => $trend,
        ], 201);
    });
}

public function update(Request $request, $id)
{
    $validated = $request->validate([
        'trend_name'       => 'required|string|max:255',
        'trend_score'      => 'nullable|numeric',
        'year'             => 'required|integer',
        'quarter'          => 'required|integer',
        'match_type'       => 'nullable|string|max:100',
        'confidence_score' => 'nullable|numeric',
        'source_title'     => 'nullable|string|max:255',
        'source_url'       => 'nullable|string|max:255',
        'market_entity_id' => 'nullable|integer',
    ]);

    return DB::transaction(function () use ($validated, $id) {

        $trend = EntityTrend::findOrFail($id);

        $trend->update($validated);

        return response()->json([
            'message' => '✅ Trend actualizado correctamente.',
            'trend'   => $trend,
        ]);
    });
}
}
