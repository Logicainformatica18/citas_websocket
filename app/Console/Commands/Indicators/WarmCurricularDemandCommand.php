<?php

namespace App\Console\Commands\Indicators;

use App\Services\Indicators\CurricularDemand;
use Illuminate\Console\Command;

/**
 * Precalcula la demanda de los indicadores curriculares (Alineamiento PE y CCTC) con el criterio
 * común de los indicadores, para que sus páginas no la calculen en la petición. Va en jobs:run-all
 * después de job-offers:apply-title-exclusions; también se puede ejecutar a mano tras
 * entities:exclude-associations.
 */
class WarmCurricularDemandCommand extends Command
{
    protected $signature = 'indicators:curricular-demand {--year=* : años (por defecto, el actual)}';

    protected $description = 'Precalcula la demanda (ofertas válidas) de Alineamiento PE y CCTC';

    public function handle(): int
    {
        $years = array_map('intval', $this->option('year') ?: [now()->year]);

        foreach ($years as $year) {
            $t = microtime(true);
            $d = CurricularDemand::warm($year);
            $this->info(sprintf('Demanda curricular %d: %d carreras, %d cursos con ofertas, en %.1f s (pico %.0f MB)',
                $year, count($d['career_year']), count($d['course_jobs']), microtime(true) - $t, memory_get_peak_usage(true) / 1048576));
        }

        return self::SUCCESS;
    }
}
