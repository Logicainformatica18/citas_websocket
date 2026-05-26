<?php

namespace App\Console\Commands\Evolution;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Prueba;
use Carbon\Carbon;

class BackfillCertificationHistoryCommand extends Command
{
    protected $signature = 'certifications:backfill-all {year=2026}';
    protected $description = 'Reconstruye el historial (Semanal, Quincenal y Mensual) para certificaciones guardando la foto exacta acumulada de cada fecha de corte.';

    public function handle()
    {
        $year = (int) $this->argument('year');

        /* ==================================================
           🔍 LOG INICIAL — CONTEXTO DE EJECUCIÓN
        ================================================== */
        $context = [
            'year'        => $year,
            'connection'  => DB::connection()->getName(),
            'database'    => DB::connection()->getDatabaseName(),
            'host'        => DB::connection()->getConfig('host'),
            'env'         => app()->environment(),
            'now'         => now()->toDateTimeString(),
        ];

        Log::info('[BACKFILL_CERT_START]', $context);
        $this->info("=== INICIANDO RECONSTRUCCIÓN INTEGRAL (MÉTODO ACUMULADO) PARA CERTIFICACIONES - AÑO {$year} ===");
        $this->table(['Campo', 'Valor'], collect($context)->map(fn($v, $k) => [$k, $v])->values());

        /* ==================================================
           🔍 VERIFICACIÓN PREVIA — DATOS FUENTE
        ================================================== */
        $sanity = [
            'market_entities_certification' => DB::table('market_entities')
                ->where('entity_type', 'certification')
                ->count(),
            'certification_job_total'       => DB::table('certification_job')->count(),
            'job_offers_year'               => DB::table('job_offers')
                ->whereYear('published_at', $year)
                ->count(),
            'entity_trends_year_cert'       => DB::table('entity_trends as et')
                ->join('market_entities as me', 'me.id', '=', 'et.market_entity_id')
                ->where('me.entity_type', 'certification')
                ->whereYear('et.created_at', $year)
                ->count(),
            'cache_existing_rows'           => DB::table('certification_evolution_cache')
                ->where('year', $year)
                ->count(),
        ];

        Log::info('[BACKFILL_CERT_SANITY_CHECK]', $sanity);
        $this->warn('🔎 Verificación de datos fuente:');
        $this->table(['Tabla / Filtro', 'Filas'], collect($sanity)->map(fn($v, $k) => [$k, $v])->values());

        if ($sanity['market_entities_certification'] === 0) {
            Log::error('[BACKFILL_CERT_NO_ENTITIES]', ['msg' => 'No hay market_entities con entity_type=certification']);
            $this->error('❌ NO HAY CERTIFICACIONES EN market_entities. Abortando.');
            return 1;
        }

        if ($sanity['certification_job_total'] === 0) {
            Log::warning('[BACKFILL_CERT_NO_JOBS]', ['msg' => 'Tabla certification_job vacía']);
            $this->error('⚠️ ATENCIÓN: certification_job está vacía. Los scores laborales serán 0.');
        }

        /* ==================================================
           1. Cargar Ponderaciones Activas
        ================================================== */
        try {
            $weights = Prueba::getActive('certifications');
        } catch (\Throwable $e) {
            Log::error('[BACKFILL_CERT_WEIGHTS_ERROR]', ['error' => $e->getMessage()]);
            $weights = null;
        }

        $laborWeight = (float) ($weights?->labor_weight ?? 0.7);
        $trendWeight = (float) ($weights?->trend_weight ?? 0.3);

        Log::info('[BACKFILL_CERT_WEIGHTS]', [
            'labor'  => $laborWeight,
            'trend'  => $trendWeight,
            'source' => $weights ? 'db' : 'default',
        ]);
        $this->info("Pesos: labor={$laborWeight} | trend={$trendWeight}");

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        $currentMonth = 1;
        $endMonth = Carbon::now()->month;

        /* ==================================================
           CONTADORES GLOBALES
        ================================================== */
        $totalTramosProcesados   = 0;
        $totalTramosOmitidos     = 0;
        $totalInsertsRealizados  = 0;
        $totalErroresTramo       = 0;

        while ($currentMonth <= $endMonth) {
            $monthName = $meses[$currentMonth];
            $this->comment("=============================================");
            $this->comment("Procesando cortes acumulados Certs: {$monthName}");
            $this->comment("=============================================");

            Log::info('[BACKFILL_CERT_MONTH]', ['month' => $currentMonth, 'name' => $monthName]);

            $startOfMonth = Carbon::create($year, $currentMonth, 1)->startOfDay();
            $endOfMonth   = $startOfMonth->copy()->endOfMonth()->endOfDay();

            // 📅 RANGOS CALCULADOS POR DÍA DE CALENDARIO ESTÁTICO
            $tramos = [
                // Semanas estrictas
                [
                    'type'       => 'weekly',
                    'label'      => "Semana 1 - {$monthName} {$year}",
                    'start_date' => Carbon::create($year, $currentMonth, 1)->startOfDay(),
                    'end_date'   => Carbon::create($year, $currentMonth, 7)->endOfDay(),
                ],
                [
                    'type'       => 'weekly',
                    'label'      => "Semana 2 - {$monthName} {$year}",
                    'start_date' => Carbon::create($year, $currentMonth, 8)->startOfDay(),
                    'end_date'   => Carbon::create($year, $currentMonth, 14)->endOfDay(),
                ],
                [
                    'type'       => 'weekly',
                    'label'      => "Semana 3 - {$monthName} {$year}",
                    'start_date' => Carbon::create($year, $currentMonth, 15)->startOfDay(),
                    'end_date'   => Carbon::create($year, $currentMonth, 21)->endOfDay(),
                ],
                [
                    'type'       => 'weekly',
                    'label'      => "Semana 4 - {$monthName} {$year}",
                    'start_date' => Carbon::create($year, $currentMonth, 22)->startOfDay(),
                    'end_date'   => $endOfMonth->copy(), // 28, 30 o 31 según el mes
                ],
                // Quincenas estrictas
                [
                    'type'       => 'biweekly',
                    'label'      => "1ra Quincena {$monthName} - {$year}",
                    'start_date' => Carbon::create($year, $currentMonth, 1)->startOfDay(),
                    'end_date'   => Carbon::create($year, $currentMonth, 15)->endOfDay(),
                ],
                [
                    'type'       => 'biweekly',
                    'label'      => "2da Quincena {$monthName} - {$year}",
                    'start_date' => Carbon::create($year, $currentMonth, 16)->startOfDay(),
                    'end_date'   => $endOfMonth->copy(),
                ],
                // Mensual estricto
                [
                    'type'       => 'monthly',
                    'label'      => "{$monthName} - {$year}",
                    'start_date' => $startOfMonth->copy(),
                    'end_date'   => $endOfMonth->copy(),
                ],
            ];

            foreach ($tramos as $tramo) {
                $startRange = $tramo['start_date'];
                $endRange   = $tramo['end_date'];

                if ($endRange->greaterThan(Carbon::now())) {
                    $totalTramosOmitidos++;
                    Log::info('[BACKFILL_CERT_TRAMO_SKIP_FUTURE]', [
                        'label' => $tramo['label'],
                        'end'   => $endRange->toDateString(),
                    ]);
                    continue;
                }

                $startString = $startRange->format('Y-m-d');
                $endString   = $endRange->format('Y-m-d');

                $semester = $currentMonth <= 6 ? 1 : 2;
                $semStart = $semester === 1 ? "$year-01-01" : "$year-07-01";

                try {
                    /* ==================================================
                       1. Subquery Laboral
                    ================================================== */
                    $laborSub = DB::table('certification_job as cj')
                        ->join('job_offers as j', 'j.id', '=', 'cj.job_offer_id')
                        ->whereBetween('j.published_at', [$semStart, $endString])
                        ->groupBy('cj.market_entity_id')
                        ->select('cj.market_entity_id', DB::raw('COUNT(DISTINCT cj.job_offer_id) as offers'));

                    $maxLabor = max(DB::query()->fromSub($laborSub, 'x')->max('offers'), 1);

                    /* ==================================================
                       2. Subquery Tendencias
                    ================================================== */
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

                    /* ==================================================
                       3. Query Principal
                    ================================================== */
                    $certifications = DB::table('market_entities as me')
                        ->leftJoinSub($laborSub, 'labor', 'labor.market_entity_id', '=', 'me.id')
                        ->leftJoinSub($trendSub, 'trends', 'trends.certification_id', '=', 'me.id')
                        ->where('me.entity_type', 'certification')
                        ->select(
                            'me.id as market_entity_id',
                            'me.name',
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

                    /* ==================================================
                       🔍 LOG DIAGNÓSTICO POR TRAMO
                    ================================================== */
                    $diag = [
                        'label'              => $tramo['label'],
                        'type'               => $tramo['type'],
                        'range'              => "{$startString} → {$endString}",
                        'sem_start'          => $semStart,
                        'max_labor'          => $maxLabor,
                        'max_trend'          => $maxTrend,
                        'total_reports'      => $totalReports,
                        'rows_returned'      => $certifications->count(),
                        'rows_with_jobs'     => $certifications->where('total_jobs', '>', 0)->count(),
                        'rows_with_trends'   => $certifications->where('trend_reports', '>', 0)->count(),
                        'top1_name'          => $certifications->first()->name ?? null,
                        'top1_final_score'   => $certifications->first()->final_score ?? null,
                        'top1_jobs'          => $certifications->first()->total_jobs ?? null,
                    ];
                    Log::info('[BACKFILL_CERT_TRAMO_DIAG]', $diag);

                    if ($certifications->isEmpty()) {
                        Log::warning('[BACKFILL_CERT_TRAMO_EMPTY]', $diag);
                        $this->warn(" ⚠️ Sin datos: [{$tramo['type']}] {$tramo['label']}");
                        $totalTramosOmitidos++;
                        continue;
                    }

                    /* ==================================================
                       4. Guardado transaccional con verificación
                    ================================================== */
                    $insertedCount = 0;

                    DB::transaction(function () use ($certifications, $tramo, $year, $startString, $endString, &$insertedCount) {

                        $deleted = DB::table('certification_evolution_cache')
                            ->where('year', $year)
                            ->where('period_type', $tramo['type'])
                            ->where('period_label', $tramo['label'])
                            ->delete();

                        Log::info('[BACKFILL_CERT_TRAMO_CLEAN]', [
                            'label'   => $tramo['label'],
                            'deleted' => $deleted,
                        ]);

                        $position = 1;
                        $batch = [];

                        foreach ($certifications as $cert) {
                            $batch[] = [
                                'market_entity_id' => $cert->market_entity_id,
                                'start_date'       => $startString,
                                'end_date'         => $endString,
                                'period_type'      => $tramo['type'],
                                'year'             => $year,
                                'period_label'     => $tramo['label'],
                                'jobs'             => (int)   $cert->total_jobs,
                                'trend_reports'    => (int)   $cert->trend_reports,
                                'labor_score'      => (float) $cert->labor_score,
                                'trend_score'      => (float) $cert->trend_score,
                                'final_score'      => (float) $cert->final_score,
                                'ranking_position' => $position,
                                'updated_at'       => now(),
                                'created_at'       => now(),
                            ];
                            $position++;
                        }

                        $insertedCount = count($batch);
                        DB::table('certification_evolution_cache')->insert($batch);
                    });

                    /* ==================================================
                       🔍 VERIFICACIÓN POST-INSERT
                    ================================================== */
                    $confirmed = DB::table('certification_evolution_cache')
                        ->where('year', $year)
                        ->where('period_type', $tramo['type'])
                        ->where('period_label', $tramo['label'])
                        ->count();

                    Log::info('[BACKFILL_CERT_TRAMO_OK]', [
                        'label'         => $tramo['label'],
                        'inserted'      => $insertedCount,
                        'confirmed_db'  => $confirmed,
                        'match'         => $confirmed === $insertedCount,
                    ]);

                    if ($confirmed !== $insertedCount) {
                        Log::error('[BACKFILL_CERT_TRAMO_MISMATCH]', [
                            'label'        => $tramo['label'],
                            'inserted'     => $insertedCount,
                            'confirmed_db' => $confirmed,
                        ]);
                        $this->error(" ❌ MISMATCH en {$tramo['label']}: insertados={$insertedCount} vs confirmados={$confirmed}");
                    }

                    $totalInsertsRealizados += $insertedCount;
                    $totalTramosProcesados++;

                    $this->info(" -> Foto guardada [{$tramo['type']}] ({$startString} al {$endString}): '{$tramo['label']}' ({$insertedCount} filas, DB confirmado: {$confirmed})");

                } catch (\Throwable $e) {
                    $totalErroresTramo++;
                    Log::error('[BACKFILL_CERT_TRAMO_ERROR]', [
                        'label'  => $tramo['label'],
                        'type'   => $tramo['type'],
                        'range'  => "{$startString} → {$endString}",
                        'error'  => $e->getMessage(),
                        'file'   => $e->getFile(),
                        'line'   => $e->getLine(),
                        'trace'  => $e->getTraceAsString(),
                    ]);
                    $this->error(" 💥 ERROR en {$tramo['label']}: {$e->getMessage()}");
                }
            }

            $currentMonth++;
        }

        /* ==================================================
           🔍 RESUMEN FINAL
        ================================================== */
        $finalCount = DB::table('certification_evolution_cache')
            ->where('year', $year)
            ->count();

        $finalByType = DB::table('certification_evolution_cache')
            ->where('year', $year)
            ->select('period_type', DB::raw('COUNT(*) as total'))
            ->groupBy('period_type')
            ->pluck('total', 'period_type')
            ->toArray();

        $summary = [
            'tramos_procesados'   => $totalTramosProcesados,
            'tramos_omitidos'     => $totalTramosOmitidos,
            'errores_tramo'       => $totalErroresTramo,
            'inserts_realizados'  => $totalInsertsRealizados,
            'filas_en_db_final'   => $finalCount,
            'desglose_por_tipo'   => $finalByType,
        ];

        Log::info('[BACKFILL_CERT_FINAL]', $summary);

        $this->newLine();
        $this->info("=== RESUMEN FINAL ===");
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Tramos procesados',    $totalTramosProcesados],
                ['Tramos omitidos',      $totalTramosOmitidos],
                ['Errores por tramo',    $totalErroresTramo],
                ['Inserts realizados',   $totalInsertsRealizados],
                ['Filas en BD (final)',  $finalCount],
            ]
        );

        $this->info("Desglose por tipo en BD:");
        foreach ($finalByType as $type => $total) {
            $this->line("  • {$type}: {$total} filas");
        }

        if ($finalCount === 0 && $totalInsertsRealizados > 0) {
            Log::critical('[BACKFILL_CERT_GHOST_INSERTS]', [
                'msg'      => 'Se reportaron inserts pero la BD está vacía. Posible rollback silencioso o conexión distinta.',
                'inserts'  => $totalInsertsRealizados,
                'in_db'    => $finalCount,
            ]);
            $this->error('🚨 ALERTA: Se hicieron inserts pero la BD quedó en 0. Revisa logs.');
        }

        $this->info("=== ¡PROCESO COMPLETADO! HISTORIAL DE CERTIFICACIONES AL 100% ===");
        return 0;
    }
}
