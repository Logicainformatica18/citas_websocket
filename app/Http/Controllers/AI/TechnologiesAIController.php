<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\TechnologyTrend;
use Illuminate\Http\Request;

class TechnologiesAIController extends Controller
{
    /**
     * 📊 Devuelve datos iniciales (para carga del dashboard)
     */
    public function index(Request $request)
    {
        return $this->buildResponse($request->all());
    }

    /**
     * 📊 Devuelve datos filtrados según instrucción IA
     */
    public function getData(array $instruction)
    {
        return $this->buildResponse($instruction['filters'] ?? []);
    }

    /**
     * 🔹 Método reutilizable para query + normalización
     */
private function buildResponse(array $filters = [])
{
    $limit = $filters['limit'] ?? 10;
    $offset = $filters['offset'] ?? 0;

    $query = TechnologyTrend::select('language', 'num_pushers', 'year', 'quarter');

    if (!empty($filters['year'])) {
        $query->where('year', $filters['year']);
    }
    if (!empty($filters['quarter'])) {
        $query->where('quarter', $filters['quarter']);
    }

    $results = $query->get();

    // 🔹 Agrupar y sumar por lenguaje
    $aggregated = $results
        ->groupBy('language')
        ->map(fn($rows) => $rows->sum('num_pushers'))
        ->sortDesc();

    // 🔹 Total global (todos los lenguajes)
    $total = max($aggregated->sum(), 1);

    // 🔹 Paginación: cortar con offset + limit
    $paged = $aggregated
        ->slice($offset, $limit);

    // 🔹 Calcular porcentajes relativos al total global
    $percentages = $paged->map(fn($count) => round(($count / $total) * 100, 2));

    return response()->json([
        'aggregations' => [
            'percent' => $percentages,
        ],
        'results' => $paged,
        'meta' => [
            'total_languages' => $aggregated->count(),
            'limit' => $limit,
            'offset' => $offset,
        ],
        'message' => '💻 Tendencias tecnológicas paginadas correctamente.',
    ]);
}


}
