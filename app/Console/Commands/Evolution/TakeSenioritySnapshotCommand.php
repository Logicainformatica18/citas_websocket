<?php

namespace App\Console\Commands\Evolution;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TakeSenioritySnapshotCommand extends Command
{
    /**
     * El nombre y firma del comando en la terminal.
     */
    protected $signature = 'seniority:take-snapshot {--filter=auto : Opciones: auto, weekly, biweekly, monthly}';

    /**
     * Descripción del comando.
     */
    protected $description = 'Calcula y actualiza diariamente de forma acumulativa la caché de evolución de seniority.';

    public function handle()
    {
        $filterOption = $this->option('filter');
        $now = Carbon::now();
        $year = $now->year;

        // 1. Mapeo de periodos que se deben actualizar hoy
        $periodsToProcess = [];

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        $monthName = $meses[$now->month];

        if ($filterOption === 'auto') {
            // Cada día se recalcula la semana en curso para que el número crezca fluidamente en el dashboard
            $periodsToProcess['weekly'] = 'SEMANAL';

            // Si es día 15 o fin de mes, aseguramos la persistencia e inclusive procesamos quincenas/meses
            if ($now->day >= 1 && $now->day <= 15) {
                $periodsToProcess['biweekly'] = 'QUINCENAL';
            } else {
                $periodsToProcess['biweekly'] = 'QUINCENAL';
            }

            $periodsToProcess['monthly'] = 'MENSUAL';
        } else {
            // Activación por banderas manuales (--filter=weekly, etc.)
            if (!in_array($filterOption, ['weekly', 'biweekly', 'monthly'])) {
                $this->error("Filtro manual no válido. Usa: auto, weekly, biweekly o monthly.");
                return 1;
            }

            $mapping = [
                'weekly'   => 'SEMANAL',
                'biweekly' => 'QUINCENAL',
                'monthly'  => 'MENSUAL'
            ];
            $periodsToProcess[$filterOption] = $mapping[$filterOption];
        }

        try {
            // Límites estrictos del año actual en curso para mantener la ventana acumulativa fija
            $startYear = "{$year}-01-01 00:00:00";
            $endYear   = $now->endOfDay()->toDateTimeString(); // Hasta el último segundo de hoy

            foreach ($periodsToProcess as $cacheTipo => $sqlTipo) {
                $this->comment("Calculando snapshot dinámico [{$cacheTipo}] acumulado hasta hoy...");

                // Tu consulta SQL adaptada de forma nativa para MariaDB
                $query = "
                    WITH datos_filtrados AS (
                        SELECT 
                            id,
                            seniority, 
                            published_at, 
                            YEAR(published_at) AS anio,
                            MONTH(published_at) AS mes,
                            WEEK(published_at, 1) AS num_semana, 
                            DAY(published_at) AS dia
                        FROM job_offers
                        WHERE published_at BETWEEN '{$startYear}' AND '{$endYear}' 
                          AND seniority IN ('junior', 'mid', 'senior')
                    ),
                    agrupacion_temporal AS (
                        SELECT 
                            seniority,
                            published_at,
                            CASE 
                                WHEN '{$sqlTipo}' = 'SEMANAL' THEN CONCAT(anio, '-', LPAD(num_semana, 2, '0'))
                                WHEN '{$sqlTipo}' = 'QUINCENAL' THEN CONCAT(anio, '-', LPAD(mes, 2, '0'), '-Q', IF(dia <= 15, 1, 2))
                                ELSE CONCAT(anio, '-', LPAD(mes, 2, '0'))
                            END AS periodo_id,
                            CASE 
                                WHEN '{$sqlTipo}' = 'SEMANAL' THEN CONCAT('Semana ', num_semana, ' - ', anio)
                                WHEN '{$sqlTipo}' = 'QUINCENAL' THEN CONCAT(IF(dia <= 15, '1ra', '2da'), ' Quincena ', 
                                    CASE mes 
                                        WHEN 1 THEN 'Enero' WHEN 2 THEN 'Febrero' WHEN 3 THEN 'Marzo' 
                                        WHEN 4 THEN 'Abril' WHEN 5 THEN 'Mayo' WHEN 6 THEN 'Junio' 
                                        WHEN 7 THEN 'Julio' WHEN 8 THEN 'Agosto' WHEN 9 THEN 'Septiembre' 
                                        WHEN 10 THEN 'Octubre' WHEN 11 THEN 'Noviembre' WHEN 12 THEN 'Diciembre' 
                                    END, ' - ', anio)
                                ELSE CONCAT(
                                    CASE mes 
                                        WHEN 1 THEN 'Enero' WHEN 2 THEN 'Febrero' WHEN 3 THEN 'Marzo' 
                                        WHEN 4 THEN 'Abril' WHEN 5 THEN 'Mayo' WHEN 6 THEN 'Junio' 
                                        WHEN 7 THEN 'Julio' WHEN 8 THEN 'Agosto' WHEN 9 THEN 'Septiembre' 
                                        WHEN 10 THEN 'Octubre' WHEN 11 THEN 'Noviembre' WHEN 12 THEN 'Diciembre' 
                                    END, ' - ', anio)
                            END AS etiqueta_periodo
                        FROM datos_filtrados
                    ),
                    conteos_por_periodo AS (
                        SELECT 
                            periodo_id,
                            etiqueta_periodo,
                            SUM(CASE WHEN seniority = 'junior' THEN 1 ELSE 0 END) AS vacantes_junior,
                            SUM(CASE WHEN seniority = 'mid' THEN 1 ELSE 0 END) AS vacantes_mid,
                            SUM(CASE WHEN seniority = 'senior' THEN 1 ELSE 0 END) AS vacantes_senior,
                            COUNT(*) AS total_del_periodo_aislado,
                            MIN(published_at) AS min_date,
                            MAX(published_at) AS max_date
                        FROM agrupacion_temporal
                        GROUP BY periodo_id, etiqueta_periodo
                    ),
                    acumulados_historicos AS (
                        SELECT 
                            periodo_id,
                            etiqueta_periodo,
                            SUM(vacantes_junior) OVER (ORDER BY periodo_id ASC) AS junior_acumulado,
                            SUM(vacantes_mid) OVER (ORDER BY periodo_id ASC) AS mid_acumulado,
                            SUM(vacantes_senior) OVER (ORDER BY periodo_id ASC) AS senior_acumulado,
                            SUM(total_del_periodo_aislado) OVER (ORDER BY periodo_id ASC) AS muestra_absoluta_acumulada,
                            min_date,
                            max_date
                        FROM conteos_por_periodo
                    )
                    SELECT * FROM acumulados_historicos ORDER BY periodo_id ASC
                ";

                $rawResults = DB::select($query);

                if (empty($rawResults)) {
                    continue;
                }

                // Guardado atómico en la tabla intermedia de caché
                DB::transaction(function () use ($rawResults, $cacheTipo, $year) {
                    foreach ($rawResults as $row) {
                        $totalJobs = (int) $row->muestra_absoluta_acumulada;

                        if ($totalJobs === 0) {
                            continue;
                        }

                        $juniorCount = (int) $row->junior_acumulado;
                        $midCount    = (int) $row->mid_acumulado;
                        $seniorCount = (int) $row->senior_acumulado;

                        // Porcentajes de la muestra acumulada
                        $juniorPct = round(($juniorCount / $totalJobs) * 100, 2);
                        $midPct    = round(($midCount / $totalJobs) * 100, 2);
                        $seniorPct = round(($seniorCount / $totalJobs) * 100, 2);

                        // Ajuste centesimal del 100%
                        $sumaPcts = $juniorPct + $midPct + $seniorPct;
                        if ($sumaPcts !== 100.0 && $sumaPcts > 0) {
                            $diferencia = round(100.0 - $sumaPcts, 2);
                            $seniorPct = round($seniorPct + $diferencia, 2);
                        }

                        $startDate = Carbon::parse($row->min_date)->format('Y-m-d');
                        $endDate   = Carbon::parse($row->max_date)->format('Y-m-d');

                        // Actualiza el registro existente o inserta el nuevo periodo si cambió de semana/mes
                        DB::table('seniority_evolution_cache')->updateOrInsert(
                            [
                                'career_id'    => null, // Universo GLOBAL
                                'period_type'  => $cacheTipo,
                                'period_label' => $row->getiqueta_periodo ?? $row->etiqueta_periodo,
                                'year'         => $year,
                            ],
                            [
                                'career_slug'  => 'global',
                                'start_date'   => $startDate,
                                'end_date'     => $endDate,
                                'total_jobs'   => $totalJobs,
                                'junior_count' => $juniorCount,
                                'mid_count'    => $midCount,
                                'senior_count' => $seniorCount,
                                'junior_pct'   => $juniorPct,
                                'mid_pct'      => $midPct,
                                'senior_pct'   => $seniorPct,
                                'created_at'   => now(),
                                'updated_at'   => now(),
                            ]
                        );
                    }
                });

                $this->info(" -> Cache [{$cacheTipo}] actualizada correctamente.");
            }

            return 0;

        } catch (\Throwable $e) {
            Log::error('[SENIORITY_SNAPSHOT_COMMAND_ERROR]', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine()
            ]);

            $this->error("Error al procesar el snapshot diario: " . $e->getMessage());
            return 1;
        }
    }
}