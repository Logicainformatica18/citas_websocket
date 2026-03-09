<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\WorldbankIndicator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WorldBankAIController extends Controller
{
    /**
     * 📊 Devuelve metadatos: indicadores, países y años disponibles.
     */
    public function metadata()
    {


        // 🔹 Traducciones de los indicadores más comunes
        $translations = [
            'Employment in industry (% of total employment) (modeled ILO estimate)' => 'Empleo en la industria (% del empleo total)',
            'Employment in services (% of total employment) (modeled ILO estimate)' => 'Empleo en servicios (% del empleo total)',
            'ICT goods exports (% of total goods exports)' => 'Exportaciones de bienes TIC (% del total de exportaciones)',
            'Individuals using the Internet (% of population)' => 'Usuarios de Internet (% de la población)',
            'Research and development expenditure (% of GDP)' => 'Gasto en I+D (% del PIB)',
            'Unemployment, total (% of total labor force) (modeled ILO estimate)' => 'Desempleo total (% de la fuerza laboral)',
        ];

        // 🔸 Indicadores únicos
        $indicators = WorldbankIndicator::select('indicator_code', 'indicator_name')
            ->distinct()
            ->orderBy('indicator_name')
            ->get()
            ->map(function ($i) use ($translations) {
                $i->indicator_name_es = $translations[$i->indicator_name] ?? $i->indicator_name;
                return $i;
            });

        // 🔸 Países únicos
        $countries = WorldbankIndicator::select('country_code', 'country_name')
            ->distinct()
            ->orderBy('country_name')
            ->get();

        // 🔸 Años disponibles
        $years = WorldbankIndicator::select('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        return response()->json([
            'indicators' => $indicators,
            'countries'  => $countries,
            'years'      => $years,
        ]);
    }

    /**
     * 📈 Obtiene los datos filtrados para el gráfico
     */
    public function getData(Request $request)
    {
        $indicator = $request->get('indicator');
        $countries = $request->get('countries', []);
        $from      = $request->get('from', 2020);
        $to        = $request->get('to', 2026);

        Log::info("📩 [WorldBankAIController@getData] Parámetros:", [
            'indicator' => $indicator,
            'countries' => $countries,
            'from'      => $from,
            'to'        => $to,
        ]);

        $query = WorldbankIndicator::query()
            ->select('country_code', 'country_name', 'indicator_code', 'indicator_name', 'year', 'value')
            ->where('indicator_code', $indicator)
            ->whereBetween('year', [$from, $to])
            ->whereNotNull('value');

        // 🔹 Filtro de países (si hay selección)
        if (!empty($countries)) {
            $query->whereIn('country_code', $countries);
        }

        $results = $query->orderBy('country_name')
            ->orderBy('year')
            ->get();

        // 🔹 Estructura clara para el frontend
        $response = [
            'indicator' => $indicator,
            'from' => $from,
            'to' => $to,
            'count' => $results->count(),
            'results' => $results,
        ];

        Log::info("✅ [WorldBankAIController@getData] Datos generados", [
            'count' => $results->count(),
        ]);

        return response()->json($response);
    }
}
