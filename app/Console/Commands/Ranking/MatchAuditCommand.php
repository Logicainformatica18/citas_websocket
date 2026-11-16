<?php

namespace App\Console\Commands\Ranking;

use App\Services\Matching\EntityTextMatcher;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Auditoría del matcher sobre las asociaciones oferta↔entidad ya guardadas (solo lectura).
 *
 * Para cada asociación del pivote (language_job, technology_job…) en el semestre, decide con
 * EntityTextMatcher si el texto guardado de la oferta (título + description + requirements + skills)
 * menciona la entidad, y lo resume por fuente y entidad:
 *   confirmada            el matcher la confirma
 *   rechazada por regla   hay mención, pero la descarta una regla (mayúsculas, negativo, alias débil
 *                         sin contexto) → revisar la muestra: aquí están los verdaderos positivos que
 *                         perderían las reglas
 *   sin mención, texto completo   falso positivo casi seguro
 *   sin mención, texto corto      no verificable (solo título o extracto): la API vio más texto del
 *                                 que se guardó → cota superior de verdaderos positivos perdidos
 *   nuevas                el matcher encuentra en la misma oferta entidades que no estaban asociadas
 *
 * Procesa las ofertas en bloques (chunkById), con memoria constante aunque el pivote sea grande.
 * Escribe resumen.csv y muestras.csv en storage/app/match_audit/.
 */
class MatchAuditCommand extends Command
{
    protected $signature = 'entities:match-audit
        {--type=languages : clave de config/entity_rankings.php (languages, technologies…)}
        {--semester= : semestre AAAA-s1 / AAAA-s2 (por defecto, el actual)}
        {--source=* : solo estas fuentes (job_offers.source)}
        {--entity=* : solo estas entidades (id o slug)}
        {--chunk=500 : ofertas por bloque}
        {--sample=10 : ejemplos por fuente, entidad y categoría en muestras.csv}
        {--short=600 : caracteres de cuerpo por debajo de los cuales el texto se considera corto}
        {--top=40 : filas por fuente en la tabla de entidades de la consola}';

    protected $description = 'Mide la precisión de las asociaciones oferta↔entidad con el matcher (solo lectura)';

    private const CATS = ['assoc', 'confirmed', 'rej_case', 'rej_negative', 'rej_no_context',
        'nomention_full', 'nomention_short', 'new'];

    public function handle(): int
    {
        $type = (string) $this->option('type');
        $config = config("entity_rankings.{$type}");
        if (! $config) {
            $this->error("--type={$type} no existe en config/entity_rankings.php");

            return self::INVALID;
        }

        [$from, $to, $semester] = $this->semesterRange($this->option('semester'));
        if (! $from) {
            $this->error('--semester debe tener el formato AAAA-s1 o AAAA-s2');

            return self::INVALID;
        }

        $pivot = $config['pivot_table'];
        $entityType = $config['entity_type'];
        $matcher = EntityTextMatcher::for($entityType, includeInactive: true);
        $entities = DB::table('market_entities')->where('entity_type', $entityType)->get(['id', 'name', 'slug', 'is_active'])->keyBy('id');
        $onlyEntities = $this->resolveEntities($entities);
        $short = (int) $this->option('short');
        $sampleSize = (int) $this->option('sample');

        $base = DB::table('job_offers as jo')
            ->where('jo.published_at', '>=', $from)
            ->where('jo.published_at', '<', $to)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from("{$pivot} as p")->whereColumn('p.job_offer_id', 'jo.id'))
            ->when($this->option('source'), fn ($q, $s) => $q->whereIn('jo.source', $s));

        $totalOffers = (clone $base)->count();
        $this->info("Auditoría de {$type} ({$pivot}), semestre {$semester}: {$totalOffers} ofertas con asociaciones.");
        if ($totalOffers === 0) {
            return self::SUCCESS;
        }

        $stats = [];       // [source][entityId][cat] => n
        $textLevels = [];  // [source][level] => ofertas
        $samples = [];     // [source|entity|cat] => ['seen' => n, 'rows' => [...]]
        $orphans = ['sin_entidad' => 0, 'inactiva' => 0];
        $started = microtime(true);

        $bar = $this->output->createProgressBar($totalOffers);
        $bar->start();

