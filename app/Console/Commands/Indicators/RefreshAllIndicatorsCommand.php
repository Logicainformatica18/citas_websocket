<?php

namespace App\Console\Commands\Indicators;

use App\Services\Indicators\IndicatorRefresher;
use Illuminate\Console\Command;

/**
 * Recalcula los precálculos de los indicadores de análisis (todos menos los rankings de Lenguajes,
 * Tecnologías y Certificaciones). Se ejecuta al final de jobs:run-all y a las 07:00 (routes/console.php).
 */
class RefreshAllIndicatorsCommand extends Command
{
    protected $signature = 'indicators:refresh-all
                            {--only=* : solo estos indicadores (ver IndicatorRefresher::INDICATORS)}
                            {--queue : encolar como Jobs encadenados (cola por defecto) en vez de ejecutar aquí}';

    protected $description = 'Recalcula las tablas resumen de los indicadores (Seniority, Geo, Empresas, Modalidad, PE, CCTC…)';

    public function handle(): int
    {
        $only = $this->option('only');
        if ($this->option('queue')) {
            IndicatorRefresher::queue($only);
            $this->info('Encolado (cola por defecto, un Job por paso).');

            return self::SUCCESS;
        }

        $steps = IndicatorRefresher::steps($only);
        $t0 = microtime(true);
        $slowest = [0, ''];
        foreach ($steps as $i => $step) {
            $t = microtime(true);
            $summary = IndicatorRefresher::run($step);
            $s = microtime(true) - $t;
            if ($s > $slowest[0]) {
                $slowest = [$s, IndicatorRefresher::label($step)];
            }
            $this->line(sprintf('[%d/%d] %-34s %6.1f s  pico %4d MB  %s', $i + 1, count($steps),
                IndicatorRefresher::label($step), $s, memory_get_peak_usage(true) / 1048576, mb_substr($summary, 0, 120)));
        }
        $this->info(sprintf('Listo: %d pasos en %.1f s; pico de memoria %d MB; paso más largo %.1f s (%s).',
            count($steps), microtime(true) - $t0, memory_get_peak_usage(true) / 1048576, $slowest[0], $slowest[1]));

        return self::SUCCESS;
    }
}
