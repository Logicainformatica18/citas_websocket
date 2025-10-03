<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\JobOffer;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request; 
class CityDemandAIController extends Controller
{
    public function index()
    {
        Log::info("🌍 Cargando CityDemandAIController@index");
        return response()->json($this->buildResponse());
    }

public function getData(Request $request)
{
    $year = $request->get('year', 2024); // default 2024
    $zoom = (int) $request->get('zoom', 4);

    $query = JobOffer::query()
        ->whereYear('published_at', '>=', $year)
        ->whereNotNull('latitude')
        ->whereNotNull('longitude');

    // Lógica de agrupación por nivel de zoom
    if ($zoom < 5) {
        // Nivel global → país
        $results = $query->selectRaw("
            country,
            AVG(latitude) as lat,
            AVG(longitude) as lng,
            COUNT(*) as total,
            modality
        ")->groupBy('country','modality')->get();
    } else {
        // Zoom medio/alto → ciudad
        $results = $query->selectRaw("
            country, city,
            AVG(latitude) as lat,
            AVG(longitude) as lng,
            COUNT(*) as total,
            modality
        ")->groupBy('country','city','modality')->get();
    }

    return response()->json([
        'results' => $results,
        'message' => "Resultados agrupados por ".($zoom < 5 ? 'país' : 'ciudad')." para el año $year"
    ]);
}






private function buildResponse(array $filters = [], $bounds = null, $zoom = null)
{
    $query = JobOffer::query();

    foreach ($filters as $field => $value) {
        $query->where($field, $value);
    }

    // ✅ Filtrar por trimestre actual
    $query->whereYear('published_at', now()->year)
          ->whereRaw('QUARTER(published_at) = QUARTER(CURDATE())');

    // ✅ Filtrar por bounds si llega del frontend (lazy loading)
    if ($bounds) {
        $query->whereBetween('latitude', [$bounds['lat_min'], $bounds['lat_max']])
              ->whereBetween('longitude', [$bounds['lng_min'], $bounds['lng_max']]);
    }

    // ✅ Agrupamiento dinámico según zoom
    if ($zoom < 5) {
        // Zoom muy bajo: agrupar por país
        $results = $query->selectRaw("
                country,
                AVG(latitude) as lat,
                AVG(longitude) as lng,
                COUNT(*) as total
            ")
            ->groupBy('country')
            ->get();
    } elseif ($zoom < 8) {
        // Zoom medio: agrupar por ciudad
        $results = $query->selectRaw("
                country, city,
                AVG(latitude) as lat,
                AVG(longitude) as lng,
                COUNT(*) as total
            ")
            ->groupBy('country','city')
            ->get();
    } else {
        // Zoom alto: puntos individuales
        $results = $query->select('country','city','latitude as lat','longitude as lng')
                         ->get();
    }

    return [
        'results' => $results,
        'message' => '🌍 Distribución lista para heatmap con carga progresiva.'
    ];
}





}
