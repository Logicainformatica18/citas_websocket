<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\JobOffer;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class CityDemandAIController extends Controller
{
    public function index()
    {
        Log::info("🌍 Cargando CityDemandAIController@index");
        return response()->json($this->buildResponse());
    }

public function getData(Request $request)
{
    $year       = (int) $request->get('year', now()->year);
    $zoom       = (int) $request->get('zoom', 6);
    $source     = $request->get('source');
    $countries  = $request->get('countries', []); // ahora array
    $modality   = $request->get('modality');
    $quarter    = $request->get('quarter');
    $startDate  = $request->get('start_date');
    $endDate    = $request->get('end_date');

    Log::info("📩 [CityDemandAIController@getData] Parámetros recibidos:", [
        'year' => $year,
        'zoom' => $zoom,
        'source' => $source,
        'countries' => $countries,
        'modality' => $modality,
        'quarter' => $quarter,
        'start_date' => $startDate,
        'end_date' => $endDate,
    ]);

    $query = JobOffer::query()
        ->whereNotNull('latitude')
        ->whereNotNull('longitude');

    // 🔹 Año
    if ($year) {
        $query->whereYear('published_at', $year);
    }

    // 🔹 Rango de fechas (predomina sobre año)
    if ($startDate && $endDate) {
        try {
            $query->whereBetween('published_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ]);
        } catch (\Exception $e) {
            Log::warning("⚠️ Error al parsear fechas: " . $e->getMessage());
        }
    }

    // 🔹 Trimestre
    $quarters = [
        'Q1' => [1, 2, 3],
        'Q2' => [4, 5, 6],
        'Q3' => [7, 8, 9],
        'Q4' => [10, 11, 12],
    ];
    if ($quarter && isset($quarters[$quarter])) {
        $query->whereIn(DB::raw('MONTH(published_at)'), $quarters[$quarter]);
    }

    // 🔹 Fuente
    if ($source) {
        $query->where('source', $source);
    }

    // 🔹 País o países múltiples
    if (!empty($countries)) {
        if (is_array($countries)) {
            $query->whereIn('country', $countries);
        } else {
            $query->where('country', $countries);
        }
    }

    // 🔹 Modalidad
    if ($modality) {
        $query->where('modality', $modality);
    }

    // 🔍 Log del SQL antes de ejecutar
    Log::debug("🧠 SQL generado:", [
        'sql' => $query->toSql(),
        'bindings' => $query->getBindings(),
    ]);

    // 🔹 Agrupación según nivel de zoom
    if ($zoom < 5) {
        $results = $query->selectRaw("
            country,
            AVG(latitude) as lat,
            AVG(longitude) as lng,
            COUNT(*) as total,
            modality
        ")->groupBy('country', 'modality')->get();
    } else {
        $results = $query->selectRaw("
            country, city,
            AVG(latitude) as lat,
            AVG(longitude) as lng,
            COUNT(*) as total,
            modality
        ")->groupBy('country', 'city', 'modality')->get();
    }

    // 🔍 Log de salida
    Log::info("✅ [CityDemandAIController@getData] Resultado generado:", [
        'count' => $results->count(),
        'zoom' => $zoom,
        'agrupacion' => $zoom < 5 ? 'país' : 'ciudad',
        'primer_registro' => $results->first(),
    ]);

    return response()->json([
        'filters' => compact('source', 'countries', 'modality', 'year', 'quarter', 'startDate', 'endDate', 'zoom'),
        'count'   => $results->count(),
        'results' => $results,
        'message' => "📊 Resultados agrupados por " . ($zoom < 5 ? 'país' : 'ciudad'),
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
