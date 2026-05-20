<?php

namespace App\Console\Commands\Evolution;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Prueba;
use Carbon\Carbon;

class BackfillTechnologyHistoryCommand extends Command
{
    protected $signature = 'technologies:backfill-all {year=2026}';
    protected $description = 'Reconstruye el historial acumulado asignando rangos reales de fechas para que el frontend pinte del 01 al 07, 08 al 14, etc.';

    public function handle()
    {
        $year = (int) $this->argument('year');
        $this->info("=== INICIANDO RECONSTRUCCIÓN INTEGRAL (MÉTODO ACUMULADO CON RANGOS VISUALES) ===");

        // 1. Cargar Ponderaciones Activas
        try { $weights = Prueba::getActive('technologies'); } catch (\Throwable $e) { $weights = null; }
        $laborWeight = (float) ($weights?->labor_weight ?? 0.7);
        $trendWeight = (float) ($weights?->trend_weight ?? 0.3);

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        $currentMonth = 1;
        $endMonth = Carbon::now()->month;

        while ($currentMonth <= $endMonth) {
            $monthName = $meses[$currentMonth];
            $this->comment("👉 Procesando tramos para: {$monthName} {$year}");

            $startOfMonth = Carbon::create($year, $currentMonth, 1)->startOfDay();
            $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

            // 🌟 AQUÍ CONFIGURAMOS LOS RANGOS VISUALES REALES PARA TU FRONTEND
            $tramos = [
                // --- CORTES SEMANALES ---
                [
                    'type' => 'weekly',
                    'label' => "Semana 1 - {$monthName} {$year}",
                    'view_start' => Carbon::create($year, $currentMonth, 1)->format('Y-m-d'),
                    'view_end' => Carbon::create($year, $currentMonth, 7)->format('Y-m-d'),
                    'snapshot_date' => Carbon::create($year, $currentMonth, 7)->endOfDay(),
                ],
                [
                    'type' => 'weekly',
                    'label' => "Semana 2 - {$monthName} {$year}",
                    'view_start' => Carbon::create($year, $currentMonth, 8)->format('Y-m-d'),
                    'view_end' => Carbon::create($year, $currentMonth, 14)->format('Y-m-d'),
                    'snapshot_date' => Carbon::create($year, $currentMonth, 14)->endOfDay(),
                ],
                [
                    'type' => 'weekly',
                    'label' => "Semana 3 - {$monthName} {$year}",
                    'view_start' => Carbon::create($year, $currentMonth, 15)->format('Y-m-d'),
                    'view_end' => Carbon::create($year, $currentMonth, 21)->format('Y-m-d'),
                    'snapshot_date' => Carbon::create($year, $currentMonth, 21)->endOfDay(),
                ],
                [
                    'type' => 'weekly',
                    'label' => "Semana 4 - {$monthName} {$year}",
                    'view_start' => Carbon::create($year, $currentMonth, 22)->format('Y-m-d'),
                    'view_end' => $endOfMonth->format('Y-m-d'),
                    'snapshot_date' => $endOfMonth->copy(),
                ],
                // --- CORTES QUINCENALES ---
                [
                    'type' => 'biweekly',
                    'label' => "1ra Quincena {$monthName} - {$year}",
                    'view_start' => Carbon::create($year, $currentMonth, 1)->format('Y-m-d'),
                    'view_end' => Carbon::create($year, $currentMonth, 15)->format('Y-m-d'),
                    'snapshot_date' => Carbon::create($year, $currentMonth, 15)->endOfDay(),
                ],
                [
                    'type' => 'biweekly',
                    'label' => "2da Quincena {$monthName} - {$year}",
                    'view_start' => Carbon::create($year, $currentMonth, 16)->format('Y-m-d'),
                    'view_end' => $endOfMonth->format('Y-m-d'),
                    'snapshot_date' => $endOfMonth->copy(),
                ],
                // --- CORTE MENSUAL ---
                [
                    'type' => 'monthly',
                    'label' => "{$monthName} - {$year}",
                    'view_start' => Carbon::create($year, $currentMonth, 1)->format('Y-m-d'),
                    'view_end' => $endOfMonth->format('Y-m-d'),
                    'snapshot_date' => $endOfMonth->copy(),
                ]
            ];

            foreach ($tramos as $tramo) {
                $snapshot = $tramo['snapshot_date'];

                if ($snapshot->greaterThan(Carbon::now())) {
                    continue;
                }

                $dateString = $snapshot->format('Y-m-d');

                // LÓGICA ACUMULADA DEL INDEX (Se queda tal cual te funcionaba para no perder vacantes)
                $semester = $currentMonth <= 6 ? 1 : 2;
                $semStart = $semester === 1 ? "$year-01-01" : "$year-07-01";
                $quarters = $semester === 1 ? [1, 2] : [3, 4];

                $laborSub = DB::table('technology_job as tj')
                    ->join('job_offers as j', 'j.id', '=', 'tj.job_offer_id')
                    ->whereBetween('j.published_at', [$semStart, $dateString]) // Mantiene tu acumulado real
                    ->groupBy('tj.market_entity_id')
                    ->select('tj.market_entity_id', DB::raw('COUNT(DISTINCT tj.job_offer_id) as offers'));

                $maxLabor = max(DB::query()->fromSub($laborSub, 'x')->max('offers'), 1);

                $trendSub = DB::table('entity_trends as et')
                    ->where('et.year', $year)
                    ->whereIn('et.quarter', $quarters)
                    ->groupBy('et.market_entity_id')
                    ->select('et.market_entity_id', DB::raw('COUNT(et.id) as trend_reports'));

                $maxTrendReports = max(DB::query()->fromSub($trendSub, 't')->max('trend_reports'), 1);

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

                if ($technologies->isNotEmpty()) {
                    DB::transaction(function () use ($technologies, $tramo, $year) {
                        $position = 1;
                        foreach ($technologies as $tech) {
                            // 🌟 SOLUCIÓN VISUAL: Usamos period_label y period_type en la clave primaria única.
                            // Pasamos los rangos correctos en 'start_date' y 'end_date' para que el frontend renderice (01 al 07), (08 al 14), etc.
                            DB::table('technology_evolution_cache')->updateOrInsert(
                                [
                                    'market_entity_id' => $tech->market_entity_id,
                                    'period_label'     => $tramo['label'],
                                    'period_type'      => $tramo['type'],
                                ],
                                [
                                    'year'             => $year,
                                    'start_date'       => $tramo['view_start'], // Inyecta "2026-05-08"
                                    'end_date'         => $tramo['view_end'],   // Inyecta "2026-05-14"
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
                }
            }

            $currentMonth++;
        }

        $this->info("=== ¡PROCESO COMPLETADO! ACUMULADOS Y RANGOS VISUALES AL 100% ===");
        return 0;
    }
}