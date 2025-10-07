<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\TechnologyTrend;
use App\Models\City;
use Illuminate\Support\Facades\Schema;


class TechnologiesAIController extends Controller
{
    /**
     * 📊 Carga inicial (sin filtros)
     */
    public function index()
    {
        return $this->buildResponse();
    }

    /**
     * 📊 Devuelve datos filtrados desde el frontend (Inertia/React)
     */
    public function getData(Request $request)
    {
        $filters = $request->input('filters', []);
        return $this->buildResponse($filters);
    }

    /**
     * 🔹 Devuelve opciones dinámicas (años, lenguajes, fuentes, países)
     */
   /**
 * 🔹 Devuelve opciones dinámicas (años, lenguajes, fuentes, países)
 */
public function metadata()
{
    try {
        Log::info("🧩 [metadata] iniciando consulta de filtros dinámicos");

        $years = TechnologyTrend::distinct()
            ->pluck('year')
            ->filter()
            ->sort()
            ->values();

        $languages = TechnologyTrend::distinct()
            ->pluck('language')
            ->filter()
            ->sort()
            ->values();

        // ⚠️ si aún no tienes la columna 'source', esto evita el crash
        $sources = [];
        if (Schema::hasColumn('technology_trends', 'source')) {
            $sources = TechnologyTrend::distinct()
                ->pluck('source')
                ->filter()
                ->sort()
                ->values();
        } else {
            $sources = collect(['github']);
        }

        // 🔍 Cruzar con tabla cities — si no existe, capturar error
        $countries = collect();
        if (Schema::hasTable('cities')) {
            $countries = \DB::table('cities')
                ->select('iso2', 'country')
                ->whereNotNull('iso2')
                ->distinct()
                ->orderBy('country')
                ->get()
                ->mapWithKeys(fn($row) => [$row->iso2 => $row->country]);
        }

        Log::info("✅ [metadata] resultados obtenidos", [
            'years_count' => $years->count(),
            'langs_count' => $languages->count(),
            'sources' => $sources,
            'countries_count' => $countries->count(),
        ]);

        return response()->json([
            'years' => $years,
            'languages' => $languages,
            'sources' => $sources,
            'countries' => $countries,
        ]);
    } catch (\Throwable $e) {
        Log::error("❌ Error en TechnologiesAIController@metadata", [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'error' => true,
            'message' => 'Error cargando metadata: ' . $e->getMessage(),
        ], 500);
    }
}


    /**
     * 🔹 Core: construcción de respuesta con filtros combinados
     */
    private function buildResponse(array $filters = [])
    {
        Log::info("🔍 TechnologiesAIController ejecutado", ['filters' => $filters]);

        $query = TechnologyTrend::select(
            'language',
            'num_pushers',
            'year',
            'quarter',
            'source',
            'iso2_code'
        );

        // Filtros flexibles (multiopción)
        if (!empty($filters['year'])) {
            $query->whereIn('year', (array) $filters['year']);
        }

        if (!empty($filters['quarter']) && $filters['quarter'] !== 'all') {
            $query->where('quarter', $filters['quarter']);
        }

        if (!empty($filters['language'])) {
            $query->whereIn('language', (array) $filters['language']);
        }

        if (!empty($filters['source'])) {
            $query->whereIn('source', (array) $filters['source']);
        }

        if (!empty($filters['country'])) {
            // Filtrar por código ISO2 (relacionado con cities)
            $query->whereIn('iso2_code', (array) $filters['country']);
        }

        $results = $query->get();

        Log::info("📊 Resultados crudos", [
            'count' => $results->count(),
            'sample' => $results->take(5)->toArray(),
        ]);

        // Agrupar y sumar total por lenguaje
        $aggregated = $results
            ->groupBy('language')
            ->map(fn($rows) => $rows->sum('num_pushers'))
            ->sortDesc();

        $total = max($aggregated->sum(), 1);
        $top = $aggregated->take(10);
        $percentages = $top->map(fn($count) => round(($count / $total) * 100, 2));

        // Resultado con metadatos
        return response()->json([
            'aggregations' => [
                'percent' => $percentages,
            ],
            'results' => $top,
            'meta' => [
                'total_languages' => $aggregated->count(),
                'total_pushers' => $total,
            ],
            'message' => '💻 Tendencias tecnológicas calculadas correctamente.',
        ]);
    }
}
