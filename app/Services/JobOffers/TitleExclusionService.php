<?php

namespace App\Services\JobOffers;

use App\Services\Indicators\IndicatorScope;
use App\Jobs\RefreshRankingsChainJob;
use App\Models\JobOffer;
use App\Models\JobOfferProcess;
use App\Models\JobTitleExclusionKeyword;
use App\Services\Ranking\EntityRankingService;
use App\Services\Ranking\ExclusionSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Procesos de la exclusión de ofertas por título. Cada "step" trabaja hasta $deadline y guarda su
 * avance en el proceso (cursor_id, processed, state); el Job que lo llama se vuelve a encolar si no
 * terminó (ver App\Jobs\JobOfferProcessJob). El comando de consola llama a los mismos métodos sin
 * límite de tiempo.
 *
 *   applyStep    marca job_offers.title_exclusion_id con la lista ACTIVA (no borra nada) y cuenta las
 *                coincidencias de cada patrón; al terminar encola el recálculo de los rankings.
 *   analyzeStep  simulación: qué excluiría la lista actual (por categoría, patrón y fuente, con ejemplos),
 *                cuántas asociaciones tienen esas ofertas y cómo cambiarían los tres rankings.
 *   deleteStep   borra las ofertas de un análisis y sus asociaciones, respaldando antes cada fila (JSON)
 *                en job_offer_deletion_backups.
 *   restoreStep  revierte una eliminación desde su respaldo.
 *   suggest      "Sugerir con IA" (misma integración que "Detectar nuevos lenguajes con IA").
 *   preview      cuántas ofertas coinciden con un patrón y ejemplos de títulos (falsos positivos).
 */
class TitleExclusionService
{
    public const CHUNK = 2000;

    public const DELETE_CHUNK = 300;

    public const RANKING_TYPES = ['languages', 'technologies', 'certifications'];

    /** Tablas propias con job_offer_id que no son asociaciones de la oferta. */
    public const OWN_TABLES = ['job_title_exclusion_candidates', 'job_offer_deletion_backups'];

    public const LIST_CHANGED_KEY = 'job_title_exclusions:list_changed_at';

    /* ==================================================
       APLICAR (marcar ofertas existentes)
    ================================================== */

    public function applyStep(JobOfferProcess $p, float $deadline): bool
    {
        $state = $p->state ?? [];
        $dry = (bool) ($p->params['dry_run'] ?? false);
        $clear = (bool) ($p->params['clear'] ?? false);

        if (! isset($state['init'])) {
            $p->total = DB::table('job_offers')->count();
            $p->cursor_id = 0;
            $p->processed = 0;
            $state = ['init' => 1, 'list_at' => now()->toDateTimeString(), 'excluded' => 0, 'to_excluded' => 0,
                'to_valid' => 0, 'changed_pattern' => 0, 'by_keyword' => [], 'matches' => []];
        }

        TitleExclusionMatcher::flush();
        $active = $clear ? new TitleExclusionMatcher([]) : TitleExclusionMatcher::active();
        $all = TitleExclusionMatcher::all();

        while (microtime(true) < $deadline) {
            $rows = DB::table('job_offers')->where('id', '>', (int) $p->cursor_id)->orderBy('id')
                ->limit(self::CHUNK)->get(['id', 'title', 'title_exclusion_id']);

            if ($rows->isEmpty()) {
                $p->state = $state;
                $this->finishApply($p, $dry);

                return true;
            }

            $updates = [];
            foreach ($rows as $r) {
                $title = (string) $r->title;
                $new = $active->excludedId($title);
                $old = $r->title_exclusion_id === null ? null : (int) $r->title_exclusion_id;
                if ($new !== null) {
                    $state['excluded']++;
                    $state['by_keyword'][$new] = ($state['by_keyword'][$new] ?? 0) + 1;
                }
                if ($new !== $old) {
                    $updates[$new ?? 0][] = $r->id;
                    $old === null ? $state['to_excluded']++ : ($new === null ? $state['to_valid']++ : $state['changed_pattern']++);
                }
                if ($title !== '') {
                    foreach ($all->matchingIds(TitleExclusionMatcher::normalize($title)) as $kid) {
                        $state['matches'][$kid] = ($state['matches'][$kid] ?? 0) + 1;
                    }
                }
            }

            if (! $dry) {
                foreach ($updates as $value => $ids) {
                    foreach (array_chunk($ids, 1000) as $batch) {
                        // DB::table: no toca updated_at ni dispara eventos
                        DB::table('job_offers')->whereIn('id', $batch)->update(['title_exclusion_id' => $value ?: null]);
                    }
                }
            }

            $p->cursor_id = $rows->last()->id;
            $p->processed += $rows->count();
        }

        $p->state = $state;

        return false;
    }

