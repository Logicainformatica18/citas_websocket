<?php

namespace App\Console\Commands\Ranking;

use App\Services\Matching\EntityTextMatcher;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Limpieza de asociaciones oferta↔entidad con el matcher (Fase C / Fase 4).
 *
 * NO borra nada del pivote: decide qué filas sobran y las deja en la tabla de trabajo
 * {pivote}_limpieza. El borrado (con respaldo en {pivote}_respaldo_limpieza) lo hace el script SQL
 * del DBA ({nn}_limpieza_aplicar.sql), y la reversión su script de rollback.
 *
 * Se marca para borrar una fila cuando su entidad está ACTIVA y el matcher no la confirma en el texto
 * guardado de la oferta (título + description + requirements + skills), incluidas las ofertas sin texto
 * o con solo un extracto (criterio estricto aprobado: exigir la confirmación). Se conservan:
 *   - las filas de entidades inactivas (no cuentan en el ranking; se recuperan al reactivar);
 *   - las filas sin entidad resoluble (ni en el pivote ni en el catálogo).
 * Con --duplicates (tecnologías) además reasigna las filas de entidades duplicadas desactivadas a su
 * principal (ver config entity_rankings.{tipo}.duplicates): quedan en la tabla de trabajo con
 * accion = 'reasignar' y el id de catálogo/entidad de destino.
 *
 * Procesa las ofertas en bloques (chunkById): memoria constante aunque el pivote tenga millones de filas.
 */
class CleanEntityAssociationsCommand extends Command
{
    protected $signature = 'entities:clean-associations
        {--type=languages : clave de config/entity_rankings.php}
        {--dry-run : solo informa; no escribe la tabla de trabajo}
        {--semester= : limitar a un semestre AAAA-s1/AAAA-s2 (por defecto, todas las ofertas)}
        {--source=* : solo estas fuentes}
        {--chunk=500 : ofertas por bloque}
        {--top=30 : filas por tabla en la consola}';

    protected $description = 'Marca las asociaciones oferta↔entidad que el matcher no confirma (tabla de trabajo para el DBA)';

    public function handle(): int
    {
        $type = (string) $this->option('type');
        $cfg = config("entity_rankings.{$type}");
        if (! $cfg) {
            $this->error("--type={$type} no existe en config/entity_rankings.php");

            return self::INVALID;
        }

        $pivot = $cfg['pivot_table'];
        $catalog = $cfg['catalog_table'];
        $fk = $cfg['catalog_fk'];
        $work = "{$pivot}_limpieza";
        $dry = (bool) $this->option('dry-run');

        if (! $dry && ! Schema::hasTable($work)) {
            $this->error("Falta la tabla de trabajo {$work}: aplicar antes el script SQL de preparación de la limpieza.");

            return self::FAILURE;
        }

        $matcher = EntityTextMatcher::for($cfg['entity_type'], includeInactive: true);
        $entities = DB::table('market_entities')->where('entity_type', $cfg['entity_type'])->get(['id', 'name', 'is_active'])->keyBy('id');
        $catalogEntity = DB::table($catalog)->pluck('market_entity_id', 'id');

        // Duplicados (tecnologías): entidad desactivada => [entidad principal, fila de catálogo de la principal]
        $duplicates = [];
        foreach ($cfg['duplicates'] ?? [] as $dupSlug => $mainSlug) {
            $dup = DB::table('market_entities')->where('entity_type', $cfg['entity_type'])->where('slug', $dupSlug)->value('id');
            $main = DB::table('market_entities')->where('entity_type', $cfg['entity_type'])->where('slug', $mainSlug)->where('is_active', 1)->value('id');
            $mainCatalog = $main ? DB::table($catalog)->where('market_entity_id', $main)->where('enabled', 1)->orderBy('id')->value('id') : null;
            if ($dup && $main && $mainCatalog) {
                $duplicates[(int) $dup] = [(int) $main, (int) $mainCatalog];
            }
        }

        $base = DB::table('job_offers as jo')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from("{$pivot} as p")->whereColumn('p.job_offer_id', 'jo.id'))
            ->when($this->option('source'), fn ($q, $s) => $q->whereIn('jo.source', $s));
        if ($sem = $this->option('semester')) {
            if (! preg_match('/^(\d{4})-s([12])$/', $sem, $m)) {
                $this->error('--semester debe tener el formato AAAA-s1 o AAAA-s2');

                return self::INVALID;
            }
            $start = CarbonImmutable::create((int) $m[1], $m[2] === '1' ? 1 : 7, 1);
            $base->where('jo.published_at', '>=', $start)->where('jo.published_at', '<', $start->addMonths(6));
        }

        $total = (clone $base)->count();
        $this->info(($dry ? '[DRY-RUN] ' : '') . "Limpieza de {$pivot}: {$total} ofertas con asociaciones.");

        if (! $dry) {
            DB::table($work)->truncate();
        }

        $stats = [];   // [source][entityId] => [total, keep, delete, reassign]
        $kept = ['inactiva' => 0, 'sin_entidad' => 0];
        $startedAt = microtime(true);
        $bar = $this->output->createProgressBar($total);

