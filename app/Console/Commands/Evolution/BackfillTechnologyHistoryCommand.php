<?php

namespace App\Console\Commands\Evolution;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Prueba;
use Carbon\Carbon;

class BackfillTechnologyHistoryCommand extends Command
{
    protected $signature = 'technologies:backfill-history {year=2026}';
    protected $description = 'Viaja al pasado semana a semana para reconstruir las fotos historicas que faltan.';

    public function handle()
    {
        $year = (int) $this->argument('year');
        $this->info("Reconstruyendo historial para el año {$year}...");

        // Ponderaciones por defecto
        try { $weights = Prueba::getActive('technologies'); } catch (\Throwable $e) { $weights = null; }
        $laborWeight = (float) ($weights?->labor_weight ?? 0.7);
        $trendWeight = (float) ($weights?->trend_weight ?? 0.3);

        // Definimos el inicio del semestre (1 de Enero) hasta el día de hoy
        $startDate = Carbon::create($year, 1, 1)->startOfWeek();
        $today = Carbon::now();

        // Iteramos semana a semana desde enero hasta hoy
        while ($startDate->lessThanOrEqualTo($today)) {
            
            // Simular el "HOY" de esa semana pasada (usaremos el domingo de esa semana como el momento de la foto)
            $snapshotDate = $startDate->copy()->endOfWeek();
            if ($snapshotDate->greaterThan($today)) {
                $snapshotDate = $today->copy();
            }

            $dateString = $snapshotDate->format('Y-m-d');
            $periodLabel = "Semana " . $snapshotDate->weekOfYear . " - " . $year;
            
            $this->info("Calculando foto para: {$periodLabel} (Corte al {$dateString})...");

            // Rango del semestre hasta ESA fecha del pasado
            $semStart = "$year-01-01";
            $semEnd   = "$year-06-30";

            // 1. Subquery Laboral simulada a esa fecha
            $laborSub = DB::table('technology_job as tj')
                ->join('job_offers as j', 'j.id', '=', 'tj.job_offer_id')
                ->whereBetween('j.published_at', [$semStart, $dateString]) // Solo lo publicado hasta ese día del pasado
                ->groupBy('tj.market_entity_id')
                ->select('tj.market_entity_id', DB::raw('COUNT(DISTINCT tj.job_offer_id) as offers'));

            $maxLabor = max(DB::query()->fromSub($laborSub, 'x')->max('offers'), 1);

            // 2. Subquery Tendencias simulada (Por simplicidad del cuadrante usaremos el semestre actual)
            $trendSub = DB::table('entity_trends as et')
                ->where('et.year', $year)
                ->whereIn('et.quarter', [1, 2])
                ->groupBy('et.market_entity_id')
                ->select('et.market_entity_id', DB::raw('COUNT(et.id) as trend_reports'));

            $maxTrendReports = max(DB::query()->fromSub($trendSub, 't')->max('trend_reports'), 1);

            // 3. Procesar ranking de esa semana
            $technologies = DB::table('market_entities as me')
                ->leftJoinSub($laborSub, 'labor', 'labor.market_entity_id', '=', 'me.id')
                ->leftJoinSub($trendSub, 'trends', 'trends.market_entity_id', '=', 'me.id')
                ->where('me.entity_type', 'technology')
                ->select(
                    'me.id as market_entity_id',
                    DB::raw('COALESCE(labor.offers, 0) as total_jobs'),
                    DB::raw('COALESCE(trends.trend_reports, 0) as total_trends'),
                    DB::raw("ROUND(((LOG(COALESCE(labor.offers,0)+1) / LOG({$maxLabor}+1)) * 100), 1) as labor_score"),
                    DB::raw("ROUND(((LOG(COALESCE(trends.trend_reports,0)+1) / LOG({$maxTrendReports}+1)) * 100), 1) as trend_score"),
                    DB::raw("ROUND((((LOG(COALESCE(labor.offers,0)+1) / LOG({$maxLabor}+1)) * 100 * {$laborWeight}) + ((LOG(COALESCE(trends.trend_reports,0)+1) / LOG({$maxTrendReports}+1)) * 100 * {$trendWeight})), 1) as final_score")
                )
                ->orderByDesc('final_score')
                ->get();

            // 4. Guardar en lote para esa semana
            DB::transaction(function () use ($technologies, $year, $dateString, $periodLabel) {
                $position = 1;
                foreach ($technologies as $tech) {
                    DB::table('technology_evolution_cache')->updateOrInsert(
                        [
                            'market_entity_id' => $tech->market_entity_id,
                            'start_date'       => $dateString,
                            'period_type'      => 'weekly',
                        ],
                        [
                            'year'             => $year,
                            'end_date'         => $dateString,
                            'period_label'     => $periodLabel,
                            'jobs'             => $tech->total_jobs,
                            'trend_reports'    => $tech->total_trends,
                            'labor_score'      => $tech->labor_score,
                            'trend_score'      => $tech->trend_score,
                            'final_score'      => $tech->final_score,
                            'ranking_position' => $position,
                            'updated_at'       => now(),
                            'created_at'       => now(),
                        ]
                    );
                    $position++;
                }
            });

            // Avanzar a la siguiente semana
            $startDate->addWeek();
        }

        $this->info("¡Historial completado con éxito!");
        return 0;
    }
}