    private function finishApply(JobOfferProcess $p, bool $dry): void
    {
        $state = $p->state;
        $keywords = JobTitleExclusionKeyword::orderBy('id')->get()->keyBy('id');

        if (! $dry) {
            IndicatorScope::bumpCacheVersion();   // Datos Generales, Geo… (cachés de los indicadores)
            $now = now();
            foreach ($keywords as $id => $k) {
                DB::table('job_title_exclusion_keywords')->where('id', $id)
                    ->update(['matches_count' => (int) ($state['matches'][$id] ?? 0), 'counted_at' => $now]);
            }
        }

        $byCategory = [];
        foreach ($state['by_keyword'] as $id => $n) {
            $cat = $keywords[$id]->category ?? '(sin categoría)';
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + $n;
        }
        arsort($byCategory);

        $p->result = [
            'dry_run' => $dry,
            'cleared' => (bool) ($p->params['clear'] ?? false),
            'total_offers' => $p->processed,
            'excluded' => $state['excluded'],
            'newly_excluded' => $state['to_excluded'],
            'restored_to_valid' => $state['to_valid'],
            'changed_pattern' => $state['changed_pattern'],
            'by_category' => $byCategory,
            'list_at' => $state['list_at'],
        ];

        if (! $dry && ($p->params['refresh'] ?? true)) {
            $this->startRankingRefresh($p);
        }
    }

    /* ==================================================
       ANALIZAR ("Analizar basura")
    ================================================== */

    public function analyzeStep(JobOfferProcess $p, float $deadline): bool
    {
        $state = $p->state ?? [];
        [$year, $period] = $this->analysisPeriod($p);
        [$pFrom, $pTo] = $this->semesterRange($year, $period);

        if (! isset($state['init'])) {
            // Candidatos de análisis anteriores: se conservan solo los de este proceso
            DB::table('job_title_exclusion_candidates')->where('process_id', '<>', $p->id)->delete();
            $p->total = DB::table('job_offers')->count();
            $p->cursor_id = 0;
            $p->processed = 0;
            $state = ['init' => 1, 'phase' => 'scan', 'excluded' => 0, 'already' => 0, 'in_period' => 0,
                'by_keyword' => [], 'ti' => [], 'by_source' => [], 'examples' => []];
        }

        $didWork = false;   // ¿este tramo ya hizo algo? (para no empezar una fase pesada al final)
        if ($state['phase'] === 'scan') {
            $didWork = true;
            TitleExclusionMatcher::flush();
            $active = TitleExclusionMatcher::active();
            while (microtime(true) < $deadline) {
                $rows = DB::table('job_offers')->where('id', '>', (int) $p->cursor_id)->orderBy('id')
                    ->limit(self::CHUNK)->get(['id', 'title', 'source', 'published_at', 'title_exclusion_id']);
                if ($rows->isEmpty()) {
                    $state['phase'] = 'assoc';
                    break;
                }
                $insert = [];
                foreach ($rows as $r) {
                    $kid = $active->excludedId($r->title);
                    if ($kid === null) {
                        continue;
                    }
                    $insert[] = ['process_id' => $p->id, 'job_offer_id' => $r->id, 'keyword_id' => $kid];
                    $state['excluded']++;
                    $state['by_keyword'][$kid] = ($state['by_keyword'][$kid] ?? 0) + 1;
                    $src = $r->source ?: '(sin fuente)';
                    $state['by_source'][$src] = ($state['by_source'][$src] ?? 0) + 1;
                    if ($r->title_exclusion_id !== null) {
                        $state['already']++;
                    }
                    if ($r->published_at >= $pFrom && $r->published_at < $pTo) {
                        $state['in_period']++;
                    }
                    if (TitleExclusionMatcher::hasTiWord((string) $r->title)) {
                        $state['ti'][$kid] = ($state['ti'][$kid] ?? 0) + 1;
                    }
                    $ex = &$state['examples'][$kid];
                    $ex ??= [];
                    // Ejemplos variados: los primeros 3 y luego uno de cada ~50
                    if (count($ex) < 3 || (count($ex) < 8 && $state['by_keyword'][$kid] % 50 === 0)) {
                        $ex[] = mb_substr((string) $r->title, 0, 140);
                    }
                    unset($ex);
                }
                foreach (array_chunk($insert, 1000) as $batch) {
                    DB::table('job_title_exclusion_candidates')->insert($batch);
                }
                $p->cursor_id = $rows->last()->id;
                $p->processed += $rows->count();
            }
            $p->state = $state;
            if ($state['phase'] === 'scan') {
                return false;
            }
        }

        // Fases de cierre: una consulta pesada por fase (hasta ~30 s). Solo se empieza una si quedan 30 s
        // del tramo, o si es lo primero que hace este Job; si no, sigue en el siguiente.
        $phases = array_merge(['assoc'], array_map(fn ($t) => "impact:{$t}", self::RANKING_TYPES), ['done']);
        while ($state['phase'] !== 'done' && (! $didWork || microtime(true) < $deadline - 30)) {
            $didWork = true;
            $t0 = microtime(true);
            if ($state['phase'] === 'assoc') {
                $state['associations'] = $this->associationCounts('job_title_exclusion_candidates', $p->id);
            } else {
                $type = substr($state['phase'], strlen('impact:'));
                $state['impact'][$type] = $this->indicatorImpact($type, $p->id, $year, $period);
            }
            $state['timings'][$state['phase']] = round(microtime(true) - $t0, 1);
            $state['phase'] = $phases[array_search($state['phase'], $phases, true) + 1];
            $p->state = $state;
        }

        if ($state['phase'] !== 'done') {
            return false;
        }

        $p->result = $this->analysisResult($p, $state, $year, $period);
        $p->state = ['init' => 1, 'phase' => 'done'];   // el resultado ya tiene todo

        return true;
    }