        $base->select('jo.id', 'jo.source', 'jo.title', 'jo.description', 'jo.requirements', 'jo.skills')
            ->chunkById((int) $this->option('chunk'), function ($offers) use (
                $pivot, $fk, $work, $dry, $matcher, $entities, $catalogEntity, $duplicates, &$stats, &$kept, $bar
            ) {
                $byOffer = [];
                foreach (DB::table($pivot)->whereIn('job_offer_id', $offers->pluck('id'))
                    ->get(['id', 'job_offer_id', $fk, 'market_entity_id']) as $row) {
                    $byOffer[$row->job_offer_id][] = $row;
                }

                $rows = [];
                $now = now();
                foreach ($offers as $offer) {
                    $source = $offer->source ?: '(sin fuente)';
                    $body = implode("\n", array_filter([$offer->description, $offer->requirements, $offer->skills]));
                    $entitiesOfOffer = [];
                    foreach ($byOffer[$offer->id] ?? [] as $r) {
                        $entitiesOfOffer[(int) ($r->market_entity_id ?? $catalogEntity[$r->{$fk}] ?? 0)] = true;
                    }

                    foreach ($byOffer[$offer->id] ?? [] as $r) {
                        $entityId = (int) ($r->market_entity_id ?? $catalogEntity[$r->{$fk}] ?? 0);
                        if ($entityId === 0 || ! isset($entities[$entityId])) {
                            $kept['sin_entidad']++;

                            continue;
                        }

                        // Duplicado desactivado → se evalúa como su principal
                        $target = $duplicates[$entityId] ?? null;
                        $evalId = $target[0] ?? $entityId;

                        if (! $target && ! $entities[$entityId]->is_active) {
                            $kept['inactiva']++;

                            continue;
                        }

                        $s = &$stats[$source][$evalId];
                        $s['total'] = ($s['total'] ?? 0) + 1;

                        $verdict = $matcher->explain($evalId, (string) $offer->title, $body);
                        $confirmed = $verdict['verdict'] === EntityTextMatcher::CONFIRMED;

                        if ($confirmed && ! $target) {
                            $s['keep'] = ($s['keep'] ?? 0) + 1;

                            continue;
                        }

                        if ($confirmed && $target && ! isset($entitiesOfOffer[$target[0]])) {
                            $s['reassign'] = ($s['reassign'] ?? 0) + 1;
                            $entitiesOfOffer[$target[0]] = true;
                            $action = 'reasignar';
                            $motivo = 'duplicado de ' . ($entities[$target[0]]->name ?? $target[0]);
                        } else {
                            $s['delete'] = ($s['delete'] ?? 0) + 1;
                            $action = 'borrar';
                            $motivo = $confirmed
                                ? 'duplicado: la oferta ya tiene la principal'
                                : ($verdict['verdict'] === EntityTextMatcher::REJECTED ? 'regla: ' . $verdict['reason'] : 'sin mención');
                        }

                        $rows[] = [
                            'pivot_id' => $r->id,
                            'job_offer_id' => $r->job_offer_id,
                            'market_entity_id' => $entityId,
                            'accion' => $action,
                            'nuevo_catalog_id' => $action === 'reasignar' ? $target[1] : null,
                            'nuevo_market_entity_id' => $action === 'reasignar' ? $target[0] : null,
                            'source' => mb_substr($source, 0, 100),
                            'motivo' => mb_substr($motivo, 0, 100),
                            'created_at' => $now,
                        ];
                    }
                    $bar->advance();
                }

                if (! $dry && $rows) {
                    foreach (array_chunk($rows, 1000) as $batch) {
                        DB::table($work)->insert($batch);
                    }
                }
            }, 'jo.id', 'id');

        $bar->finish();
        $this->newLine(2);

        $this->report($stats, $entities, $kept);
        $this->info(sprintf('%s en %.1f s · memoria máx. %.0f MB', $dry ? 'Simulación terminada' : "Tabla de trabajo {$work} lista",
            microtime(true) - $startedAt, memory_get_peak_usage(true) / 1048576));

        return self::SUCCESS;
    }

    private function report(array $stats, $entities, array $kept): void
    {
        $bySource = [];
        $byEntity = [];
        foreach ($stats as $source => $rows) {
            foreach ($rows as $id => $c) {
                foreach (['total', 'keep', 'delete', 'reassign'] as $k) {
                    $bySource[$source][$k] = ($bySource[$source][$k] ?? 0) + ($c[$k] ?? 0);
                    $byEntity[$id][$k] = ($byEntity[$id][$k] ?? 0) + ($c[$k] ?? 0);
                }
            }
        }
        $fmt = fn ($c, $label) => [$label, $c['total'] ?? 0, $c['keep'] ?? 0, $c['delete'] ?? 0, $c['reassign'] ?? 0,
            ($c['total'] ?? 0) ? sprintf('%.0f %%', 100 * ($c['delete'] ?? 0) / $c['total']) : '—'];

        uasort($bySource, fn ($a, $b) => ($b['total'] ?? 0) <=> ($a['total'] ?? 0));
        $this->table(['Fuente', 'Filas', 'Se conservan', 'Se borran', 'Se reasignan', '% borrado'],
            array_map(fn ($s) => $fmt($bySource[$s], $s), array_keys($bySource)));

        uasort($byEntity, fn ($a, $b) => ($b['total'] ?? 0) <=> ($a['total'] ?? 0));
        $rows = [];
        foreach (array_slice($byEntity, 0, (int) $this->option('top'), true) as $id => $c) {
            $rows[] = $fmt($c, $entities[$id]->name ?? $id);
        }
        $this->table(['Entidad', 'Filas', 'Se conservan', 'Se borran', 'Se reasignan', '% borrado'], $rows);

        $t = ['total' => 0, 'keep' => 0, 'delete' => 0, 'reassign' => 0];
        foreach ($bySource as $c) {
            foreach ($t as $k => $_) {
                $t[$k] += $c[$k] ?? 0;
            }
        }
        $this->line(sprintf('TOTAL: %d filas evaluadas · %d se conservan · %d se borran · %d se reasignan · '
            . 'no evaluadas: %d de entidades inactivas, %d sin entidad', $t['total'], $t['keep'], $t['delete'], $t['reassign'],
            $kept['inactiva'], $kept['sin_entidad']));
    }
}
