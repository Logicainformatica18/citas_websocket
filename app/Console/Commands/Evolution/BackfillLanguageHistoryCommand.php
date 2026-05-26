<?php

namespace App\Console\Commands\Evolution;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Prueba;
use Carbon\Carbon;

class BackfillLanguageHistoryCommand extends Command
{
    protected $signature = 'languages:backfill-all {year=2026}';
    protected $description = 'Reconstruye el historial de Lenguajes (Semanal, Quincenal y Mensual) guardando la foto exacta acumulada de cada fecha de corte.';

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

        Log::info('[BACKFILL_LANG_START]', $context);
        $this->info("=== INICIANDO RECONSTRUCCIÓN INTEGRAL (MÉTODO ACUMULADO) PARA EL AÑO {$year} - LENGUAJES ===");
        $this->table(['Campo', 'Valor'], collect($context)->map(fn($v, $k) => [$k, $v])->values());

        /* ==================================================
           🔍 VERIFICACIÓN PREVIA — DATOS FUENTE EN PROD
        ================================================== */
        $sanity = [
            'market_entities_language' => DB::table('market_entities')
                ->where('entity_type', 'language')
                ->count(),
            'language_job_total'       => DB::table('language_job')->count(),
            'job_offers_year'          => DB::table('job_offers')
                ->whereYear('published_at', $year)
                ->count(),
            'entity_trends_year'       => DB::table('entity_trends')
                ->where('year', $year)
                ->count(),
            'entity_trends_year_lang'  => DB::table('entity_trends as et')
                ->join('market_entities as me', 'me.id', '=', 'et.market_entity_id')
                ->where('me.entity_type', 'language')
                ->where('et.year', $year)
                ->count(),
            'cache_existing_rows'      => DB::table('language_evolution_cache')
                ->where('year', $year)
                ->count(),
        ];

        Log::info('[BACKFILL_LANG_SANITY_CHECK]', $sanity);
        $this->warn('🔎 Verificación de datos fuente:');
        $this->table(['Tabla / Filtro', 'Filas'], collect($sanity)->map(fn($v, $k) => [$k, $v])->values());

        if ($sanity['market_entities_language'] === 0) {
            Log::error('[BACKFILL_LANG_NO_ENTITIES]', ['msg' => 'No hay market_entities con entity_type=language']);
            $this->error('❌ NO HAY LENGUAJES EN market_entities. Abortando.');
            return 1;
        }

        if ($sanity['language_job_total'] === 0) {
            Log::warning('[BACKFILL_LANG_NO_JOBS]', ['msg' => 'Tabla language_job vacía']);
            $this->error('⚠️ ATENCIÓN: language_job está vacía. Los scores laborales serán 0.');
        }

        /* ==================================================
           1. Cargar Ponderaciones Activas
        ================================================== */
        try {
            $weights = Prueba::getActive('languages');
        } catch (\Throwable $e) {
            Log::error('[BACKFILL_LANG_WEIGHTS_ERROR]', ['error' => $e->getMessage()]);
            $weights = null;
        }

        $laborWeight = (float) ($weights?->labor_weight ?? 0.7);
        $trendWeight = (float) ($weights?->trend_weight ?? 0.3);

