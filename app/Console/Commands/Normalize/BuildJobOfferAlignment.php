<?php

namespace App\Console\Commands\Normalize;

use App\Services\Indicators\AlignmentBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tabla job_offer_alignment (oferta → carrera), base del ranking de ciudades de Demanda
 * geográfica: una fila por (oferta, carrera, fuente) si la oferta tiene alguna tecnología,
 * lenguaje o metodología de los cursos de la carrera, con `mentions` = cuántas. Asociaciones
 * completas (sin el filtro del detector ni el de entidades inactivas); la exclusión por título se
 * aplica al leer. Ver AlignmentBuilder (por tramos de job_offer_id; cada tramo actualiza solo lo
 * que cambió, así la tabla nunca queda vacía).
 */
class BuildJobOfferAlignment extends Command
{
    protected $signature = 'jobs:build-alignment {--truncate : vaciar la tabla antes (no hace falta: cada tramo actualiza sus filas)}
                            {--chunk=25000 : ofertas (rango de ids) por tramo}';

    protected $description = 'Construye la tabla job_offer_alignment basada en tecnologías, lenguajes y metodologías';

    public function handle()
    {
        if ($this->option('truncate')) {
            DB::table('job_offer_alignment')->truncate();
            $this->info('Tabla limpiada');
        }

        $t = microtime(true);
        $chunk = max(1000, (int) $this->option('chunk'));
        foreach (AlignmentBuilder::chunks($chunk) as [$from, $to]) {
            $r = AlignmentBuilder::buildRange($from, $to);
            $this->line(sprintf('  ofertas %d-%d: %d insertadas o actualizadas, %d borradas', $from, $to - 1, $r['upsert'], $r['borradas']));
        }

        $total = DB::table('job_offer_alignment')->count();
        $this->info(sprintf('Alineaciones totales: %d (%.1f s, pico %d MB)', $total, microtime(true) - $t, memory_get_peak_usage(true) / 1048576));
    }
}
