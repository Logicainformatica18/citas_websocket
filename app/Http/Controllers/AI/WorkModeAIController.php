<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\JobOffer;

class WorkModeAIController extends Controller
{
    /**
     * 📊 Devuelve datos por defecto (para carga inicial del dashboard)
     */
    public function index()
    {
        return $this->buildResponse();
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
    $query = JobOffer::select('modality');

    foreach ($filters as $field => $value) {
        $query->where($field, $value);
    }

    $results = $query->get();

    // 🔹 Mapeo inglés → español
    $map = [
        'hybrid'        => 'Híbrido',
        'fully_remote'  => 'Remoto',
        'remote'  => 'Remoto local',
        'no_remote'     => 'Presencial',
    ];

    // 🔹 Normalizar resultados
    $normalized = $results->map(function ($row) use ($map) {
        $mod = $row->modality ?? 'desconocido';
        return $map[$mod] ?? ucfirst($mod);
    });

    // 🔹 Calcular porcentajes reales
    $total = max($normalized->count(), 1);
    $percentages = $normalized->groupBy(fn($m) => $m)->map(function ($group) use ($total) {
        return round(($group->count() / $total) * 100, 2);
    });

    // 🔹 Modalidades fijas (si falta alguna la completamos con 0)
    $fixedModalities = [
        'Híbrido'       => 0,
        'Remoto'        => 0,
        'Remoto local'  => 0,
        'Presencial'    => 0,
    ];

    $percent = array_merge($fixedModalities, $percentages->toArray());

    return response()->json([
        'aggregations' => [
            'percent' => $percent,
        ],
        'results' => $normalized,
    ]);
}

}
