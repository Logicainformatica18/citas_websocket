<?php

namespace App\Console\Commands\Trends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\MarketEntity;

class DiscoverMacroTrendsCommand extends Command
{
    protected $signature = 'trends:populate-macro {--min_jobs=20}';
    protected $description = 'Detecta macro tendencias del año y trimestre actual usando FULLTEXT';

    public function handle()
    {
        $minJobs = (int) $this->option('min_jobs');

        // 🔥 Año y trimestre automáticos del sistema
        $year = now()->year;
        $quarter = now()->quarter;

        $this->info("📅 Procesando año {$year} Q{$quarter}");
        $this->info("🔍 Detectando macro tendencias con FULLTEXT...");

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Obtener tendencias SOLO del periodo actual
        |--------------------------------------------------------------------------
        */

        $candidates = DB::table('entity_trends')
            ->select('trend_name')
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->groupBy('trend_name')
            ->get();

        if ($candidates->isEmpty()) {
            $this->warn("⚠ No hay tendencias registradas para {$year} Q{$quarter}");
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Procesar cada tendencia
        |--------------------------------------------------------------------------
        */

        foreach ($candidates as $candidate) {

            $trendName = trim($candidate->trend_name);

            if (empty($trendName)) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Buscar ofertas del mismo año y trimestre usando FULLTEXT
            |--------------------------------------------------------------------------
            */

            $jobs = DB::table('job_offers')
                ->select('id')
                ->whereYear('published_at', $year)
                ->whereRaw('QUARTER(published_at) = ?', [$quarter])
                ->whereRaw(
                    "MATCH(title, skills, description)
                     AGAINST (? IN NATURAL LANGUAGE MODE)",
                    [$trendName]
                )
                ->get();

            $jobCount = $jobs->count();

            if ($jobCount < $minJobs) {
                $this->info("⛔ {$trendName} no supera mínimo ({$jobCount} ofertas)");
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | 3️⃣ Crear o recuperar macro tendencia
            |--------------------------------------------------------------------------
            */

            $slug = Str::slug($trendName);

            $entity = MarketEntity::firstOrCreate(
                [
                    'slug' => $slug,
                    'entity_type' => 'macro_trend'
                ],
                [
                    'name'        => $trendName,
                    'origin'      => 'trend',
                    'category'    => 'macro_trend',
                    'has_trend'   => 1,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | 4️⃣ Insertar relaciones en macro_trend_job
            |--------------------------------------------------------------------------
            */

            $insertData = [];

            foreach ($jobs as $job) {
                $insertData[] = [
                    'job_offer_id'     => $job->id,
                    'market_entity_id' => $entity->id,
                    'created_at'       => now(),
                ];
            }

            if (!empty($insertData)) {

                $chunks = array_chunk($insertData, 500);

                foreach ($chunks as $chunk) {
                    DB::table('macro_trend_job')->insertOrIgnore($chunk);
                }
            }

            $this->info("✅ {$trendName} enlazada a {$jobCount} ofertas.");
        }

        $this->info("🎯 Macro tendencias procesadas correctamente.");
    }
}
