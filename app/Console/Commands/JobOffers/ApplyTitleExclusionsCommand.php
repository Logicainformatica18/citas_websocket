<?php

namespace App\Console\Commands\JobOffers;

use App\Models\JobOfferProcess;
use App\Services\JobOffers\TitleExclusionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Reevalúa TODAS las ofertas con la lista de exclusión por título (job_title_exclusion_keywords) y
 * marca job_offers.title_exclusion_id (NULL = válida). No borra nada; los rankings ignoran las
 * marcadas. Recorre en bloques de 2 000 ofertas (memoria constante). Ejecutarlo cuando cambie la lista
 * y después los ranking:refresh-* (o con --refresh). Queda registrado en job_offer_processes.
 */
class ApplyTitleExclusionsCommand extends Command
{
    protected $signature = 'job-offers:apply-title-exclusions
        {--dry-run : solo informa; no marca ofertas}
        {--clear : quita todas las marcas (apagado de emergencia; luego ranking:refresh-*)}
        {--refresh : al terminar ejecuta ranking:refresh-languages, -technologies y -certifications}';

    protected $description = 'Marca (sin borrar) las ofertas cuyo título es de un rol no TI, para que los rankings las ignoren';

    public function handle(TitleExclusionService $service): int
    {
        foreach (['job_title_exclusion_keywords', 'job_offer_processes'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn('job_offers', 'title_exclusion_id')) {
                $this->error('Falta la estructura: aplicar docs/produccion/ofertas_exclusion/01_exclusion_titulos.sql (o la migración).');

                return self::FAILURE;
            }
        }

        $dry = (bool) $this->option('dry-run');
        $process = JobOfferProcess::start('apply', [
            'dry_run' => $dry,
            'clear' => (bool) $this->option('clear'),
            'refresh' => false,   // en consola los rankings se recalculan aparte (o con --refresh)
            'origin' => 'consola',
        ]);
        $process->update(['status' => 'running', 'started_at' => now()]);

        $start = microtime(true);
        $bar = null;
        do {
            $done = $service->applyStep($process, microtime(true) + 5);
            $process->save();
            $bar ??= $this->output->createProgressBar($process->total);
            $bar->setProgress($process->processed);
        } while (! $done);
        $bar->finish();
        $this->newLine();

        $process->update(['status' => 'done', 'finished_at' => now()]);
        $r = $process->result;

        $this->info(sprintf('%s%d ofertas evaluadas en %.1f s · excluidas por título: %d (%.1f %%)',
            $dry ? '[DRY-RUN] ' : '', $r['total_offers'], microtime(true) - $start, $r['excluded'],
            $r['total_offers'] ? 100 * $r['excluded'] / $r['total_offers'] : 0));
        $this->line("  cambios: {$r['newly_excluded']} pasan a excluidas · {$r['restored_to_valid']} vuelven a válidas · {$r['changed_pattern']} cambian de patrón");
        $this->table(['Categoría', 'Ofertas'], collect($r['by_category'])->map(fn ($n, $c) => [$c, $n])->values()->all());
        $this->line("Registrado como proceso #{$process->id}.");

        if ($dry) {
            return self::SUCCESS;
        }
        if ($this->option('refresh')) {
            foreach (TitleExclusionService::RANKING_TYPES as $type) {
                $this->line("→ ranking:refresh-{$type}");
                Artisan::call("ranking:refresh-{$type}", [], $this->output);
            }
        } else {
            $this->info('Ahora: php artisan ranking:refresh-languages, ranking:refresh-technologies y ranking:refresh-certifications');
        }

        return self::SUCCESS;
    }
}