    private function analysisResult(JobOfferProcess $p, array $state, int $year, string $period): array
    {
        $keywords = JobTitleExclusionKeyword::orderBy('id')->get()->keyBy('id');
        $byKeyword = [];
        $byCategory = [];
        foreach ($state['by_keyword'] as $id => $n) {
            $k = $keywords[$id] ?? null;
            $cat = $k?->category ?: '(sin categoría)';
            $byKeyword[] = [
                'id' => (int) $id,
                'pattern' => $k?->pattern ?? "#{$id}",
                'match_mode' => $k?->match_mode,
                'whole_word' => (bool) $k?->whole_word,
                'category' => $cat,
                'offers' => $n,
                'with_ti_word' => (int) ($state['ti'][$id] ?? 0),
                'examples' => $state['examples'][$id] ?? [],
            ];
            $byCategory[$cat]['category'] = $cat;
            $byCategory[$cat]['offers'] = ($byCategory[$cat]['offers'] ?? 0) + $n;
            $byCategory[$cat]['with_ti_word'] = ($byCategory[$cat]['with_ti_word'] ?? 0) + (int) ($state['ti'][$id] ?? 0);
            $byCategory[$cat]['examples'] = array_slice(array_merge($byCategory[$cat]['examples'] ?? [], array_slice($state['examples'][$id] ?? [], 0, 2)), 0, 8);
        }
        usort($byKeyword, fn ($a, $b) => $b['offers'] <=> $a['offers']);
        $byCategory = array_values($byCategory);
        usort($byCategory, fn ($a, $b) => $b['offers'] <=> $a['offers']);
        arsort($state['by_source']);

        return [
            'total_offers' => $p->processed,
            'excluded' => $state['excluded'],
            'already_marked' => $state['already'],
            'newly_excluded' => $state['excluded'] - $state['already'],
            'period' => ['year' => $year, 'period' => $period, 'label' => "{$year}-{$period}", 'excluded' => $state['in_period']],
            'by_category' => $byCategory,
            'by_keyword' => $byKeyword,
            'by_source' => collect($state['by_source'])->map(fn ($n, $s) => ['source' => $s, 'offers' => $n])->values()->all(),
            'associations' => $state['associations'] ?? [],
            'associations_total' => array_sum(array_column($state['associations'] ?? [], 'rows')),
            'impact' => $state['impact'] ?? [],
            'timings' => $state['timings'] ?? [],
        ];
    }

    /** Semestre del análisis: el pedido o el actual. */
    private function analysisPeriod(JobOfferProcess $p): array
    {
        $year = (int) ($p->params['year'] ?? now()->year);
        $period = in_array($p->params['period'] ?? null, ['s1', 's2'], true) ? $p->params['period'] : (now()->month <= 6 ? 's1' : 's2');

        return [$year, $period];
    }

    private function semesterRange(int $year, string $period): array
    {
        return $period === 's1'
            ? ["{$year}-01-01 00:00:00", "{$year}-07-01 00:00:00"]
            : ["{$year}-07-01 00:00:00", ($year + 1) . '-01-01 00:00:00'];
    }

    /**
     * Filas de cada tabla con job_offer_id que pertenecen a las ofertas candidatas.
     *
     * @return array<int, array{table: string, rows: int}>
     */
    public function associationCounts(string $candidatesTable, int $processId): array
    {
        $out = [];
        foreach ($this->jobOfferTables() as $table) {
            $n = DB::table("{$table} as t")
                ->join("{$candidatesTable} as c", 'c.job_offer_id', '=', 't.job_offer_id')
                ->where('c.process_id', $processId)
                ->count();
            if ($n > 0) {
                $out[] = ['table' => $table, 'rows' => $n];
            }
        }
        usort($out, fn ($a, $b) => $b['rows'] <=> $a['rows']);

        return $out;
    }