        $base->select('jo.id', 'jo.source', 'jo.title', 'jo.description', 'jo.requirements', 'jo.skills', 'jo.url')
            ->chunkById((int) $this->option('chunk'), function ($offers) use (
                $pivot, $matcher, $entities, $onlyEntities, $short, $sampleSize,
                &$stats, &$textLevels, &$samples, &$orphans, $bar
            ) {
                $assoc = [];
                foreach (DB::table($pivot)->whereIn('job_offer_id', $offers->pluck('id'))
                    ->select('job_offer_id', 'market_entity_id')->distinct()->get() as $row) {
                    $assoc[$row->job_offer_id][] = $row->market_entity_id;
                }

                foreach ($offers as $offer) {
                    $source = $offer->source ?: '(sin fuente)';
                    $body = trim(implode("\n", array_filter([$offer->description, $offer->requirements, $offer->skills])));
                    $bodyLen = mb_strlen(EntityTextMatcher::normalize($body));
                    $level = $bodyLen === 0 ? 'titulo' : ($bodyLen < $short ? 'extracto' : 'completo');
                    $textLevels[$source][$level] = ($textLevels[$source][$level] ?? 0) + 1;

                    $linked = [];
                    foreach ($assoc[$offer->id] ?? [] as $entityId) {
                        if ($entityId === null) {
                            $orphans['sin_entidad']++;

                            continue;
                        }
                        $entityId = (int) $entityId;
                        $linked[$entityId] = true;
                        if ($onlyEntities && ! isset($onlyEntities[$entityId])) {
                            continue;
                        }
                        if (! ($entities[$entityId]->is_active ?? false)) {
                            $orphans['inactiva']++;

                            continue;
                        }

                        $r = $matcher->explain($entityId, (string) $offer->title, $body);
                        $cat = match ($r['verdict']) {
                            EntityTextMatcher::CONFIRMED => 'confirmed',
                            EntityTextMatcher::REJECTED => match ($r['reason']) {
                                EntityTextMatcher::REASON_CASE => 'rej_case',
                                EntityTextMatcher::REASON_NEGATIVE => 'rej_negative',
                                default => 'rej_no_context',
                            },
                            default => $level === 'completo' ? 'nomention_full' : 'nomention_short',
                        };
                        $stats[$source][$entityId]['assoc'] = ($stats[$source][$entityId]['assoc'] ?? 0) + 1;
                        $stats[$source][$entityId][$cat] = ($stats[$source][$entityId][$cat] ?? 0) + 1;
                        if ($cat !== 'confirmed') {
                            $this->sample($samples, $sampleSize, "{$source}|{$entityId}|{$cat}", $offer, $level, $r);
                        }
                    }

                    // Entidades que el matcher encuentra en la oferta y no estaban asociadas
                    foreach ($matcher->match((string) $offer->title, $body) as $entityId => $hit) {
                        // Inactivas sin reglas propias (organizaciones como IDC u OECD): no aportan
                        if (isset($linked[$entityId]) || ($onlyEntities && ! isset($onlyEntities[$entityId]))
                            || (! ($entities[$entityId]->is_active ?? false) && ! $matcher->hasRules($entityId))) {
                            continue;
                        }
                        $stats[$source][$entityId]['new'] = ($stats[$source][$entityId]['new'] ?? 0) + 1;
                        $this->sample($samples, $sampleSize, "{$source}|{$entityId}|new", $offer, $level,
                            ['verdict' => 'new', 'reason' => null] + $hit);
                    }

                    $bar->advance();
                }
            }, 'jo.id', 'id');

        $bar->finish();
        $this->newLine(2);

        $dir = storage_path('app/match_audit/' . $type . '_' . $semester . '_' . now()->format('Ymd_His'));
        @mkdir($dir, 0777, true);
        $this->writeSummary($dir . '/resumen.csv', $stats, $entities);
        $this->writeSamples($dir . '/muestras.csv', $samples, $entities);
        $this->printTables($stats, $textLevels, $entities);

        $this->line(sprintf('Asociaciones sin entidad: %d · de entidades inactivas (no cuentan en el ranking): %d',
            $orphans['sin_entidad'], $orphans['inactiva']));
        $this->info(sprintf('Listo en %.1f s · memoria máx. %.0f MB · %s', microtime(true) - $started,
            memory_get_peak_usage(true) / 1048576, $dir));

