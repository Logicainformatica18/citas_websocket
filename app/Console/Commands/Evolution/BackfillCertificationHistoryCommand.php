<?php

namespace App\Console\Commands\Evolution;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Prueba;
use Carbon\Carbon;

class BackfillCertificationHistoryCommand extends Command
{
    protected $signature = 'certifications:backfill-all {year=2026}';
    protected $description = 'Reconstruye el historial (Semanal, Quincenal y Mensual) para certificaciones guardando la foto exacta acumulada de cada fecha de corte.';

    public function handle()
    {
        $year = (int) $this->argument('year');
        $this->info("=== INICIANDO RECONSTRUCCIÓN INTEGRAL (MÉTODO ACUMULADO) PARA CERTIFICACIONES - AÑO {$year} ===");

        try { 
            $weights = Prueba::getActive('certifications'); 
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
            $this->comment("Procesando cortes acumulados Certs: {$monthName}");
            $this->comment("=============================================");

            $startOfMonth = Carbon::create($year, $currentMonth, 1)->startOfDay();
            $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

            // 📅 RANGOS CALCULADOS POR DÍA DE CALENDARIO ESTÁTICO
            $tramos = [
                // Semanas estrictas
                [
                    'type' => 'weekly',
                    'label' => "Semana 1 - {$monthName} {$year}",
                    'start_date' => Carbon::create($year, $currentMonth, 1)->startOfDay(),
                    'end_date' => Carbon::create($year, $currentMonth, 7)->endOfDay(),
                ],
                [
                    'type' => 'weekly',
                    'label' => "Semana 2 - {$monthName} {$year}",
                    'start_date' => Carbon::create($year, $currentMonth, 8)->startOfDay(),
                    'end_date' => Carbon::create($year, $currentMonth, 14)->endOfDay(),
                ],
                [
                    'type' => 'weekly',
                    'label' => "Semana 3 - {$monthName} {$year}",
                    'start_date' => Carbon::create($year, $currentMonth, 15)->startOfDay(),
                    'end_date' => Carbon::create($year, $currentMonth, 21)->endOfDay(),
                ],
                [
                    'type' => 'weekly',
                    'label' => "Semana 4 - {$monthName} {$year}",
                    'start_date' => Carbon::create($year, $currentMonth, 22)->startOfDay(),
                    'end_date' => $endOfMonth->copy(), // Absorbe hasta el 28, 30 o 31 según el mes
                ],
                // Quincenas estrictas
                [
                    'type' => 'biweekly',
                    'label' => "1ra Quincena {$monthName} - {$year}",
                    'start_date' => Carbon::create($year, $currentMonth, 1)->startOfDay(),
                    'end_date' => Carbon::create($year, $currentMonth, 15)->endOfDay(),
                ],
                [
                    'type' => 'biweekly',
                    'label' => "2da Quincena {$monthName} - {$year}",
                    'start_date' => Carbon::create($year, $currentMonth, 16)->startOfDay(),
                    'end_date' => $endOfMonth->copy(),
                ],
                // Mensual estricto
                [
                    'type' => 'monthly',
                    'label' => "{$monthName} - {$year}",
                    'start_date' => $startOfMonth->copy(),
                    'end_date' => $endOfMonth->copy(),
                ]
            ];

            foreach ($tramos as $tramo) {
                $startRange = $tramo['start_date'];
                $endRange = $tramo['end_date'];

                if ($endRange->greaterThan(Carbon::now())) {
                    continue;
                }

                $startString = $startRange->format('Y-m-d');
                $endString = $endRange->format('Y-m-d');

                $semester = $currentMonth <= 6 ? 1 : 2;
                $semStart = $semester === 1 ? "$year-01-01" : "$year-07-01";

                // 1. Subquery Laboral
                $laborSub = DB::table('certification_job as cj')
                    ->join('job_offers as j', 'j.id', '=', 'cj.job_offer_id')
                    ->whereBetween('j.published_at', [$semStart, $endString])
                    ->groupBy('cj.market_entity_id')
                    ->select('cj.market_entity_id', DB::raw('COUNT(DISTINCT cj.job_offer_id) as offers'));

                $maxLabor = max(DB::query()->fromSub($laborSub, 'x')->max('offers'), 1);

                // 2. Subquery Tendencias
                $trendSub = DB::table('entity_trends as et')
                    ->join('market_entities as me', function ($j) {
                        $j->on('me.id', '=', 'et.market_entity_id')
                          ->where('me.entity_type', 'certification');
                    })
                    ->whereBetween('et.created_at', [$semStart, $endString])
                    ->groupBy('me.id')
                    ->select('me.id as certification_id', DB::raw('COUNT(DISTINCT et.id) as report_mentions'));

                $maxTrend = max(DB::query()->fromSub($trendSub, 'r')->max('report_mentions'), 1);

                $totalReports = max(
                    DB::table('entity_trends as et')
                        ->join('market_entities as me', function ($j) {
                            $j->on('me.id', '=', 'et.market_entity_id')
                              ->where('me.entity_type', 'certification');
                        })
                        ->whereBetween('et.created_at', [$semStart, $endString])
                        ->count('et.id'),
                    1
                );

                // 3. Query Principal
                $certifications = DB::table('market_entities as me')
                    ->leftJoinSub($laborSub, 'labor', 'labor.market_entity_id', '=', 'me.id')
                    ->leftJoinSub($trendSub, 'trends', 'trends.certification_id', '=', 'me.id')
                    ->where('me.entity_type', 'certification')
                    ->select(
                        'me.id as market_entity_id',
                        DB::raw('COALESCE(labor.offers, 0) as total_jobs'),
                        DB::raw('COALESCE(trends.report_mentions, 0) as trend_reports'),
                        DB::raw("ROUND(((LOG(COALESCE(labor.offers, 0) + 1) / LOG({$maxLabor} + 1)) * 100), 1) as labor_score"),
                        DB::raw("ROUND(((LOG(COALESCE(trends.report_mentions, 0) + 1) / LOG({$maxTrend} + 1)) * 100), 1) as trend_score"),
                        DB::raw("
                            ROUND(
                                (
                                    (LOG(COALESCE(labor.offers, 0) + 1) / LOG({$maxLabor} + 1)) * 100 * {$laborWeight}
                                    +
                                    (COALESCE(trends.report_mentions, 0) / {$totalReports}) * 100 * {$trendWeight}
                                ), 
                            1) as final_score
                        ")
                    )
                    ->get()
                    ->sortByDesc('final_score')
                    ->values();

                // 4. Guardado transaccional limpiando duplicados de tramos previos amorfos
                if ($certifications->isNotEmpty()) {
                    DB::transaction(function () use ($certifications, $tramo, $year, $startString, $endString) {
                        
                        // Eliminar registros viejos del mismo periodo para evitar que se pisen o queden huerfanos con fechas corridas
                        DB::table('certification_evolution_cache')
                            ->where('year', $year)
                            ->where('period_type', $tramo['type'])
                            ->where('period_label', $tramo['label'])
                            ->delete();

                        $position = 1;
                        foreach ($certifications as $cert) {
                            DB::table('certification_evolution_cache')->insert([
                                'market_entity_id' => $cert->market_entity_id,
                                'start_date'       => $startString,
                                'end_date'         => $endString,
                                'period_type'      => $tramo['type'],
                                'year'             => $year,
                                'period_label'     => $tramo['label'],
                                'jobs'             => $cert->total_jobs,
                                'trend_reports'    => $cert->trend_reports,
                                'labor_score'      => $cert->labor_score,
                                'trend_score'      => $cert->trend_score,
                                'final_score'      => $cert->final_score,
                                'ranking_position' => $position,
                                'updated_at'       => now(),
                                'created_at'       => now(),
                            ]);
                            $position++;
                        }
                    });
                    $this->info(" -> Foto guardada [{$tramo['type']}] ({$startString} al {$endString})");
                }
            }
            $currentMonth++;
        }
        $this->info("=== ¡PROCESO COMPLETADO! HISTORIAL DE CERTIFICACIONES AL 100% ===");
        return 0;
    }
}