    /**
     * Cómo cambiaría un ranking en el semestre si se excluyeran los candidatos del análisis: vacantes
     * por entidad antes/después (mismos filtros que EntityRankingService) y posiciones recalculadas con la
     * fórmula del ranking (labor = LOG(n+1)/LOG(max+1)*100; la tendencia no cambia) y los pesos vigentes.
     */
    public function indicatorImpact(string $type, int $processId, int $year, string $period): array
    {
        $svc = EntityRankingService::for($type);
        [$from, $to] = $this->semesterRange($year, $period);
        $base = 'FROM job_offers j
                 JOIN ' . $svc->pivotTable() . ' lj ON lj.job_offer_id = j.id
                 LEFT JOIN job_title_exclusion_candidates c ON c.process_id = ? AND c.job_offer_id = j.id
                WHERE j.published_at >= ? AND j.published_at < ?' . EntityRankingService::titleExclusionSql() . '
                  AND lj.market_entity_id IN (' . $svc->activeIdsSql() . ')' . $svc->exclusionSql();

        // Un solo recorrido: WITH ROLLUP agrega la fila (NULL) con las vacantes únicas del semestre
        $rows = DB::select('SELECT lj.market_entity_id AS e, COUNT(DISTINCT lj.job_offer_id) AS before_n,
                   COUNT(DISTINCT CASE WHEN c.job_offer_id IS NULL THEN lj.job_offer_id END) AS after_n '
            . $base . ' GROUP BY lj.market_entity_id WITH ROLLUP', [$processId, $from, $to]);

        $counts = [];
        $tot = (object) ['before_n' => 0, 'after_n' => 0];
        foreach ($rows as $r) {
            if ($r->e === null) {
                $tot = $r;
            } else {
                $counts[(int) $r->e] = [(int) $r->before_n, (int) $r->after_n];
            }
        }

        // Entidades del ranking guardado (con su tendencia) para recalcular posiciones
        [$lw, $tw] = $svc->activeWeights();
        $ranked = DB::table($svc->table('rankings') . ' as r')
            ->join('market_entities as me', fn ($j) => $j->on('me.id', '=', 'r.market_entity_id')->where('me.is_active', 1))
            ->where('r.year', $year)->where('r.period', $period)
            ->get(['r.market_entity_id', 'r.name', 'r.trend_score']);

        $rank = function (int $idx) use ($ranked, $counts, $lw, $tw) {
            $max = max(1, ...array_map(fn ($c) => $c[$idx], $counts ?: [[0, 0]]));
            $list = $ranked->map(function ($r) use ($counts, $idx, $max, $lw, $tw) {
                $n = $counts[(int) $r->market_entity_id][$idx] ?? 0;
                $labor = log($n + 1) / log($max + 1) * 100;

                return ['id' => (int) $r->market_entity_id, 'name' => $r->name, 'jobs' => $n,
                    'final' => round($labor * $lw + (float) $r->trend_score * $tw, 1)];
            })->sortBy([['final', 'desc'], ['jobs', 'desc'], ['name', 'asc']])->values();

            return $list->map(fn ($r, $i) => $r + ['position' => $i + 1])->keyBy('id');
        };
        $before = $rank(0);
        $after = $rank(1);

        $drops = collect($counts)->map(fn ($c, $id) => ['id' => $id, 'name' => $before[$id]['name'] ?? "#{$id}",
            'before' => $c[0], 'after' => $c[1], 'diff' => $c[1] - $c[0],
            'position_before' => $before[$id]['position'] ?? null, 'position_after' => $after[$id]['position'] ?? null])
            ->filter(fn ($d) => $d['diff'] < 0)->sortBy('diff')->take(10)->values()->all();

        $top = fn ($list, $other) => $list->take(10)->map(fn ($r) => $r + ['other_position' => $other[$r['id']]['position'] ?? null])->values()->all();

        return [
            'label' => $svc->label(),
            'vacantes_before' => (int) $tot->before_n,
            'vacantes_after' => (int) $tot->after_n,
            'associations_removed' => array_sum(array_map(fn ($c) => $c[0] - $c[1], $counts)),
            'top_before' => $top($before, $after),
            'top_after' => $top($after, $before),
            'biggest_drops' => $drops,
        ];
    }

    /* ==================================================
       ELIMINAR DEFINITIVAMENTE (con respaldo)
    ================================================== */

    /** Tablas con columna job_offer_id (asociaciones de la oferta), sin las propias de este módulo. */
    public function jobOfferTables(): array
    {
        static $tables = null;

        return $tables ??= collect(DB::select(
            "SELECT c.TABLE_NAME AS t FROM information_schema.COLUMNS c
               JOIN information_schema.TABLES tb ON tb.TABLE_SCHEMA = c.TABLE_SCHEMA AND tb.TABLE_NAME = c.TABLE_NAME
              WHERE c.TABLE_SCHEMA = DATABASE() AND c.COLUMN_NAME = 'job_offer_id' AND tb.TABLE_TYPE = 'BASE TABLE'
              ORDER BY c.TABLE_NAME"
        ))->pluck('t')->reject(fn ($t) => in_array($t, self::OWN_TABLES, true))->values()->all();
    }

