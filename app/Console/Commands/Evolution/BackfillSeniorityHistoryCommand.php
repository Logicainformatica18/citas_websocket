<?php

namespace App\Console\Commands\Evolution;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BackfillSeniorityHistoryCommand extends Command
{
    /**
     * El nombre y firma del comando en la terminal.
     */
    protected $signature = 'seniority:backfill-all {year?}';

    /**
     * Descripción del comando.
     */
    protected $description = 'Reconstruye el historial acumulativo de seniority utilizando el SQL optimizado para MariaDB.';

    public function handle()
    {
        $year = $this->argument('year') ? (int) $this->argument('year') : (int) date('Y');
        $this->info("=== INICIANDO RECONSTRUCCIÓN HISTÓRICA ACUMULATIVA - AÑO {$year} ===");

        // Limpieza inicial para evitar duplicados residuales en el año
        DB::table('seniority_evolution_cache')->where('year', $year)->delete();

        // Tipos de recorte a procesar secuencialmente
        $tiposRecorte = [
            'SEMANAL'   => 'weekly',
            'QUINCENAL' => 'biweekly',
            'MENSUAL'   => 'monthly'
        ];

        $startYear = "{$year}-01-01 00:00:00";
        $endYear   = ($year === (int) date('Y')) ? Carbon::now()->endOfDay()->toDateTimeString() : "{$year}-12-31 23:59:59";

        foreach ($tiposRecorte as $sqlTipo => $cacheTipo) {
            $this->comment("Procesando bloque de corte acumulativo: {$sqlTipo}...");

            // Tu consulta exacta adaptada 100% para la compatibilidad de MariaDB
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
                            WHEN '{$sqlTipo}' = 'QUINCENAL' THEN CONCAT('Quincena ', IF(dia <= 15, 1, 2), ' - Mes ', mes)
                            ELSE CONCAT('Mes ', mes, ' - ', anio)
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

            // Ejecución de la consulta directa en MariaDB
            $rawResults = DB::select($query);

            if (empty($rawResults)) {
                $this->warn(" -> Sin datos encontrados para el corte {$sqlTipo}.");
                continue;
            }

            // Guardamos los datos utilizando transacciones masivas eficientes
            DB::transaction(function () use ($rawResults, $cacheTipo, $year) {
                foreach ($rawResults as $row) {
                    $totalJobs = (int) $row->muestra_absoluta_acumulada;

                    if ($totalJobs === 0) {
                        continue;
                    }

                    $juniorCount = (int) $row->junior_acumulado;
                    $midCount    = (int) $row->mid_acumulado;
                    $seniorCount = (int) $row->senior_acumulado;

                    // Cálculo matemático exacto de porcentajes evolutivos
                    $juniorPct = round(($juniorCount / $totalJobs) * 100, 2);
                    $midPct    = round(($midCount / $totalJobs) * 100, 2);
                    $seniorPct = round(($seniorCount / $totalJobs) * 100, 2);

                    // Ajuste de desviación centesimal para clavar el 100.00%
                    $sumaPcts = $juniorPct + $midPct + $seniorPct;
                    if ($sumaPcts !== 100.0 && $sumaPcts > 0) {
                        $diferencia = round(100.0 - $sumaPcts, 2);
                        $seniorPct = round($seniorPct + $diferencia, 2);
                    }

                    // Rangos de fecha límites del histórico detectados en el bloque
                    $startDate = Carbon::parse($row->min_date)->format('Y-m-d');
                    $endDate   = Carbon::parse($row->max_date)->format('Y-m-d');

                    // Guardado directo en la tabla de caché (Segmentación Global)
                    DB::table('seniority_evolution_cache')->updateOrInsert(
                        [
                            'career_id'    => null,
                            'period_type'  => $cacheTipo,
                            'period_label' => $row->etiqueta_periodo,
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

            $this->info(" -> Bloque [{$cacheTipo}] guardado con éxito.");
        }

        $this->info("=== ¡PROCESO COMPLETADO AL 100% CON MÉTODO ACUMULATIVO REAL EN MARIADB! ===");
        return 0;
    }
}