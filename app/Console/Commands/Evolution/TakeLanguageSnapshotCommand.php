<?php

namespace App\Console\Commands\Evolution;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Prueba;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TakeLanguageSnapshotCommand extends Command
{
    /**
     * Firma del comando modificado para lenguajes.
     */
    protected $signature = 'languages:take-snapshot {--filter=auto : Opciones: auto, weekly, biweekly, monthly}';

    protected $description = 'Calcula de forma automática o manual las fotos históricas necesarias para Lenguajes (Semanal, Quincenal, Mensual).';

    public function handle()
    {
        $filterOption = $this->option('filter');
        $now = Carbon::now();
        $year = $now->year;

        // 1. Determinar de forma automática qué tipos de fotos corresponden a la fecha de HOY
        $periodsToProcess = [];

        if ($filterOption === 'auto') {
            // A. ¿Es Domingo? Tomamos la foto semanal
            if ($now->isSunday()) {
                $periodsToProcess['weekly'] = "Semana " . $now->weekOfYear . " - " . $year;
            }

            // B. ¿Es día 15 o último día del mes? Tomamos la foto quincenal
            if ($now->day === 15) {
                $periodsToProcess['biweekly'] = "1ra Quincena " . $now->translatedFormat('F') . " - " . $year;
            } elseif ($now->copy()->endOfMonth()->isSameDay($now)) {
                $periodsToProcess['biweekly'] = "2da Quincena " . $now->translatedFormat('F') . " - " . $year;
                
                // C. Si es el último día del mes, también se captura la foto mensual de golpe
                $periodsToProcess['monthly'] = $now->translatedFormat('F') . " - " . $year;
            }

            // Si hoy no cae en ninguna de las condiciones de corte, el comando avisa y termina de forma segura
            if (empty($periodsToProcess)) {
                $this->info("Hoy (" . $now->format('Y-m-d') . ") no es un día de corte establecido. No se requiere tomar fotos históricas para lenguajes.");
                return 0;
            }
        } else {
            // Si decides forzarlo manualmente pasándole el parámetro en la terminal
            if (!in_array($filterOption, ['weekly', 'biweekly', 'monthly'])) {
                $this->error("Filtro manual no válido. Usa: auto, weekly, biweekly o monthly.");
                return 1;
            }

            if ($filterOption === 'weekly') {
                $periodsToProcess['weekly'] = "Semana " . $now->weekOfYear . " - " . $year;
            } elseif ($filterOption === 'monthly') {
                $periodsToProcess['monthly'] = $now->translatedFormat('F') . " - " . $year;
            } else {
                $periodsToProcess['biweekly'] = "Quincena " . ($now->day <= 15 ? '1' : '2') . " " . $now->translatedFormat('F') . " - " . $year;
            }
        }

        try {
            // 2. Definir el rango del semestre (Lógica idéntica a tu RankingLenguajesController)
            $period = $now->month <= 6 ? 's1' : 's2';
            $semester = $period === 's1' ? 1 : 2;
            $range = $this->getPeriodRange($period, $year);

            $startDate = $now->format('Y-m-d');
            $endDate   = $now->format('Y-m-d'); 

            // 3. Cargar Ponderaciones Activas para Lenguajes
            try {
                $weights = Prueba::getActive('languages'); // 🔥 Cambiado a 'languages'
            } catch (\Throwable $e) {
                $weights = null;
            }

            $laborWeight = (float) ($weights?->labor_weight ?? 0.7);
            $trendWeight = (float) ($weights?->trend_weight ?? 0.3);

            // 4. Calcular Sub-queries de volúmenes acumulados (Fiel a tu lógica de Lenguajes)
            $laborSub = DB::table('language_job as lj') // 🔥 Cambiado a 'language_job'
                ->join('job_offers as j', 'j.id', '=', 'lj.job_offer_id')
                ->whereBetween('j.published_at', [$range['start'], $startDate]) // Acumulado del semestre hasta hoy
                ->groupBy('lj.market_entity_id')
                ->select('lj.market_entity_id', DB::raw('COUNT(DISTINCT lj.job_offer_id) as offers'));

            $maxLabor = max(DB::query()->fromSub($laborSub, 'x')->max('offers'), 1);

            $trendSub = DB::table('entity_trends as et')
                ->join('market_entities as me', function ($j) {
                    $j->on('me.id', '=', 'et.market_entity_id')
                      ->where('me.entity_type', 'language'); // 🔥 Forzar tipo language
                })
                ->where('et.year', $year)
                ->whereIn('et.quarter', $semester === 1 ? [1, 2] : [3, 4])
                ->groupBy('me.id')
                ->select('me.id as language_id', DB::raw('COUNT(DISTINCT et.id) as report_mentions'));

            $maxTrendReports = max(DB::query()->fromSub($trendSub, 't')->max('report_mentions'), 1);

            // 5. Procesar y calcular scores para el ranking general de lenguajes de hoy
            $languages = DB::table('market_entities as me')
                ->leftJoinSub($laborSub, 'labor', 'labor.market_entity_id', '=', 'me.id')
                ->leftJoinSub($trendSub, 'trends', 'trends.language_id', '=', 'me.id')
                ->where('me.entity_type', 'language') // 🔥 Filtrar por 'language'
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

            if ($languages->isEmpty()) {
                $this->warn("No se encontraron lenguajes para procesar.");
                return 0;
            }

            // 6. Guardar en la Base de Datos en la tabla de caché de lenguajes
            DB::transaction(function () use ($languages, $periodsToProcess, $year, $startDate, $endDate) {
                foreach ($periodsToProcess as $type => $label) {
                    $position = 1;

                    foreach ($languages as $lang) {
                        // ⚠️ Asegúrate de tener creada la tabla 'language_evolution_cache'
                        DB::table('language_evolution_cache')->updateOrInsert(
                            [
                                'market_entity_id' => $lang->market_entity_id,
                                'start_date'       => $startDate,
                                'period_type'      => $type, 
                            ],
                            [
                                'year'             => $year,
                                'end_date'         => $endDate,
                                'period_label'     => $label,
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
                    $this->info(" -> Foto histórica de Lenguajes [{$type}] guardada con éxito bajo la etiqueta: '{$label}'.");
                }
            });

            return 0;

        } catch (\Throwable $e) {
            Log::error('[LANGUAGE_SNAPSHOT_COMMAND_ERROR]', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine()
            ]);

            $this->error("Ocurrió un error al procesar el snapshot de lenguajes: " . $e->getMessage());
            return 1;
        }
    }

    private function getPeriodRange(string $period, int $year): array
    {
        if ($period === 's1') {
            return [
                'start' => "$year-01-01",
                'end'   => "$year-06-30",
            ];
        }

        return [
            'start' => "$year-07-01",
            'end'   => "$year-12-31",
        ];
    }
}