<?php

namespace App\Console\Commands\Normalize;

use App\Services\Indicators\IndicatorScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tabla job_offer_alignment (oferta → carrera), que usa el indicador de demanda geográfica:
 * una oferta se alinea con una carrera si tiene una asociación VÁLIDA (IndicatorScope: sin
 * asociaciones excluidas por el detector ni de entidades inactivas) con una tecnología, un
 * lenguaje o una metodología de los cursos de la carrera. La exclusión por título se aplica al
 * leerla (el indicador filtra job_offers), así que no hace falta reconstruirla al marcar ofertas.
 *
 * Se procesa por tramos de job_offer_id: primero los pares (oferta, entidad) válidos del tramo
 * (DISTINCT: MySQL los materializa, el filtro se evalúa una vez por par) y luego el cruce con
 * cursos y carreras. Evaluar el filtro después del cruce (5,3 millones de filas) tardaba >10 min.
 */
class BuildJobOfferAlignment extends Command
{
    protected $signature = 'jobs:build-alignment {--truncate} {--chunk=25000 : ofertas (rango de ids) por tramo}';

    protected $description = 'Construye la tabla job_offer_alignment basada en tecnologías, lenguajes y metodologías';

    /** fuente => [tabla pivote, alias, columna de la entidad, tabla curso-entidad, alias] */
    private const SOURCES = [
        'technology' => ['technology_job', 'tj', 'technology_id', 'course_technology', 'ct'],
        'language' => ['language_job', 'lj', 'language_id', 'course_language', 'cl'],
        'methodology' => ['methodology_job', 'mj', 'methodology_id', 'course_methodology', 'cm'],
    ];

    public function handle()
    {
        if ($this->option('truncate')) {
            DB::table('job_offer_alignment')->truncate();
            $this->info('Tabla limpiada');
        }

        $chunk = max(1000, (int) $this->option('chunk'));
        $maxId = (int) DB::table('job_offers')->max('id');

        foreach (self::SOURCES as $source => [$pivot, $a, $col, $courseTable, $ca]) {
            $this->info("Procesando {$source}...");
            $start = microtime(true);
            $inserted = 0;

            for ($from = 0; $from <= $maxId; $from += $chunk) {
                $inserted += DB::affectingStatement("
                    INSERT IGNORE INTO job_offer_alignment
                    (job_offer_id, career_id, source, created_at, updated_at)

                    SELECT DISTINCT v.job_offer_id, cc.career_id, '{$source}', NOW(), NOW()
                    FROM (
                        SELECT DISTINCT {$a}.job_offer_id, {$a}.{$col}
                        FROM {$pivot} {$a}
                        WHERE {$a}.job_offer_id >= ? AND {$a}.job_offer_id < ?"
                        . IndicatorScope::associationsSql($pivot, $a) . "
                    ) v
                    JOIN {$courseTable} {$ca} ON {$ca}.{$col} = v.{$col}
                    JOIN career_course cc ON cc.course_id = {$ca}.course_id
                ", [$from, $from + $chunk]);
            }

            $this->line(sprintf('  %d filas en %.1f s', $inserted, microtime(true) - $start));
        }

        $total = DB::table('job_offer_alignment')->count();

        $this->info("Alineaciones totales: {$total}");
    }
}