    public function deleteStep(JobOfferProcess $p, float $deadline): bool
    {
        $analysisId = (int) $p->params['analysis_id'];
        $state = $p->state ?? [];
        if (! isset($state['init'])) {
            $p->total = DB::table('job_title_exclusion_candidates')->where('process_id', $analysisId)->count();
            $p->cursor_id = 0;
            $p->processed = 0;
            $state = ['init' => 1, 'deleted' => ['job_offers' => 0], 'backed_up' => 0];
        }
        $tables = $this->jobOfferTables();

        while (microtime(true) < $deadline) {
            $ids = DB::table('job_title_exclusion_candidates')->where('process_id', $analysisId)
                ->where('job_offer_id', '>', (int) $p->cursor_id)->orderBy('job_offer_id')
                ->limit(self::DELETE_CHUNK)->pluck('job_offer_id')->map(fn ($id) => (int) $id)->all();
            if ($ids === []) {
                $p->state = $state;
                $p->result = ['analysis_id' => $analysisId, 'deleted' => $state['deleted'],
                    'offers_deleted' => $state['deleted']['job_offers'], 'backup_rows' => $state['backed_up'],
                    'restorable' => true];
                $this->startRankingRefresh($p);

                IndicatorScope::bumpCacheVersion();
                return true;
            }

            DB::transaction(function () use ($ids, $tables, $p, &$state) {
                // Primero las asociaciones (respaldo + borrado), al final las ofertas
                foreach (array_merge($tables, ['job_offers']) as $table) {
                    $key = $table === 'job_offers' ? 'id' : 'job_offer_id';
                    $rows = DB::table($table)->whereIn($key, $ids)->get();
                    if ($rows->isEmpty()) {
                        continue;
                    }
                    $now = now();
                    foreach ($rows->chunk(100) as $chunk) {
                        DB::table('job_offer_deletion_backups')->insert($chunk->map(fn ($r) => [
                            'process_id' => $p->id,
                            'table_name' => $table,
                            'job_offer_id' => $r->{$key},
                            'payload' => json_encode($r, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                            'created_at' => $now,
                        ])->all());
                    }
                    $deleted = DB::table($table)->whereIn($key, $ids)->delete();
                    $state['deleted'][$table] = ($state['deleted'][$table] ?? 0) + $deleted;
                    $state['backed_up'] += $rows->count();
                }
            });

            $p->cursor_id = end($ids);
            $p->processed += count($ids);
            $p->state = $state;
            $p->save();
        }

        return false;
    }

    /** Revierte una eliminación: primero las ofertas, luego sus asociaciones (INSERT IGNORE). */
    public function restoreStep(JobOfferProcess $p, float $deadline): bool
    {
        $deleteId = (int) $p->params['delete_process_id'];
        $state = $p->state ?? [];
        if (! isset($state['init'])) {
            $p->total = DB::table('job_offer_deletion_backups')->where('process_id', $deleteId)->whereNull('restored_at')->count();
            $p->processed = 0;
            $state = ['init' => 1, 'phase' => 'job_offers', 'restored' => []];
        }

        while (microtime(true) < $deadline) {
            $q = DB::table('job_offer_deletion_backups')->where('process_id', $deleteId)->whereNull('restored_at');
            $state['phase'] === 'job_offers' ? $q->where('table_name', 'job_offers') : $q->where('table_name', '<>', 'job_offers');
            $rows = $q->orderBy('id')->limit(300)->get(['id', 'table_name', 'payload']);

            if ($rows->isEmpty()) {
                if ($state['phase'] === 'job_offers') {
                    $state['phase'] = 'children';
                    continue;
                }
                $p->state = $state;
                $p->result = ['delete_process_id' => $deleteId, 'restored' => $state['restored'],
                    'offers_restored' => $state['restored']['job_offers'] ?? 0];
                $delete = JobOfferProcess::find($deleteId);
                if ($delete) {
                    $delete->result = array_merge($delete->result ?? [], ['restorable' => false, 'restored_by_process' => $p->id, 'restored_at' => now()->toDateTimeString()]);
                    $delete->save();
                }
                $this->startRankingRefresh($p);

                IndicatorScope::bumpCacheVersion();
                return true;
            }

            DB::transaction(function () use ($rows, &$state) {
                foreach ($rows->groupBy('table_name') as $table => $group) {
                    $columns = array_flip($this->columns($table));
                    $insert = $group->map(fn ($b) => array_intersect_key(json_decode($b->payload, true) ?? [], $columns))->all();
                    $n = 0;
                    foreach (array_chunk($insert, 100) as $batch) {
                        $n += DB::table($table)->insertOrIgnore($batch);
                    }
                    $state['restored'][$table] = ($state['restored'][$table] ?? 0) + $n;
                }
                DB::table('job_offer_deletion_backups')->whereIn('id', $rows->pluck('id'))->update(['restored_at' => now()]);
            });

            $p->processed += $rows->count();
            $p->state = $state;
            $p->save();
        }

        return false;
    }

    private function columns(string $table): array
    {
        static $cache = [];

        return $cache[$table] ??= \Illuminate\Support\Facades\Schema::getColumnListing($table);
    }

    /* ==================================================
       VISTA PREVIA DE UN PATRÓN (conteo y ejemplos)
    ================================================== */

    /**
     * Recorre los títulos distintos con su número de ofertas, de los más frecuentes a los menos, SIN
     * guardarlos en memoria (consulta no bufferizada: ~200 000 títulos en un arreglo superan los 128 MB
     * del worker). $fn no puede hacer consultas a la base mientras dura el recorrido.
     */
    public function eachTitle(callable $fn): void
    {
        $pdo = DB::connection()->getPdo();
        $buffered = $pdo->getAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
        $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        try {
            $stmt = $pdo->query('SELECT title, COUNT(*) AS n FROM job_offers GROUP BY title ORDER BY n DESC, title');
            while (($r = $stmt->fetch(\PDO::FETCH_NUM)) !== false) {
                $fn((string) $r[0], (int) $r[1]);
            }
            $stmt->closeCursor();
        } finally {
            $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $buffered);
        }
    }

