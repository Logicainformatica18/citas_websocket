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

    // Todas las ciudades con lat/lng registradas
    $cityCoords = \App\Models\City::all();

    // Traer ofertas
    $results = $query->get(['country', 'city', 'modality']);

    // Agrupar por city+country
    $grouped = $results->groupBy(function ($row) {
        $country = trim($row->country ?? 'Desconocido');
        $city    = trim($row->city ?? 'Desconocido');
        return "{$city},{$country}";
    });

    $total = max($results->count(), 1);
    $percent = $grouped->map(fn($g) => round(($g->count() / $total) * 100, 2));

    $cities = $grouped->map(function ($group, $label) use ($cityCoords) {
        $first    = $group->first();
        $cityName = trim($first->city ?? '');
        $country  = trim($first->country ?? '');

        $match = null;

        // 1️⃣ Buscar por country + city
        if ($country && $cityName) {
            $match = $cityCoords->first(fn($c) =>
                stripos($c->city, $cityName) !== false &&
                stripos($c->country, $country) !== false
            );
        }

        // 2️⃣ Buscar solo por city
        if (!$match && $cityName) {
            $match = $cityCoords->first(fn($c) =>
                stripos($c->city, $cityName) !== false
            );
        }

        // 3️⃣ Buscar solo por iso2 (del modelo City)
        if (!$match && $country) {
            $match = $cityCoords->first(fn($c) =>
                strtoupper($c->iso2) === strtoupper($country)
            );
        }

        $modalidades = $group->pluck('modality')->countBy()->toArray();
        $modalidadDominante = collect($modalidades)->sortDesc()->keys()->first();

        return [
            'label'      => $label,
            'country'    => $country ?: null,
            'city'       => $cityName ?: null,
            'count'      => $group->count(),
            'modalidad'  => $modalidades,
            'modality'   => $modalidadDominante,
            'lat'        => $match->lat ?? null,
            'lng'        => $match->lng ?? null,
            'iso2'       => $match->iso2 ?? null, // ✅ lo sacamos de City, no de JobOffer
        ];
    });

    return [
        'results' => $cities->values(),
        'aggregations' => ['percent' => $percent],
        'modalities' => $results->pluck('modality')->countBy()->toArray(),
        'message' => '🌍 Distribución de ofertas por ciudad y país calculada correctamente.'
    ];
}





}