        Log::info('[BACKFILL_LANG_WEIGHTS]', [
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
            $this->comment("Procesando cortes acumulados para Lenguajes: {$monthName}");
            $this->comment("=============================================");

            Log::info('[BACKFILL_LANG_MONTH]', ['month' => $currentMonth, 'name' => $monthName]);

            $startOfMonth = Carbon::create($year, $currentMonth, 1)->startOfDay();
            $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

            $tramos = [
                ['type' => 'weekly',   'label' => "Semana 1 - {$monthName} {$year}",     'start_date' => Carbon::create($year, $currentMonth, 1)->startOfDay(),  'end_date' => Carbon::create($year, $currentMonth, 7)->endOfDay()],
                ['type' => 'weekly',   'label' => "Semana 2 - {$monthName} {$year}",     'start_date' => Carbon::create($year, $currentMonth, 8)->startOfDay(),  'end_date' => Carbon::create($year, $currentMonth, 14)->endOfDay()],
                ['type' => 'weekly',   'label' => "Semana 3 - {$monthName} {$year}",     'start_date' => Carbon::create($year, $currentMonth, 15)->startOfDay(), 'end_date' => Carbon::create($year, $currentMonth, 21)->endOfDay()],
                ['type' => 'weekly',   'label' => "Semana 4 - {$monthName} {$year}",     'start_date' => Carbon::create($year, $currentMonth, 22)->startOfDay(), 'end_date' => $endOfMonth->copy()],
                ['type' => 'biweekly', 'label' => "1ra Quincena {$monthName} - {$year}", 'start_date' => Carbon::create($year, $currentMonth, 1)->startOfDay(),  'end_date' => Carbon::create($year, $currentMonth, 15)->endOfDay()],
                ['type' => 'biweekly', 'label' => "2da Quincena {$monthName} - {$year}", 'start_date' => Carbon::create($year, $currentMonth, 16)->startOfDay(), 'end_date' => $endOfMonth->copy()],
                ['type' => 'monthly',  'label' => "{$monthName} - {$year}",              'start_date' => $startOfMonth->copy(),                                  'end_date' => $endOfMonth->copy()],
            ];

            foreach ($tramos as $tramo) {
                $startRange = $tramo['start_date'];
                $endRange   = $tramo['end_date'];

                if ($endRange->greaterThan(Carbon::now())) {
                    $totalTramosOmitidos++;
                    Log::info('[BACKFILL_LANG_TRAMO_SKIP_FUTURE]', [
                        'label' => $tramo['label'],
                        'end'   => $endRange->toDateString(),
                    ]);
                    continue;
                }

                $startString = $startRange->format('Y-m-d');
                $endString   = $endRange->format('Y-m-d');

                $semester = $currentMonth <= 6 ? 1 : 2;
                $semStart = $semester === 1 ? "$year-01-01" : "$year-07-01";
                $quarters = $semester === 1 ? [1, 2] : [3, 4];

                try {
                    /* ==================================================
                       1. Subquery Laboral
                    ================================================== */
                    $laborSub = DB::table('language_job as lj')
                        ->join('job_offers as j', 'j.id', '=', 'lj.job_offer_id')
                        ->whereBetween('j.published_at', [$semStart, $endString])
                        ->groupBy('lj.market_entity_id')
                        ->select('lj.market_entity_id', DB::raw('COUNT(DISTINCT lj.job_offer_id) as offers'));

                    $maxLabor = max(DB::query()->fromSub($laborSub, 'x')->max('offers'), 1);

                    /* ==================================================
                       2. Subquery Tendencias
                    ================================================== */
                    $trendSub = DB::table('entity_trends as et')
                        ->join('market_entities as me', function ($j) {
                            $j->on('me.id', '=', 'et.market_entity_id')
                              ->where('me.entity_type', 'language');
                        })
                        ->where('et.year', $year)
                        ->whereIn('et.quarter', $quarters)
                        ->groupBy('me.id')
                        ->select('me.id as language_id', DB::raw('COUNT(DISTINCT et.id) as report_mentions'));

                    $maxTrendReports = max(DB::query()->fromSub($trendSub, 't')->max('report_mentions'), 1);

                    /* ==================================================
                       3. Query Principal
                    ================================================== */
                    $languages = DB::table('market_entities as me')
                        ->leftJoinSub($laborSub, 'labor', 'labor.market_entity_id', '=', 'me.id')
                        ->leftJoinSub($trendSub, 'trends', 'trends.language_id', '=', 'me.id')
                        ->where('me.entity_type', 'language')
                        ->select(
                            'me.id as market_entity_id',
                            'me.name',
                            DB::raw('COALESCE(labor.offers, 0) as total_jobs'),
                            DB::raw('COALESCE(trends.report_mentions, 0) as total_trends'),
                            DB::raw("ROUND(((LOG(COALESCE(labor.offers,0)+1) / LOG({$maxLabor}+1)) * 100), 1) as labor_score"),
                            DB::raw("ROUND(((LOG(COALESCE(trends.report_mentions,0)+1) / LOG({$maxTrendReports}+1)) * 100), 1) as trend_score"),
                            DB::raw("ROUND(
                                (
                                    ((LOG(COALESCE(labor.offers,0)+1) / LOG({$maxLabor}+1)) * 100 * {$laborWeight})
                                    +
                                    ((LOG(COALESCE(trends.report_mentions,0)+1) / LOG({$maxTrendReports}+1)) * 100 * {$trendWeight})
                                ), 1) as final_score")
                        )
                        ->orderByDesc('final_score')
                        ->get();

                    /* ==================================================
                       🔍 LOG DIAGNÓSTICO POR TRAMO
                    ================================================== */
                    $diag = [
                        'label'              => $tramo['label'],
                        'type'               => $tramo['type'],
                        'range'              => "{$startString} → {$endString}",
                        'sem_start'          => $semStart,
                        'quarters'           => $quarters,
                        'max_labor'          => $maxLabor,
                        'max_trend_reports'  => $maxTrendReports,
                        'rows_returned'      => $languages->count(),
                        'rows_with_jobs'     => $languages->where('total_jobs', '>', 0)->count(),
                        'rows_with_trends'   => $languages->where('total_trends', '>', 0)->count(),
                        'top1_name'          => $languages->first()->name ?? null,
                        'top1_final_score'   => $languages->first()->final_score ?? null,
                        'top1_jobs'          => $languages->first()->total_jobs ?? null,
                    ];
                    Log::info('[BACKFILL_LANG_TRAMO_DIAG]', $diag);

                    if ($languages->isEmpty()) {
                        Log::warning('[BACKFILL_LANG_TRAMO_EMPTY]', $diag);
                        $this->warn(" ⚠️ Sin datos: [{$tramo['type']}] {$tramo['label']}");
                        $totalTramosOmitidos++;
                        continue;
                    }

                    /* ==================================================
                       4. Guardado transaccional con verificación
                    ================================================== */
                    $insertedCount = 0;

                    DB::transaction(function () use ($languages, $tramo, $year, $startString, $endString, &$insertedCount) {

                        $deleted = DB::table('language_evolution_cache')
                            ->where('year', $year)
                            ->where('period_type', $tramo['type'])
                            ->where('period_label', $tramo['label'])
                            ->delete();

                        Log::info('[BACKFILL_LANG_TRAMO_CLEAN]', [
                            'label'   => $tramo['label'],
                            'deleted' => $deleted,
                        ]);

                        $position = 1;
                        $batch = [];

                        foreach ($languages as $lang) {
                            $batch[] = [
                                'market_entity_id' => $lang->market_entity_id,
                                'start_date'       => $startString,
                                'end_date'         => $endString,
                                'period_type'      => $tramo['type'],
                                'year'             => $year,
                                'period_label'     => $tramo['label'],
                                'jobs'             => (int) $lang->total_jobs,
                                'trend_reports'    => (int) $lang->total_trends,
                                'labor_score'      => (float) $lang->labor_score,
                                'trend_score'      => (float) $lang->trend_score,
                                'final_score'      => (float) $lang->final_score,
                                'ranking_position' => $position,
                                'updated_at'       => now(),
                                'created_at'       => now(),
                            ];
                            $position++;
                        }

                        // Insert por lotes para que sea atómico y rápido
                        $insertedCount = count($batch);
                        DB::table('language_evolution_cache')->insert($batch);
                    });

                    /* ==================================================
                       🔍 VERIFICACIÓN POST-INSERT (CONFIRMA QUE PERSISTIÓ)
                    ================================================== */
                    $confirmed = DB::table('language_evolution_cache')
                        ->where('year', $year)
                        ->where('period_type', $tramo['type'])
                        ->where('period_label', $tramo['label'])
                        ->count();

                    Log::info('[BACKFILL_LANG_TRAMO_OK]', [
                        'label'         => $tramo['label'],
                        'inserted'      => $insertedCount,
                        'confirmed_db'  => $confirmed,
                        'match'         => $confirmed === $insertedCount,
                    ]);

                    if ($confirmed !== $insertedCount) {
                        Log::error('[BACKFILL_LANG_TRAMO_MISMATCH]', [
                            'label'        => $tramo['label'],
                            'inserted'     => $insertedCount,
                            'confirmed_db' => $confirmed,
                        ]);
                        $this->error(" ❌ MISMATCH en {$tramo['label']}: insertados={$insertedCount} vs confirmados={$confirmed}");
                    }

                    $totalInsertsRealizados += $insertedCount;
                    $totalTramosProcesados++;

                    $this->info(" -> Guardada foto [{$tramo['type']}] {$startString} al {$endString}: '{$tramo['label']}' ({$insertedCount} filas, DB confirmado: {$confirmed})");

                } catch (\Throwable $e) {
                    $totalErroresTramo++;
                    Log::error('[BACKFILL_LANG_TRAMO_ERROR]', [
                        'label'   => $tramo['label'],
                        'type'    => $tramo['type'],
                        'range'   => "{$startString} → {$endString}",
                        'error'   => $e->getMessage(),
                        'file'    => $e->getFile(),
                        'line'    => $e->getLine(),
                        'trace'   => $e->getTraceAsString(),
                    ]);
                    $this->error(" 💥 ERROR en {$tramo['label']}: {$e->getMessage()}");
                }
            }

            $currentMonth++;
        }

        /* ==================================================
           🔍 RESUMEN FINAL — DEBE COINCIDIR CON LA BD
        ================================================== */
        $finalCount = DB::table('language_evolution_cache')
            ->where('year', $year)
            ->count();

        $finalByType = DB::table('language_evolution_cache')
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

        Log::info('[BACKFILL_LANG_FINAL]', $summary);

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
            Log::critical('[BACKFILL_LANG_GHOST_INSERTS]', [
                'msg'      => 'Se reportaron inserts pero la BD está vacía. Posible rollback silencioso o conexión distinta.',
                'inserts'  => $totalInsertsRealizados,
                'in_db'    => $finalCount,
            ]);
            $this->error('🚨 ALERTA: Se hicieron inserts pero la BD quedó en 0. Revisa logs.');
        }

        $this->info("=== ¡PROCESO COMPLETADO! ===");
        return 0;
    }
}