    /**
     * Ofertas cuyo título coincide con el patrón y ejemplos, comparando con la lista ACTIVA actual:
     *   exclude   → cuántas pasaría a excluir (no excluidas hoy y sin excepción que las proteja)
     *   exception → cuántas rescataría (hoy excluidas)
     *   with_ti_word: títulos que además tienen una palabra de TI (riesgo de falso positivo)
     */
    public function preview(string $pattern, string $matchMode, bool $wholeWord, string $type, int $limit = 40, ?int $ignoreKeywordId = null): array
    {
        $active = $ignoreKeywordId === null
            ? TitleExclusionMatcher::active()
            : new TitleExclusionMatcher(DB::table(TitleExclusionMatcher::TABLE)->where('is_active', 1)->where('id', '<>', $ignoreKeywordId)->orderBy('id')->get());

        return $this->previewMany([[$pattern, $matchMode, $wholeWord, $type]], $limit, $active)[0] ?? ['error' => 'Patrón no válido'];
    }

    /**
     * Vista previa de varios patrones en UN recorrido de los títulos (memoria acotada): ejemplos = los
     * más frecuentes, ti_examples = los primeros con palabra de TI, random = muestra (reservorio) del resto.
     *
     * @param  array<int, array{0: string, 1: string, 2: bool, 3: string}>  $specs  [patrón, modo, palabra, tipo]
     * @return array<int, array|null>  null si el patrón no compila
     */
    public function previewMany(array $specs, int $limit, ?TitleExclusionMatcher $active = null): array
    {
        $active ??= TitleExclusionMatcher::active();
        $regex = [];
        $out = [];
        $seen = [];
        foreach ($specs as $i => [$pattern, $mode, $ww, $type]) {
            $regex[$i] = TitleExclusionMatcher::compile($pattern, $mode, (bool) $ww);
            $out[$i] = $regex[$i] === null ? null : ['matches' => 0, 'titles' => 0, 'affected' => 0, 'already_excluded' => 0,
                'with_ti_word' => 0, 'examples' => [], 'ti_examples' => [], 'random' => []];
            $seen[$i] = 0;
        }

        $this->eachTitle(function (string $title, int $n) use ($specs, $regex, $active, $limit, &$out, &$seen) {
            $norm = null;
            $info = null;
            foreach ($regex as $i => $re) {
                if ($re === null) {
                    continue;
                }
                $norm ??= TitleExclusionMatcher::normalize($title);
                if (! preg_match($re, $norm)) {
                    continue;
                }
                if ($info === null) {
                    $by = $active->excludedBy($title);
                    $info = ['by' => $by, 'ti' => TitleExclusionMatcher::hasTiWord($title), 'protected' => $by === null && $active->isProtected($title)];
                }
                $o = &$out[$i];
                $o['matches'] += $n;
                $o['titles']++;
                if ($info['by'] !== null) {
                    $o['already_excluded'] += $n;
                }
                if ($specs[$i][3] === 'exception' ? $info['by'] !== null : ($info['by'] === null && ! $info['protected'])) {
                    $o['affected'] += $n;
                }
                if ($info['ti']) {
                    $o['with_ti_word'] += $n;
                }
                $hit = ['title' => $title, 'offers' => $n, 'ti' => $info['ti'], 'excluded_by' => $info['by']['pattern'] ?? null];
                if (count($o['examples']) < $limit) {
                    $o['examples'][] = $hit;
                } else {
                    // Reservorio: muestra uniforme de 15 entre los menos frecuentes
                    $seen[$i]++;
                    if (count($o['random']) < 15) {
                        $o['random'][] = $hit;
                    } elseif (($j = random_int(0, $seen[$i] - 1)) < 15) {
                        $o['random'][$j] = $hit;
                    }
                }
                if ($info['ti'] && count($o['ti_examples']) < 20) {
                    $o['ti_examples'][] = $hit;
                }
                unset($o);
            }
        });

        return $out;
    }

    /* ==================================================
       SUGERIR CON IA
    ================================================== */

