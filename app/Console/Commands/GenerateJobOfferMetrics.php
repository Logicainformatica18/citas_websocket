<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\JobOffer;
use App\Models\Language;
use App\Models\LanguageMetric;
use Illuminate\Support\Str;

class GenerateJobOfferMetrics extends Command
{
    protected $signature = 'joboffers:metrics';
    protected $description = 'Genera métricas de lenguajes basadas en las ofertas almacenadas (CSV importado)';

    public function handle()
    {
        $this->info("📊 Generando métricas desde job_offers...");

        $languages = Language::pluck('id', 'name');
        $total = 0;

        foreach ($languages as $languageName => $languageId) {
            $offers = JobOffer::where('title', 'like', "%{$languageName}%")->get();

            if ($offers->isEmpty()) {
                $this->line("— Sin coincidencias para {$languageName}");
                continue;
            }

            $countries = [];
            $modalities = [];

            foreach ($offers as $offer) {
                $country = $offer->country ?? 'Desconocido';
                $modality = $offer->modality ?? 'unknown';

                $countries[$country] = ($countries[$country] ?? 0) + 1;
                $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;
            }

            LanguageMetric::where('language_id', $languageId)
                ->where('source', 'LocalCSV')
                ->whereDate('run_date', now()->toDateString())
                ->delete();

            LanguageMetric::create([
                'language_id' => $languageId,
                'jobs_found_count' => $offers->count(),
                'jobs_new_count' => 0,
                'countries_breakdown' => $countries,
                'modality_breakdown' => $modalities,
                'run_date' => now(),
                'source' => 'LocalCSV',
            ]);


            $this->info("✅ {$offers->count()} ofertas procesadas para {$languageName}");
            $total += $offers->count();
        }

        $this->info("🎉 Métricas completadas ({$total} ofertas totales analizadas).");
    }
}
