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

    // Agrupar por city+country (aunque city o country estén vacíos)
    $grouped = $results->groupBy(function ($row) {
        $country = trim($row->country ?? 'Desconocido');
        $city    = trim($row->city ?? 'Desconocido');
        return "{$city},{$country}";
    });

    $total = max($results->count(), 1);
    $percent = $grouped->map(function ($group) use ($total) {
        return round(($group->count() / $total) * 100, 2);
    });

    $cities = $grouped->map(function ($group, $label) use ($cityCoords) {
        $first    = $group->first();
        $cityName = trim($first->city ?? '');
        $country  = trim($first->country ?? '');

        // 🔍 Buscar coordenadas solo si hay city+country
        $match = null;
        if (!empty($cityName) && !empty($country)) {
            $match = $cityCoords->first(function ($city) use ($cityName, $country) {
                return stripos($city->city, $cityName) !== false &&
                       stripos($city->country, $country) !== false;
            });
        }

        // Conteo por modalidad
        $modalidades = $group->pluck('modality')->countBy()->toArray();

        // Modalidad dominante
        $modalidadDominante = collect($modalidades)->sortDesc()->keys()->first();

        return [
            'label'      => $label,
            'country'    => $country ?: null,
            'city'       => $cityName ?: null,
            'count'      => $group->count(),
            'modalidad'  => $modalidades,        // 👈 todas las modalidades
            'modality'   => $modalidadDominante, // 👈 la dominante
            'lat'        => $match->lat ?? null,
            'lng'        => $match->lng ?? null,
        ];
    });




  return [
    'results' => $cities->values(),
    'aggregations' => [
        'percent' => $percent,
    ],
    'modalities' => $results->pluck('modality')->countBy()->toArray(), // 👈 agregado aquí
    'message' => '🌍 Distribución de ofertas por ciudad y país calculada correctamente.'
];

}





}
