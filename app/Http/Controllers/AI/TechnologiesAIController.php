<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\TechnologyTrendEnriched;
use App\Models\City;

class TechnologiesAIController extends Controller
{
    /**
     * 📊 Devuelve opciones dinámicas para filtros
     */
    public function metadata()
    {
        try {
            Log::info("🧩 [metadata] cargando filtros LATAM...");

            // 🔹 Años
            $years = TechnologyTrendEnriched::distinct()
                ->pluck('year')
                ->filter()
                ->sort()
                ->values();

            // 🔹 Lenguajes
            $languages = TechnologyTrendEnriched::distinct()
                ->pluck('language')
                ->filter()
                ->sort()
                ->values();

            // 🔹 Fuentes (GitHub, GitLab, etc.)
            $sources = TechnologyTrendEnriched::distinct()
                ->pluck('source')
                ->filter()
                ->sort()
                ->values();

            // 🔹 Países detectados en la tabla enriquecida
            $trendCountries = TechnologyTrendEnriched::selectRaw('TRIM(UPPER(iso2_code)) as code')
                ->whereNotNull('iso2_code')
                ->where('iso2_code', '!=', '')
                ->distinct()
                ->pluck('code')
                ->toArray();

            // 🔹 Cruzar con cities
            $countries = collect();
            if (Schema::hasTable('cities')) {
                $countries = City::whereIn('iso2', $trendCountries)
                    ->select('iso2', 'country')
                    ->distinct()
                    ->get()
                    ->mapWithKeys(fn($row) => [$row->iso2 => $row->country])
                    ->sort();
            }

            Log::info("✅ [metadata] filtros listos", [
                'years' => $years->count(),
                'languages' => $languages->count(),
                'sources' => $sources->count(),
                'countries' => $countries->count(),
            ]);

            return response()->json([
                'years' => $years,
                'languages' => $languages,
                'sources' => $sources,
                'countries' => $countries,
            ]);
        } catch (\Throwable $e) {
            Log::error("❌ Error en metadata LATAM", ['msg' => $e->getMessage()]);
            return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 📈 Devuelve datos agregados con más métricas
     */
    public function getData(Request $request)
    {
        $filters = $request->input('filters', []);
        Log::info("🔍 [getData] filtros", $filters);

        $query = TechnologyTrendEnriched::query();

        if (!empty($filters['year'])) {
            $query->whereIn('year', (array) $filters['year']);
        }

        if (!empty($filters['language'])) {
            $query->whereIn('language', (array) $filters['language']);
        }

        if (!empty($filters['source'])) {
            $query->whereIn('source', (array) $filters['source']);
        }

        if (!empty($filters['country'])) {
            $query->whereIn('iso2_code', (array) $filters['country']);
        }

        $data = $query->get();

        if ($data->isEmpty()) {
            return response()->json(['aggregations' => ['percent' => []]]);
        }

        // 🔹 Agrupar por lenguaje
        $grouped = $data->groupBy('language')->map(function ($rows) {
            return [
                'popularity' => round($rows->avg('popularity_index'), 2),
                'repos' => $rows->sum('num_repos'),
                'users' => $rows->sum('num_users'),
                'bytes' => $rows->sum('total_bytes'),
            ];
        });

        // 🔹 Ordenar y tomar top 10
        $sorted = $grouped->sortByDesc('popularity')->take(10);

        // 🔹 Formato para frontend
        $percentages = $sorted->map(fn($r) => $r['popularity']);

        return response()->json([
            'aggregations' => ['percent' => $percentages],
            'extra' => $sorted,
            'message' => '✅ Datos enriquecidos cargados correctamente',
        ]);
    }
}