    public function suggest(JobOfferProcess $p): void
    {
        $active = TitleExclusionMatcher::active();
        $keywords = JobTitleExclusionKeyword::orderBy('id')->get();
        $p->processed = 0;
        $p->total = 3;
        $p->save();

        // Muestra: los 250 títulos más frecuentes que hoy no se excluyen, 150 al azar del resto (reservorio)
        // y hasta 120 excluidos con palabra de TI (posibles falsos positivos, para proponer excepciones)
        $sample = [];
        $tail = [];
        $tailSeen = 0;
        $suspicious = [];
        $this->eachTitle(function (string $title, int $n) use ($active, &$sample, &$tail, &$tailSeen, &$suspicious) {
            if ($title === '') {
                return;
            }
            $by = $active->excludedBy($title);
            if ($by === null) {
                if (count($sample) < 250) {
                    $sample[] = [$title, $n];
                } elseif (count($tail) < 150) {
                    $tail[] = [$title, $n];
                    $tailSeen++;
                } elseif (($j = random_int(0, $tailSeen++)) < 150) {
                    $tail[$j] = [$title, $n];
                }
            } elseif (count($suspicious) < 120 && TitleExclusionMatcher::hasTiWord($title)) {
                $suspicious[] = [$title, $n, $by['pattern']];
            }
        });
        $sample = array_merge($sample, $tail);
        $p->processed = 1;
        $p->save();

        $raw = $this->askAi($this->suggestPrompt($keywords, $sample, $suspicious));
        $p->processed = 2;
        $p->save();

        $existing = $keywords->map(fn ($k) => "{$k->type}|{$k->match_mode}|" . (int) $k->whole_word . "|{$k->pattern}")->flip();
        $candidates = [];
        foreach (array_slice($raw['suggestions'] ?? [], 0, 40) as $s) {
            $pattern = trim((string) ($s['pattern'] ?? ''));
            $type = ($s['type'] ?? 'exclude') === 'exception' ? 'exception' : 'exclude';
            $mode = ($s['match_mode'] ?? 'text') === 'regex' ? 'regex' : 'text';
            $ww = (bool) ($s['whole_word'] ?? false);
            if ($mode === 'text') {
                $pattern = TitleExclusionMatcher::normalize($pattern);
            }
            $key = "{$type}|{$mode}|" . (int) $ww . "|{$pattern}";
            if (TitleExclusionMatcher::error($pattern, $mode, $ww) !== null || isset($existing[$key])) {
                continue;
            }
            $existing[$key] = true;
            $candidates[] = compact('pattern', 'type', 'mode', 'ww') + ['category' => $s['category'] ?? '', 'reason' => $s['reason'] ?? ''];
        }

        // Impacto de todas las sugerencias en un solo recorrido de los títulos
        $previews = $this->previewMany(array_map(fn ($c) => [$c['pattern'], $c['mode'], $c['ww'], $c['type']], $candidates), 8, $active);
        $suggestions = [];
        foreach ($candidates as $i => $c) {
            $preview = $previews[$i];
            $suggestions[] = [
                'key' => md5("{$c['type']}|{$c['mode']}|{$c['ww']}|{$c['pattern']}"),
                'pattern' => $c['pattern'],
                'type' => $c['type'],
                'match_mode' => $c['mode'],
                'whole_word' => $c['ww'],
                'category' => mb_substr(trim((string) $c['category']), 0, 60) ?: null,
                'reason' => mb_substr(trim((string) $c['reason']), 0, 300),
                'matches' => $preview['matches'],
                'affected' => $preview['affected'],
                'with_ti_word' => $preview['with_ti_word'],
                'examples' => array_map(fn ($e) => ['title' => $e['title'], 'offers' => $e['offers'], 'ti' => $e['ti']], $preview['examples']),
                'status' => 'pending',
            ];
        }
        usort($suggestions, fn ($a, $b) => $b['affected'] <=> $a['affected']);

        $p->processed = 3;
        $p->result = [
            'model' => config('job_title_exclusions.ai_model'),
            'titles_sent' => count($sample),
            'suspicious_sent' => count($suspicious),
            'received' => count($raw['suggestions'] ?? []),
            'suggestions' => $suggestions,
        ];
    }

