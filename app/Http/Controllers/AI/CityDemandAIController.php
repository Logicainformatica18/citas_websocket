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
    /**
     * 📋 Devuelve metadata para filtros dinámicos (fuentes, países, modalidades, años)
     */
    public function metadata()
    {
        Log::info("🌎 Cargando metadata CityDemandAIController");

        $countries = JobOffer::whereNotNull('country')
            ->where('country', '<>', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        $modalities = JobOffer::whereNotNull('modality')
            ->where('modality', '<>', '')
            ->distinct()
            ->orderBy('modality')
            ->pluck('modality');

        $sources = JobOffer::whereNotNull('source')
            ->where('source', '<>', '')
            ->distinct()
            ->orderBy('source')
            ->pluck('source');

        $years = JobOffer::selectRaw('YEAR(published_at) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        return response()->json([
            'countries' => $countries,
            'modalities' => $modalities,
            'sources' => $sources,
            'years' => $years,
        ]);
    }

    /**
     * 🧭 Endpoint inicial
     */
    public function index()
    {
        Log::info("🌍 Cargando CityDemandAIController@index");
        return response()->json($this->buildResponse());
    }

    /**
     * 📊 Datos agregados por ciudad o país (según zoom y filtros)
     */
    public function getData(Request $request)
    {
        $year       = (int) $request->get('year', now()->year);
        $zoom       = (int) $request->get('zoom', 6);
        $source     = $request->get('source');
        $countries  = $request->get('countries', []);
     $modalities = (array) $request->get('modalities', []);

        $quarter    = $request->get('quarter');
        $startDate  = $request->get('start_date');
        $endDate    = $request->get('end_date');

        Log::info("📩 [CityDemandAIController@getData] Parámetros recibidos:", [
            'year' => $year,
            'zoom' => $zoom,
            'source' => $source,
            'countries' => $countries,
            'modalities' => $modalities,
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

        // 🔹 Fechas personalizadas
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

        // 🔹 País(es)
        if (!empty($countries)) {
            if (is_array($countries)) {
                $query->whereIn('country', $countries);
            } else {
                $query->where('country', $countries);
            }
        }

        // 🔹 Modalidad
       // 🔹 Modalidades múltiples
if (!empty($modalities)) {
    $query->whereIn('modality', $modalities);
}


        // 🔍 Log SQL
        Log::debug("🧠 SQL generado:", [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);

        // 🔹 Agrupación
        if ($zoom < 5) {
            // 🔸 Agrupar por país
            $results = $query->selectRaw("
                country,
                AVG(latitude) as lat,
                AVG(longitude) as lng,
                COUNT(*) as total,
                modality
            ")->groupBy('country', 'modality')->get();
        } else {
            // 🔸 Agrupar por ciudad
            $results = $query->selectRaw("
                country, city,
                AVG(latitude) as lat,
                AVG(longitude) as lng,
                COUNT(*) as total,
                modality
            ")->groupBy('country', 'city', 'modality')->get();
        }

        Log::info("✅ [CityDemandAIController@getData] Resultado generado:", [
            'count' => $results->count(),
            'zoom' => $zoom,
            'agrupacion' => $zoom < 5 ? 'país' : 'ciudad',
            'primer_registro' => $results->first(),
        ]);

        return response()->json([
            'filters' => compact('source', 'countries', 'modalities', 'year', 'quarter', 'startDate', 'endDate', 'zoom'),
            'count'   => $results->count(),
            'results' => $results,
            'message' => "📊 Resultados agrupados por " . ($zoom < 5 ? 'país' : 'ciudad'),
        ]);
    }

    /**
     * 🧾 Detalle de ofertas para modal (al hacer clic en el mapa)
     */
    public function getDetails(Request $request)
    {
        $country = $request->get('country');
        $city    = $request->get('city');

        $query = JobOffer::query()
            ->select(
                'title',
                'company',
                'modality',
                'salary_min',
                'salary_max',
                'currency',
                'source',
                'url',
                DB::raw('DATE(published_at) as date')
            );

        if ($country) $query->where('country', $country);
        if ($city) $query->where('city', $city);

        $offers = $query->orderByDesc('published_at')
            ->limit(50)
            ->get();

        return response()->json([
            'country' => $country,
            'city' => $city,
            'offers' => $offers,
            'count' => $offers->count(),
            'message' => "🧾 Detalle de ofertas en " . ($city ? "$city, $country" : $country),
        ]);
    }

    /**
     * ⚙️ Lógica base (no se toca)
     */
    private function buildResponse(array $filters = [], $bounds = null, $zoom = null)
    {
        $query = JobOffer::query();

        foreach ($filters as $field => $value) {
            $query->where($field, $value);
        }

        $query->whereYear('published_at', now()->year)
            ->whereRaw('QUARTER(published_at) = QUARTER(CURDATE())');

        if ($bounds) {
            $query->whereBetween('latitude', [$bounds['lat_min'], $bounds['lat_max']])
                ->whereBetween('longitude', [$bounds['lng_min'], $bounds['lng_max']]);
        }

        if ($zoom < 5) {
            $results = $query->selectRaw("
                country,
                AVG(latitude) as lat,
                AVG(longitude) as lng,
                COUNT(*) as total
            ")->groupBy('country')->get();
        } elseif ($zoom < 8) {
            $results = $query->selectRaw("
                country, city,
                AVG(latitude) as lat,
                AVG(longitude) as lng,
                COUNT(*) as total
            ")->groupBy('country', 'city')->get();
        } else {
            $results = $query->select('country', 'city', 'latitude as lat', 'longitude as lng')->get();
        }

        return [
            'results' => $results,
            'message' => '🌍 Distribución lista para heatmap con carga progresiva.',
        ];
    }
}
