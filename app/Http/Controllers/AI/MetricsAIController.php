<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\LanguageMetric;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MetricsAIController extends Controller
{
    public function index()
    {
      

        // 📌 Últimos registros por lenguaje
        $latest = LanguageMetric::select('language_id', DB::raw('MAX(created_at) as last_created'))
            ->groupBy('language_id');

        $metrics = LanguageMetric::joinSub($latest, 'latest', function ($join) {
                $join->on('language_metrics.language_id', '=', 'latest.language_id')
                     ->on('language_metrics.created_at', '=', 'latest.last_created');
            })
            ->with('language:id,name')
            ->get();

        Log::info("📈 [MetricsAIController] Se encontraron " . $metrics->count() . " métricas de lenguajes.");

        // 🔹 Gráfico 1: Lenguajes más demandados
        $languageDemand = $metrics->map(fn($m) => [
            'name' => $m->language->name ?? 'N/A',
            'jobs' => $m->jobs_found_count ?? 0,
        ]);

     

        // 🔹 Gráfico 2: Modalidad global agregada
        $modalitySum = [];
        foreach ($metrics as $m) {
           $modalities = is_string($m->modality_breakdown)
    ? json_decode($m->modality_breakdown, true)
    : ($m->modality_breakdown ?? []);


            if (!empty($modalities)) {
                foreach ($modalities as $key => $count) {
                    $modalitySum[$key] = ($modalitySum[$key] ?? 0) + $count;
                }
            }
        }

     

        $response = [
            'language_demand' => $languageDemand,
            'modality_global' => $modalitySum,
        ];

       

        return response()->json($response);
    }
}