    private function suggestPrompt($keywords, array $titles, array $suspicious): string
    {
        $list = $keywords->map(fn ($k) => sprintf('%s | %s | %s%s | %s%s', $k->type, $k->match_mode, $k->pattern,
            $k->whole_word ? ' (palabra completa)' : '', $k->category ?? '-', $k->is_active ? '' : ' | INACTIVO'))->implode("\n");
        $sample = collect($titles)->map(fn ($t) => "{$t[1]}\t{$t[0]}")->implode("\n");
        $susp = collect($suspicious)->map(fn ($t) => "{$t[1]}\t{$t[0]}\t[excluido por: {$t[2]}]")->implode("\n");
        $ti = implode(', ', array_slice(config('job_title_exclusions.ti_patterns', []), 0, 80));

        return <<<PROMPT
Eres analista de datos de un observatorio del mercado laboral de TECNOLOGÍAS DE LA INFORMACIÓN. Los indicadores
(lenguajes de programación, tecnologías y certificaciones más demandados) se calculan con ofertas de empleo de
muchos países e idiomas (español, inglés, portugués, alemán, francés…). Hay que descartar las ofertas cuyo TÍTULO
corresponde a un rol que NO es de TI (ventas, marketing, call center, salud, educación, logística, oficios, otras
ingenierías…), aunque los requisitos mencionen Java, Excel u otra tecnología.

Los títulos se comparan en minúsculas y SIN tildes. Cada patrón es:
  - type: "exclude" (rol no TI) o "exception" (protege títulos de TI que una exclusión atrapa por error; gana sobre las exclusiones)
  - match_mode: "text" (texto literal, PREFERIDO) o "regex" (expresión regular PCRE; \b = borde de palabra)
  - whole_word: true si el texto debe ser una palabra completa (úsalo en palabras cortas o ambiguas)

LISTA ACTUAL (type | match_mode | patrón | categoría):
{$list}

Palabras que indican un rol de TI (referencia; NO propongas exclusiones que atrapen títulos con ellas): {$ti}

TÍTULOS FRECUENTES QUE HOY NO SE EXCLUYEN (ofertas<TAB>título):
{$sample}

TÍTULOS QUE HOY SE EXCLUYEN Y TIENEN UNA PALABRA DE TI (posibles falsos positivos; ofertas<TAB>título<TAB>patrón):
{$susp}

TAREA
1. Propón patrones "exclude" NUEVOS para roles claramente no TI que aparecen en los títulos que hoy no se excluyen
   (en cualquier idioma). Cada patrón debe ser preciso: que no atrape roles de TI.
2. Propón "exception" para los falsos positivos reales de la segunda lista (por ejemplo "salesforce", "marketing technology",
   "clinical data", "manufacturing systems engineer"), sin proteger roles que de verdad no son de TI.
3. No repitas patrones de la lista actual. Máximo 30 sugerencias en total. Escribe los patrones en minúsculas y sin tildes.
4. category: una de ventas, marketing, recursos humanos, contabilidad, atención al cliente, digitación, finanzas, legal,
   administración, salud, educación, transporte y logística, operarios y oficios, comercio y servicios, otras ingenierías, otros
   (para "exception" usa la categoría del patrón que corrige).

Responde SOLO con JSON válido, sin texto adicional:
{"suggestions":[{"pattern":"...","type":"exclude|exception","match_mode":"text|regex","whole_word":true,"category":"...","reason":"motivo breve en español"}]}
PROMPT;
    }

    /** Misma llamada que DiscoverLanguageGapsCommand::gptSearch() (OpenAI, JSON). */
    private function askAi(string $prompt): array
    {
        $apiKey = config('services.openai.key');
        if (! $apiKey) {
            throw new \RuntimeException('OPENAI_API_KEY no configurada');
        }

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('job_title_exclusions.ai_model', 'gpt-4.1-mini'),
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a strict job-title classifier for an IT labor-market observatory. You respond only with valid JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Error de la IA: ' . mb_substr($response->body(), 0, 500));
        }

        $text = trim(preg_replace('/```json|```/i', '', (string) data_get($response->json(), 'choices.0.message.content', '')));
        if (preg_match('/\{.*\}/s', $text, $m)) {
            return json_decode($m[0], true) ?? [];
        }
        Log::warning('[TITLE_EXCLUSION] La IA no devolvió JSON', ['text' => mb_substr($text, 0, 500)]);

        return [];
    }

    /* ==================================================
       UTILIDADES
    ================================================== */

    /**
     * Encola el recálculo de los tres rankings, un Job por indicador y semestre (cada uno < 90 s; el
     * recálculo completo de Tecnologías tarda ~2 min y superaría el retry_after de la cola).
     */
    public function startRankingRefresh(?JobOfferProcess $p): void
    {
        $steps = [];
        foreach (self::RANKING_TYPES as $type) {
            foreach (EntityRankingService::for($type)->periods() as [$y, $s]) {
                $steps[] = [$type, $y, $s];
            }
        }
        if ($p) {
            $p->result = array_merge($p->result ?? [], ['rankings' => ['status' => 'queued', 'done' => 0, 'total' => count($steps)]]);
        }
        // 5 s de retraso: el Job que lo lanza guarda antes su resultado (la cadena escribe en él)
        RefreshRankingsChainJob::dispatch($p?->id, $steps)->delay(now()->addSeconds(5));
    }

    /** Marca que la lista cambió (la interfaz avisa que falta aplicar). */
    public static function touchList(): void
    {
        Cache::forever(self::LIST_CHANGED_KEY, now()->toDateTimeString());
    }

    /** ¿La lista cambió después del último "aplicar" terminado? */
    public static function pendingChanges(): bool
    {
        $changed = Cache::get(self::LIST_CHANGED_KEY);
        $last = JobOfferProcess::where('type', 'apply')->where('status', 'done')
            ->latest('id')->limit(20)->get()
            ->first(fn ($p) => empty($p->params['dry_run']));

        if ($last === null) {
            return true;
        }

        return $changed !== null && $changed >= ($last->result['list_at'] ?? $last->created_at->toDateTimeString());
    }

    /** Resumen para la cabecera de la pantalla. */
    public function summary(): array
    {
        $hasColumn = JobOffer::hasTitleExclusionColumn();

        return [
            'total_offers' => DB::table('job_offers')->count(),
            'excluded_offers' => $hasColumn ? DB::table('job_offers')->whereNotNull('title_exclusion_id')->count() : 0,
            'has_column' => $hasColumn,
            'pending_changes' => self::pendingChanges(),
            'association_exclusions_active' => ExclusionSettings::active(),
        ];
    }
}
