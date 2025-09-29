<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\JobOffer;
use Illuminate\Support\Facades\Log;

class CityDemandAIController extends Controller
{
    public function index()
    {
        Log::info("🌍 Cargando CityDemandAIController@index");
        return response()->json($this->buildResponse());
    }

    public function getData(array $instruction)
    {
        Log::info("🌍 CityDemandAIController@getData", ['filters' => $instruction['filters'] ?? []]);
        return $this->buildResponse($instruction['filters'] ?? []);
    }

    private function buildResponse(array $filters = [])
    {
        $query = JobOffer::query();

        foreach ($filters as $field => $value) {
            $query->where($field, $value);
        }

        // ✅ Usar country + city
        $results = $query->get(['country', 'city', 'modality']);

        // Agrupar por combinación "city, country"
        $grouped = $results->groupBy(function ($row) {
            $country = $row->country ?? 'Desconocido';
            $city    = $row->city ?? 'Desconocido';
            return "{$city}, {$country}";
        });

        // Calcular porcentajes
        $total = max($results->count(), 1);
        $percent = $grouped->map(function ($group) use ($total) {
            return round(($group->count() / $total) * 100, 2);
        });

        // Preparar resultados para el mapa
        $cities = $grouped->map(function ($group, $label) {
            return [
                'label'     => $label,                        // "Santiago, Chile"
                'country'   => $group->first()->country ?? '',
                'city'      => $group->first()->city ?? '',
                'count'     => $group->count(),
                'modalidad' => $group->pluck('modality')->countBy()->toArray(),
            ];
        });

        return [
            'results' => $cities->values(),
            'aggregations' => [
                'percent' => $percent,
            ],
            'message' => '🌍 Distribución de ofertas por ciudad y país calculada correctamente.'
        ];
    }
}
