<?php

namespace App\Console\Commands\Ranking;

use App\Services\Indicators\IndicatorScope;
use App\Services\Matching\EntityTextMatcher;
use App\Services\Ranking\ExclusionSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exclusión de asociaciones SIN borrar: llena entity_association_exclusions con los pares
 * (oferta, entidad) del pivote que el matcher no confirma en el texto guardado de la oferta
 * (título + description + requirements + skills). EntityRankingService los ignora al calcular.
 * language_job / technology_job / certification_job no se modifican.
 *
 * Criterio (el mismo que la limpieza entities:clean-associations, que no se usa en producción):
 *   - se evalúan las filas cuya entidad (market_entity_id del pivote) está ACTIVA;
 *   - las de entidades inactivas o sin entidad no cuentan en el ranking y no se evalúan;
 *   - se excluye todo lo que el matcher no confirma, incluidas las ofertas sin texto o con solo
 *     un extracto (criterio estricto).
 *
 * Fecha de confianza: la primera ejecución (no --dry-run) guarda su fecha y hora en
 * entity_association_exclusion_settings.trust_since. En las siguientes, las filas del pivote creadas
 * desde esa fecha no se re-evalúan: ya las confirmó el matcher al asociarlas, con el texto completo de
 * la API (que varios scrapers no guardan). Ver php artisan entities:exclusions.
 *
 * Re-ejecución: las exclusiones se insertan o se renuevan (revisado_en); las que ya no aplican
 * (p. ej. tras ajustar reglas o activar una entidad) pasan a vigente = 0. Nunca borra filas.
 * Procesa las ofertas en bloques (chunkById): memoria constante con millones de filas.
 * Después: php artisan ranking:refresh-{tipo}
 */
class ExcludeEntityAssociationsCommand extends Command
{
    protected $signature = 'entities:exclude-associations
        {--type=all : languages, technologies, certifications o all}
        {--dry-run : solo informa; no escribe exclusiones}
        {--chunk=500 : ofertas por bloque}';

    protected $description = 'Marca (sin borrar) las asociaciones oferta↔entidad que el matcher no confirma, para que los rankings las ignoren';

    public function handle(): int
    {
        $table = ExclusionSettings::TABLE;
        $dry = (bool) $this->option('dry-run');
        if (! $dry && ! Schema::hasTable($table)) {
            $this->error("Falta la tabla {$table}: aplicar antes DESPLIEGUE_RANKINGS_PRODUCCION.sql (o migrate).");

            return self::FAILURE;
        }

        $type = (string) $this->option('type');
        $types = $type === 'all' ? ['languages', 'technologies', 'certifications'] : [$type];
        foreach ($types as $t) {
            if (! config("entity_rankings.{$t}")) {
                $this->error("--type={$t} no existe en config/entity_rankings.php");

                return self::INVALID;
            }
        }

        if (! ExclusionSettings::enabled()) {
            $this->warn('Aviso: la exclusión está desactivada (entities:exclusions --disable): se calcula, pero el ranking no la aplica.');
        }

        // Fecha de confianza: la de la primera ejecución; en esta primera vez se evalúa todo
        $trustSince = ExclusionSettings::trustSince();
        $runStart = DB::selectOne('SELECT NOW() AS t')->t;
        $this->line($trustSince
            ? "Fecha de confianza: {$trustSince} (las asociaciones creadas desde entonces no se re-evalúan)"
            : 'Primera ejecución: se evalúan todas las asociaciones; su fecha y hora quedará como fecha de confianza.');

        foreach ($types as $t) {
            $this->processType($t, $table, $dry, $trustSince);
        }

        if (! $dry) {
            IndicatorScope::bumpCacheVersion();   // cachés de los indicadores (Datos Generales, Geo…)
        }

        if (! $dry && $trustSince === null && ExclusionSettings::initTrustSince($runStart)) {
            $this->info("Fecha de confianza guardada: {$runStart}");
        }

        $this->info('Listo. Ahora: php artisan ranking:refresh-' . ($type === 'all' ? '{languages,technologies,certifications}' : $type));

        return self::SUCCESS;
    }

    private function processType(string $type, string $table, bool $dry, ?string $trustSince): void
    {
        $cfg = config("entity_rankings.{$type}");
        $pivot = $cfg['pivot_table'];
        $startedAt = microtime(true);
        $runStart = DB::selectOne('SELECT NOW() AS t')->t;

        $matcher = EntityTextMatcher::for($cfg['entity_type']);   // solo entidades activas
        $active = array_flip($matcher->entityIds());

        $base = DB::table('job_offers as jo')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from("{$pivot} as p")->whereColumn('p.job_offer_id', 'jo.id'));
        $total = (clone $base)->count();
        $this->info(($dry ? '[DRY-RUN] ' : '') . "{$type} ({$pivot}): {$total} ofertas con asociaciones");

