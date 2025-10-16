<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\JobOffer;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\CityDemandExport;

class CityDemandAIController extends Controller
{
    /**
     * 📋 Devuelve metadata para filtros dinámicos
     */
    public function metadata()
    {
        Log::info("🌎 Cargando metadata CityDemandAIController");

        return response()->json([
            'countries' => JobOffer::whereNotNull('country')
                ->where('country', '<>', '')
                ->distinct()
                ->orderBy('country')
                ->pluck('country'),
            'modalities' => JobOffer::whereNotNull('modality')
                ->where('modality', '<>', '')
                ->distinct()
                ->orderBy('modality')
                ->pluck('modality'),
            'sources' => JobOffer::whereNotNull('source')
                ->where('source', '<>', '')
                ->distinct()
                ->orderBy('source')
                ->pluck('source'),
            'years' => JobOffer::selectRaw('YEAR(published_at) as year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year'),
        ]);
    }

    /**
     * 📊 Datos agregados por ciudad o país (para mapa interactivo)
     */
    public function getData(Request $request)
    {
        $year       = (int) $request->get('year', now()->year);
        $zoom       = (int) $request->get('zoom', 6);
        $sources    = (array) $request->get('sources', []);
        $countries  = (array) $request->get('countries', []);
        $modalities = (array) $request->get('modalities', []);
        $quarter    = $request->get('quarter');
        $startDate  = $request->get('start_date');
        $endDate    = $request->get('end_date');

        Log::info("📩 [CityDemandAIController@getData] Parámetros", compact(
            'year', 'zoom', 'sources', 'countries', 'modalities', 'quarter', 'startDate', 'endDate'
        ));

        // 🔹 Base: ignora ubicaciones remotas para el mapa
        $query = JobOffer::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where(function ($q) {
                $q->where('country', 'NOT LIKE', '%remote%')
                  ->where('country', 'NOT LIKE', '%remoto%')
                  ->where('city', 'NOT LIKE', '%remote%')
                  ->where('city', 'NOT LIKE', '%remoto%');
            });

        // 🔹 Año
        if ($year) $query->whereYear('published_at', $year);

        // 🔹 Fechas personalizadas
        if ($startDate && $endDate) {
            try {
                $query->whereBetween('published_at', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay(),
                ]);
            } catch (\Exception $e) {
                Log::warning("⚠️ Error al parsear fechas: {$e->getMessage()}");
            }
        }

        // 🔹 Trimestre
        $quarters = [
            'Q1' => [1,2,3], 'Q2' => [4,5,6], 'Q3' => [7,8,9], 'Q4' => [10,11,12],
        ];
        if ($quarter && isset($quarters[$quarter])) {
            $query->whereIn(DB::raw('MONTH(published_at)'), $quarters[$quarter]);
        }

        // 🔹 Filtros dinámicos
        if (!empty($sources)) $query->whereIn('source', $sources);
        if (!empty($countries)) $query->whereIn('country', $countries);
        if (!empty($modalities)) $query->whereIn('modality', $modalities);

        // 🔹 Agrupar por ciudad
        $results = $query->selectRaw("
            country, city,
            AVG(latitude) as lat,
            AVG(longitude) as lng,
            COUNT(*) as total
        ")->groupBy('country', 'city')->get();

        if ($results->isEmpty()) {
            return response()->json(['results' => [], 'message' => 'Sin datos para los filtros.']);
        }

        // 🔹 Normalizar intensidad
        $max = $results->max('total') ?: 1;
        $results->transform(fn($r) => tap($r, fn($x) => $x->intensity = round($r->total / $max, 3)));

        // 🔹 KPIs adicionales
        $totalOffers = $results->sum('total');

        $byModality = JobOffer::select('modality', DB::raw('COUNT(*) as total'))
            ->when($year, fn($q) => $q->whereYear('published_at', $year))
            ->groupBy('modality')
            ->orderByDesc('total')
            ->get();

        $bySource = JobOffer::select('source', DB::raw('COUNT(*) as total'))
            ->when($year, fn($q) => $q->whereYear('published_at', $year))
            ->groupBy('source')
            ->orderByDesc('total')
            ->get();

        $topCountries = $results
            ->groupBy('country')
            ->map(fn($g) => $g->sum('total'))
            ->sortDesc()
            ->take(5)
            ->map(fn($total, $country) => ['country' => $country, 'total' => $total])
            ->values();

        return response()->json([
            'filters' => compact('sources', 'countries', 'modalities', 'year', 'quarter', 'startDate', 'endDate'),
            'count'   => $results->count(),
            'max'     => $max,
            'results' => $results,
            'top_countries' => $topCountries,
            'summary' => [
                'total_offers' => $totalOffers,
                'top_modality' => $byModality->first(),
                'top_source' => $bySource->first(),
            ],
            'message' => "📊 Total de demanda por ciudad (sin ubicaciones remotas).",
        ]);
    }

    /**
     * 📤 Exporta resultados a Excel o PDF (incluye remotos)
     */
    public function export(Request $request)
    {
        $format     = $request->get('format', 'excel');
        $year       = (int) $request->get('year', now()->year);
        $sources    = (array) $request->get('sources', []);
        $countries  = (array) $request->get('countries', []);
        $modalities = (array) $request->get('modalities', []);
        $quarter    = $request->get('quarter');
        $startDate  = $request->get('start_date');
        $endDate    = $request->get('end_date');

        $query = JobOffer::query()
            ->select(
                'title', 'company', 'country', 'city',
                'modality', 'source',
                DB::raw('DATE(published_at) as published_at')
            )
            ->whereYear('published_at', $year);

        // Aplicar mismos filtros que el mapa
        if (!empty($sources)) $query->whereIn('source', $sources);
        if (!empty($countries)) $query->whereIn('country', $countries);
        if (!empty($modalities)) $query->whereIn('modality', $modalities);

        if ($startDate && $endDate) {
            $query->whereBetween('published_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ]);
        }

        $quarters = [
            'Q1' => [1,2,3], 'Q2' => [4,5,6], 'Q3' => [7,8,9], 'Q4' => [10,11,12],
        ];
        if ($quarter && isset($quarters[$quarter])) {
            $query->whereIn(DB::raw('MONTH(published_at)'), $quarters[$quarter]);
        }

        $data = $query->orderByDesc('published_at')->get();

        // 🧾 PDF
        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.city-demand', [
                'data' => $data,
                'filters' => compact('year', 'sources', 'countries', 'modalities'),
            ])->setPaper('a4', 'landscape');

            return $pdf->download("city-demand-{$year}.pdf");
        }

        // 📊 Excel
        return Excel::download(new CityDemandExport($data), "city-demand-{$year}.xlsx");
    }
}
