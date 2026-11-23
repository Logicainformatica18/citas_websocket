<?php

namespace App\Console\Commands\Ranking;

use App\Services\Ranking\EntityRankingService;
use Illuminate\Console\Command;

/**
 * Base de ranking:refresh-languages, ranking:refresh-technologies, …
 * Cada subclase fija $rankingType (clave de config/entity_rankings.php) y su firma.
 */
abstract class RefreshEntityRankingCommand extends Command
{
    /** Clave del indicador en config/entity_rankings.php */
    protected string $rankingType;

    public function handle(): int
    {
        $service = EntityRankingService::for($this->rankingType);
        $label = $service->label();

        $year = $this->option('year');
        $period = $this->option('period');

        if ($year !== null && ! preg_match('/^\d{4}$/', $year)) {
            $this->error('--year debe ser un año de 4 dígitos.');

            return self::INVALID;
        }

        if ($period !== null && ! in_array($period, ['s1', 's2'], true)) {
            $this->error('--period debe ser s1 o s2.');

            return self::INVALID;
        }

        // --period sin --year: el semestre indicado del año actual.
        if ($period !== null && $year === null) {
            $year = (int) now()->year;
        }

        $result = $service->refreshWithLock(
            $year !== null ? (int) $year : null,
            $period,
            function (array $r) use ($label) {
                if (($r['step'] ?? null) === 'windows') {
                    $r['ok']
                        ? $this->line(sprintf('  ✅ Evolución: %d ventanas (semanas, quincenas y meses), %d filas (%.1f s)', $r['windows'] ?? 0, $r['rows'], $r['duration_ms'] / 1000))
                        : $this->error("  ❌ Evolución: {$r['error']}");

                    return;
                }

                if ($r['ok']) {
                    $this->line(sprintf(
                        '  ✅ %d-%s: %d %s, %d vacantes, %d reportes (%.1f s)',
                        $r['year'], $r['period'], $r['rows'], $label, $r['vacantes'], $r['reportes'], $r['duration_ms'] / 1000
                    ));
                } else {
                    $this->error("  ❌ {$r['year']}-{$r['period']}: {$r['error']}");
                }
            }
        );

        if ($result === null) {
            $this->warn("Ya hay un recálculo del ranking de {$label} en curso. No se hizo nada.");

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info(sprintf('Ranking de %s recalculado en %.1f s.', $label, $result['duration_ms'] / 1000));
        $this->line($service->exclusionSql() !== ''
            ? '  Exclusiones del matcher: APLICADAS (entity_association_exclusions)'
            : '  Exclusiones del matcher: NO aplicadas (desactivadas con entities:exclusions --disable, o sin tabla)');
        $this->line($service::titleExclusionSql() !== ''
            ? '  Ofertas excluidas por título: IGNORADAS (job_offers.title_exclusion_id)'
            : '  Ofertas excluidas por título: falta la columna job_offers.title_exclusion_id (no se filtran)');

        if (! $result['ok']) {
            $this->error('Algunos periodos fallaron; conservan sus datos anteriores. Revisa storage/logs.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