        $stats = ['evaluados' => 0, 'confirmados' => 0, 'excluidos' => 0, 'no_evaluados' => 0, 'confiadas' => 0];
        $bySource = [];
        $bar = $this->output->createProgressBar($total);

        $base->select('jo.id', 'jo.source', 'jo.title', 'jo.description', 'jo.requirements', 'jo.skills')
            ->chunkById((int) $this->option('chunk'), function ($offers) use (
                $pivot, $table, $dry, $matcher, $active, $runStart, $trustSince, &$stats, &$bySource, $bar
            ) {
                $pairs = [];
                $trusted = [];
                foreach (DB::table($pivot)->whereIn('job_offer_id', $offers->pluck('id'))
                    ->get(['id', 'job_offer_id', 'market_entity_id', 'created_at']) as $r) {
                    if ($r->market_entity_id === null) {
                        continue;
                    }
                    if ($trustSince && $r->created_at !== null && $r->created_at >= $trustSince) {
                        $trusted[$r->job_offer_id][(int) $r->market_entity_id] = true;
                    }
                    $pairs[$r->job_offer_id][(int) $r->market_entity_id] ??= $r->id;
                }

                $rows = [];
                foreach ($offers as $offer) {
                    $body = implode("\n", array_filter([$offer->description, $offer->requirements, $offer->skills]));
                    foreach ($pairs[$offer->id] ?? [] as $entityId => $pivotId) {
                        if (! isset($active[$entityId])) {
                            $stats['no_evaluados']++;

                            continue;
                        }
                        if (isset($trusted[$offer->id][$entityId])) {
                            $stats['confiadas']++;

                            continue;
                        }
                        $stats['evaluados']++;
                        $v = $matcher->explain($entityId, (string) $offer->title, $body);
                        if ($v['verdict'] === EntityTextMatcher::CONFIRMED) {
                            $stats['confirmados']++;

                            continue;
                        }
                        $stats['excluidos']++;
                        $src = $offer->source ?: '(sin fuente)';
                        $bySource[$src] = ($bySource[$src] ?? 0) + 1;
                        $rows[] = [
                            'pivot_table' => $pivot,
                            'job_offer_id' => $offer->id,
                            'market_entity_id' => $entityId,
                            'pivot_id' => $pivotId,
                            'source' => mb_substr($src, 0, 100),
                            'motivo' => $v['verdict'] === EntityTextMatcher::REJECTED ? 'regla: ' . $v['reason'] : 'sin mención',
                            'vigente' => 1,
                            'revisado_en' => $runStart,
                            'created_at' => $runStart,
                        ];
                    }
                    $bar->advance();
                }

                if (! $dry && $rows) {
                    foreach (array_chunk($rows, 1000) as $batch) {
                        // Inserta o renueva (sin tocar created_at); nunca borra
                        DB::table($table)->upsert($batch, ['pivot_table', 'job_offer_id', 'market_entity_id'],
                            ['pivot_id', 'source', 'motivo', 'vigente', 'revisado_en']);
                    }
                }
            }, 'jo.id', 'id');

        $bar->finish();
        $this->newLine();

        $retiradas = 0;
        if (! $dry) {
            // Exclusiones de corridas anteriores que hoy el matcher confirma (o de entidades ya
            // inactivas): dejan de aplicarse, pero se conservan como historial.
            $retiradas = DB::table($table)->where('pivot_table', $pivot)->where('vigente', 1)
                ->where('revisado_en', '<', $runStart)->update(['vigente' => 0]);
        }
        $vigentes = $dry ? null : DB::table($table)->where('pivot_table', $pivot)->where('vigente', 1)->count();

        arsort($bySource);
        $this->line(sprintf('  evaluadas %d · confirmadas %d · excluidas %d (%.1f %%) · no evaluadas %d (entidad inactiva) · confiadas %d (creadas desde %s) · retiradas %d%s · %.1f s',
            $stats['evaluados'], $stats['confirmados'], $stats['excluidos'],
            $stats['evaluados'] ? 100 * $stats['excluidos'] / $stats['evaluados'] : 0,
            $stats['no_evaluados'], $stats['confiadas'], $trustSince ?? '—', $retiradas, $vigentes === null ? '' : " · vigentes {$vigentes}",
            microtime(true) - $startedAt));
        $this->line('  excluidas por fuente: ' . implode(', ', array_map(fn ($s, $n) => "{$s} {$n}",
            array_keys(array_slice($bySource, 0, 8, true)), array_slice($bySource, 0, 8, true))));
    }
}
