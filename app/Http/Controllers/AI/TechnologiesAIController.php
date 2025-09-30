<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use App\Models\TechnologyTrend;

class TechnologiesAIController extends Controller
{
    /**
     * 📊 Carga inicial sin filtros
     */
    public function index()
    {
        return $this->buildResponse();
    }

    /**
     * 📊 Devuelve datos filtrados según instrucción del padre
     */
    public function getData(array $instruction)
    {
        return $this->buildResponse($instruction['filters'] ?? []);
    }

    /**
     * 🔹 Construcción de respuesta con filtros
     */
    private function buildResponse(array $filters = [])
    {
        Log::info("🔍 TechnologiesAIController ejecutado", ['filters' => $filters]);

        $query = TechnologyTrend::select('language', 'num_pushers', 'year', 'quarter');

        if (!empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }
        if (!empty($filters['quarter']) && $filters['quarter'] !== 'all') {
            $query->where('quarter', $filters['quarter']);
        }

        $results = $query->get();

        Log::info("📊 Resultados crudos", [
            'count' => $results->count(),
            'sample' => $results->take(5)->toArray(),
        ]);

       // Agrupar y sumar todo el set (año + trimestre filtrado)
$aggregated = $results
    ->groupBy('language')
    ->map(fn($rows) => $rows->sum('num_pushers'))
    ->sortDesc();

// Calcular total general (no solo top)
$total = max($aggregated->sum(), 1);

// Top 10 tecnologías
$top = $aggregated->take(10);

// Calcular porcentajes respecto al total
$percentages = $top->map(fn($count) => round(($count / $total) * 100, 2));


        Log::info("🏆 Top tecnologías", [
            'top' => $top->toArray(),
            'percentages' => $percentages->toArray(),
        ]);

        return response()->json([
            'aggregations' => [
                'percent' => $percentages,
            ],
            'results' => $top,
            'meta' => [
                'total_languages' => $aggregated->count(),
                'total_pushers'   => $total,
            ],
            'message' => '💻 Tendencias tecnológicas calculadas correctamente.',
        ]);
    }
}