        return self::SUCCESS;
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} */
    private function semesterRange(?string $semester): array
    {
        if ($semester === null) {
            $now = CarbonImmutable::now();
            $semester = $now->year . '-s' . ($now->month <= 6 ? 1 : 2);
        }
        if (! preg_match('/^(\d{4})-s([12])$/', $semester, $m)) {
            return [null, null, null];
        }
        $start = CarbonImmutable::create((int) $m[1], $m[2] === '1' ? 1 : 7, 1);

        // Rango semiabierto [inicio, inicio + 6 meses), como el ranking
        return [$start->toDateTimeString(), $start->addMonths(6)->toDateTimeString(), $semester];
    }

    private function resolveEntities($entities): array
    {
        $wanted = $this->option('entity');
        if (! $wanted) {
            return [];
        }
        $ids = [];
        foreach ($wanted as $w) {
            foreach ($entities as $e) {
                if ((string) $e->id === (string) $w || strcasecmp($e->slug, $w) === 0 || strcasecmp($e->name, $w) === 0) {
                    $ids[$e->id] = true;
                }
            }
        }

        return $ids;
    }

    /** Muestreo por reservorio: ejemplos al azar sin guardar todo en memoria. */
    private function sample(array &$samples, int $size, string $key, object $offer, string $level, array $r): void
    {
        if ($size <= 0) {
            return;
        }
        $s = &$samples[$key];
        $s['seen'] = ($s['seen'] ?? 0) + 1;
        $row = [
            'offer_id' => $offer->id,
            'level' => $level,
            'title' => mb_substr((string) $offer->title, 0, 200),
            'reason' => $r['reason'] ?? '',
            'alias' => $r['alias'] ?? '',
            'snippet' => $r['snippet'] ?? '',
            'url' => mb_substr((string) $offer->url, 0, 300),
        ];
        if (count($s['rows'] ?? []) < $size) {
            $s['rows'][] = $row;
        } elseif (($j = random_int(0, $s['seen'] - 1)) < $size) {
            $s['rows'][$j] = $row;
        }
    }

    private function writeSummary(string $path, array $stats, $entities): void
    {
        $fh = fopen($path, 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['fuente', 'entity_id', 'entidad', 'activa', 'asociaciones', 'confirmadas', 'precision_%',
            'rech_mayusculas', 'rech_negativo', 'rech_sin_contexto', 'sin_mencion_texto_completo',
            'sin_mencion_texto_corto', 'nuevas']);
        foreach ($stats as $source => $byEntity) {
            foreach ($byEntity as $id => $c) {
                $c += array_fill_keys(self::CATS, 0);
                fputcsv($fh, [$source, $id, $entities[$id]->name ?? '?', (int) ($entities[$id]->is_active ?? 0),
                    $c['assoc'], $c['confirmed'], $c['assoc'] ? round(100 * $c['confirmed'] / $c['assoc'], 1) : '',
                    $c['rej_case'], $c['rej_negative'], $c['rej_no_context'], $c['nomention_full'],
                    $c['nomention_short'], $c['new']]);
            }
        }
        fclose($fh);
    }

    private function writeSamples(string $path, array $samples, $entities): void
    {
        $fh = fopen($path, 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['fuente', 'entidad', 'categoria', 'total_categoria', 'offer_id', 'texto', 'titulo',
            'motivo', 'alias', 'fragmento', 'url', 'es_verdadero_positivo (s/n)']);
        ksort($samples);
        foreach ($samples as $key => $s) {
            [$source, $id, $cat] = explode('|', $key);
            foreach ($s['rows'] as $row) {
                fputcsv($fh, [$source, $entities[(int) $id]->name ?? $id, $cat, $s['seen'], $row['offer_id'],
                    $row['level'], $row['title'], $row['reason'], $row['alias'], $row['snippet'], $row['url'], '']);
            }
        }
        fclose($fh);
    }

    private function printTables(array $stats, array $textLevels, $entities): void
    {
        $pct = fn ($a, $b) => $b ? sprintf('%.0f %%', 100 * $a / $b) : '—';
        $rows = [];
        foreach ($stats as $source => $byEntity) {
            $t = array_fill_keys(self::CATS, 0);
            foreach ($byEntity as $c) {
                foreach ($c as $k => $v) {
                    $t[$k] += $v;
                }
            }
            $lv = $textLevels[$source] ?? [];
            $offers = array_sum($lv);
            $rows[] = [$source, $offers,
                sprintf('%s / %s / %s', $pct($lv['completo'] ?? 0, $offers), $pct($lv['extracto'] ?? 0, $offers), $pct($lv['titulo'] ?? 0, $offers)),
                $t['assoc'], $pct($t['confirmed'], $t['assoc']),
                $t['rej_case'] + $t['rej_negative'] + $t['rej_no_context'],
                $t['nomention_full'], $t['nomention_short'], $t['new']];
        }
        usort($rows, fn ($a, $b) => $b[3] <=> $a[3]);
        $this->info('Por fuente');
        $this->table(['Fuente', 'Ofertas', 'Texto compl./extr./título', 'Asoc.', 'Precisión', 'Rech. por regla',
            'Sin mención (texto compl.)', 'Sin mención (corto)', 'Nuevas'], $rows);

        $top = (int) $this->option('top');
        foreach ($stats as $source => $byEntity) {
            uasort($byEntity, fn ($a, $b) => ($b['assoc'] ?? 0) <=> ($a['assoc'] ?? 0));
            $rows = [];
            foreach (array_slice($byEntity, 0, $top, true) as $id => $c) {
                $c += array_fill_keys(self::CATS, 0);
                $rows[] = [($entities[$id]->name ?? $id) . (($entities[$id]->is_active ?? 0) ? '' : ' (inactiva)'),
                    $c['assoc'], $c['confirmed'], $pct($c['confirmed'], $c['assoc']),
                    $c['rej_case'], $c['rej_negative'], $c['rej_no_context'],
                    $c['nomention_full'], $c['nomention_short'], $c['new']];
            }
            $this->info("Fuente: {$source}");
            $this->table(['Entidad', 'Asoc.', 'Conf.', 'Prec.', 'Rech. mayúsc.', 'Rech. negativo', 'Rech. sin contexto',
                'Sin mención (compl.)', 'Sin mención (corto)', 'Nuevas'], $rows);
        }
    }
}
