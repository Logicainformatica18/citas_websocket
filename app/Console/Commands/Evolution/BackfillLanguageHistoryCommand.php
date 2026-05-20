<?php

namespace App\Console\Commands\Evolution;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Prueba;
use Carbon\Carbon;

class BackfillLanguageHistoryCommand extends Command
{
    protected $signature = 'languages:backfill-all {year=2026}';
    protected $description = 'Reconstruye el historial de Lenguajes (Semanal, Quincenal y Mensual) guardando la foto exacta acumulada de cada fecha de corte.';

    public function handle()
    {
        $year = (int) $this->argument('year');
        $this->info("=== INICIANDO RECONSTRUCCIÓN INTEGRAL (MÉTODO ACUMULADO) PARA EL AÑO {$year} - LENGUAJES ===");

        // 1. Cargar Ponderaciones Activas para Lenguajes
        try { 
            $weights = Prueba::getActive('languages'); // 🔥 Modificado para lenguajes
        } catch (\Throwable $e) { 
            $weights = null; 
        }
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
            $this->comment("=============================================");
            $this->comment("Procesando cortes acumulados para Lenguajes: {$monthName}");
            $this->comment("=============================================");

            $startOfMonth = Carbon::create($year, $currentMonth, 1)->startOfDay();
            $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

            // Definimos los días exactos donde se toma la "foto" en el tiempo
            $tramos = [
                // --- CORTES SEMANALES ---
                [
                    'type' => 'weekly',
                    'label' => "Semana 1 - {$monthName} {$year}",
                    'snapshot_date' => Carbon::create($year, $currentMonth, 7)->endOfDay(),
                ],
                [
                    'type' => 'weekly',
                    'label' => "Semana 2 - {$monthName} {$year}",
                    'snapshot_date' => Carbon::create($year, $currentMonth, 14)->endOfDay(),
                ],
                [
                    'type' => 'weekly',
                    'label' => "Semana 3 - {$monthName} {$year}",
                    'snapshot_date' => Carbon::create($year, $currentMonth, 21)->endOfDay(),
                ],
                [
                    'type' => 'weekly',
                    'label' => "Semana 4 - {$monthName} {$year}",
                    'snapshot_date' => $endOfMonth->copy(),
                ],
                // --- CORTES QUINCENALES ---
                [
                    'type' => 'biweekly',
                    'label' => "1ra Quincena {$monthName} - {$year}",
                    'snapshot_date' => Carbon::create($year, $currentMonth, 15)->endOfDay(),
                ],
                [
                    'type' => 'biweekly',
                    'label' => "2da Quincena {$monthName} - {$year}",
                    'snapshot_date' => $endOfMonth->copy(),
                ],
                // --- CORTE MENSUAL ---
                [
                    'type' => 'monthly',
                    'label' => "{$monthName} - {$year}",
                    'snapshot_date' => $endOfMonth->copy(),
                ]
            ];

            foreach ($tramos as $tramo) {
                $snapshot = $tramo['snapshot_date'];

                // Si la fecha de la foto pertenece al futuro, no la procesamos
                if ($snapshot->greaterThan(Carbon::now())) {
                    continue;
                }

                $dateString = $snapshot->format('Y-m-d');

                // LÓGICA COINCIDENTE CON EL INDEX DE LENGUAJES:
                $semester = $currentMonth <= 6 ? 1 : 2;
                $semStart = $semester === 1 ? "$year-01-01" : "$year-07-01";
                $quarters = $semester === 1 ? [1, 2] : [3, 4];

                // Subquery Laboral: Acumula desde el inicio del semestre hasta la fecha de corte en language_job
                $laborSub = DB::table('language_job as lj') // 🔥 Cambiado a language_job
                    ->join('job_offers as j', 'j.id', '=', 'lj.job_offer_id')
                    ->whereBetween('j.published_at', [$semStart, $dateString])
                    ->groupBy('lj.market_entity_id')
                    ->select('lj.market_entity_id', DB::raw('COUNT(DISTINCT lj.job_offer_id) as offers'));

                $maxLabor = max(DB::query()->fromSub($laborSub, 'x')->max('offers'), 1);

                // Subquery Tendencias: Mantiene el semestre correspondiente filtrado para lenguajes
                $trendSub = DB::table('entity_trends as et')
                    ->join('market_entities as me', function ($j) {
                        $j->on('me.id', '=', 'et.market_entity_id')
                          ->where('me.entity_type', 'language'); // 🔥 Forzar tipo language
                    })
                    ->where('et.year', $year)
                    ->whereIn('et.quarter', $quarters)
                    ->groupBy('me.id')
                    ->select('me.id as language_id', DB::raw('COUNT(DISTINCT et.id) as report_mentions'));

                $maxTrendReports = max(DB::query()->fromSub($trendSub, 't')->max('report_mentions'), 1);

                // Generar ranking matemático idéntico al Index de Lenguajes
                $languages = DB::table('market_entities as me')
                    ->leftJoinSub($laborSub, 'labor', 'labor.market_entity_id', '=', 'me.id')
                    ->leftJoinSub($trendSub, 'trends', 'trends.language_id', '=', 'me.id')
                    ->where('me.entity_type', 'language') // 🔥 Forzar tipo language
                    ->select(
                        'me.id as market_entity_id',
                        DB::raw('COALESCE(labor.offers, 0) as total_jobs'),
                        DB::raw('COALESCE(trends.report_mentions, 0) as total_trends'),
                        DB::raw("ROUND(((LOG(COALESCE(labor.offers,0)+1) / LOG({$maxLabor}+1)) * 100), 1) as labor_score"),
                        DB::raw("ROUND(((LOG(COALESCE(trends.report_mentions,0)+1) / LOG({$maxTrendReports}+1)) * 100), 1) as trend_score"),
                        DB::raw("ROUND((((LOG(COALESCE(labor.offers,0)+1) / LOG({$maxLabor}+1)) * 100 * {$laborWeight}) + ((LOG(COALESCE(trends.report_mentions,0)+1) / LOG({$maxTrendReports}+1)) * 100 * {$trendWeight})), 1) as final_score")
                    )
                    ->orderByDesc('final_score')
                    ->get();

                // Guardar en la caché usando la fecha de la foto como identificador
                if ($languages->isNotEmpty()) {
                    DB::transaction(function () use ($languages, $tramo, $year, $dateString) {
                        $position = 1;
                        foreach ($languages as $lang) {
                            // 🔥 Guardado en la tabla language_evolution_cache
                            DB::table('language_evolution_cache')->updateOrInsert(
                                [
                                    'market_entity_id' => $lang->market_entity_id,
                                    'start_date'       => $dateString, 
                                    'period_type'      => $tramo['type'],
                                ],
                                [
                                    'year'             => $year,
                                    'end_date'         => $dateString,
                                    'period_label'     => $tramo['label'],
                                    'jobs'             => $lang->total_jobs,
                                    'trend_reports'    => $lang->total_trends,
                                    'labor_score'      => $lang->labor_score,
                                    'trend_score'      => $lang->trend_score,
                                    'final_score'      => $lang->final_score,
                                    'ranking_position' => $position,
                                    'updated_at'       => now(),
                                    'created_at'       => now(),
                                ]
                            );
                            $position++;
                        }
                    });
                    $this->info(" -> Guardada foto de Lenguajes [{$tramo['type']}] calculada al corte del {$dateString}: '{$tramo['label']}'");
                }
            }

            $currentMonth++;
        }

        $this->info("=== ¡PROCESO COMPLETADO! HISTORIAL DE LENGUAJES SINCRONIZADO AL 100% ===");
        return 0;
    }
}