<?php

namespace App\Services\Ranking;

use App\Http\Controllers\Dashboard\JobMarketStatusController;
use App\Models\JobOffer;
use App\Models\Prueba;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

/**
 * Ranking genérico de entidades de mercado (lenguajes, tecnologías, …): cálculo
 * (comando, Job y botón "Recalcular") y lectura (página, modal de evolución y export).
 * Cada indicador es una subclase que fija $type; la configuración (entity_type, tabla
 * pivote, tablas resumen, ponderaciones, tabla de carreras, lock y log) está en
 * config/entity_rankings.php. Nació de LanguageRankingService sin cambiar su lógica:
 * la "foto dorada" de Lenguajes da 0 diferencias antes y después del refactor.
 *
 * El cálculo replica la fórmula original del controlador:
 *   labor_score = LOG(ofertas+1)  / LOG(max_ofertas+1)  * 100
 *   trend_score = LOG(reportes+1) / LOG(max_reportes+1) * 100
 *   final_score = labor_score * peso_laboral + trend_score * peso_tendencias
 *
 * Los pesos se aplican AL LEER, así que un cambio de ponderaciones se ve al instante
 * sin recalcular. final_score/position quedan guardados solo como referencia.
 */
abstract class EntityRankingService
{
    /** Si el proceso muere, el lock se libera solo pasado este tiempo. */
    public const LOCK_SECONDS = 900;

    public const FIRST_YEAR = 2024;

    public const WINDOW_TYPES = ['weekly', 'biweekly', 'monthly'];

    private const MONTHS_SHORT = [1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    private const MONTHS_LONG = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    private const DEFAULT_LABOR_WEIGHT = 0.7;
    private const DEFAULT_TREND_WEIGHT = 0.3;

    /* ==================================================
       CONFIGURACIÓN POR TIPO (config/entity_rankings.php)
    ================================================== */

    /** Clave del indicador en config/entity_rankings.php: 'languages', 'technologies', … */
    protected string $type;

    /** Servicio del indicador indicado ('languages', 'technologies', …). */
    public static function for(string $type): self
    {
        $class = config("entity_rankings.{$type}.service");

        if (! $class) {
            throw new \InvalidArgumentException("Indicador de ranking desconocido: {$type}");
        }

        return app($class);
    }

    public function type(): string
    {
        return $this->type;
    }

    public function config(string $key): mixed
    {
        $value = config("entity_rankings.{$this->type}.{$key}");

        if ($value === null) {
            throw new \RuntimeException("Falta entity_rankings.{$this->type}.{$key}");
        }

        return $value;
    }

    /** market_entities.entity_type del indicador ('language', 'technology', …). */
    public function entityType(): string
    {
        return $this->config('entity_type');
    }

    /** Tabla pivote oferta↔entidad (language_job, technology_job, …). */
    public function pivotTable(): string
    {
        return $this->config('pivot_table');
    }

    /** Tablas resumen: rankings, meta, windows, windows_meta. */
    public function table(string $which): string
    {
        return $this->config("tables.{$which}");
    }

    public function lockName(): string
    {
        return $this->config('lock_name');
    }

    public function statusKey(): string
    {
        return $this->lockName() . ':status';
    }

    public function logPrefix(): string
    {
        return $this->config('log_prefix');
    }

    /** Etiqueta en español para mensajes ("lenguajes", "tecnologías"). */
    public function label(): string
    {
        return $this->config('label');
    }

    /**
     * Condición de "entidad activa del indicador" para unir market_entities (alias me).
     * market_entities.is_active es la única fuente de verdad (ver MarketEntity::scopeActive).
     * entity_type sale de la configuración (no de la petición), así que es seguro interpolarlo.
     */
    protected function activeJoin(): string
    {
        return "AND me.entity_type = '{$this->entityType()}' AND me.is_active = 1";
    }

    /**
     * Ids de las entidades activas del indicador, para filtrar la tabla pivote con
     * "market_entity_id IN (…)". Son enteros leídos de la BD (seguro interpolarlos);
     * '0' si no hay ninguna, para que el IN siga siendo válido.
     */
    public function activeIdsSql(): string
    {
        $ids = DB::table('market_entities')
            ->where('entity_type', $this->entityType())
            ->where('is_active', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->implode(',');

        return $ids !== '' ? $ids : '0';
    }

    /**
     * Filtro de asociaciones excluidas (entity_association_exclusions, que llena
     * entities:exclude-associations): los pares (oferta, entidad) que el matcher no confirma no
     * cuentan, sin borrar nada de la tabla pivote. Activo por defecto; apagado de emergencia con
     * php artisan entities:exclusions --disable (flag en entity_association_exclusion_settings).
     * Se omite solo si la tabla no existe. Afecta al ranking por semestre, a la evolución y a las
     * vacantes únicas (salen de la misma consulta). Nombres de tabla constantes: seguro interpolarlos.
     */
    public function exclusionSql(): string
    {
        if (! ExclusionSettings::active()) {
            return '';
        }
        $table = ExclusionSettings::TABLE;

        return " AND NOT EXISTS (SELECT 1 FROM `{$table}` x WHERE x.pivot_table = '{$this->pivotTable()}'"
            . ' AND x.job_offer_id = lj.job_offer_id AND x.market_entity_id = lj.market_entity_id AND x.vigente = 1)';
    }

    /**
     * Ofertas excluidas por título (rol no TI, job_offers.title_exclusion_id, que marca
     * job-offers:apply-title-exclusions): no cuentan en el ranking, la evolución ni las vacantes
     * analizadas. No se borra nada. Se omite si la columna aún no existe.
     */
    public static function titleExclusionSql(string $alias = 'j'): string
    {
        return JobOffer::hasTitleExclusionColumn() ? " AND {$alias}.title_exclusion_id IS NULL" : '';
    }

    /* ==================================================
       RECÁLCULO
    ================================================== */

    /**
     * Recalcula con el lock tomado. Devuelve null si ya hay un recálculo en curso.
     *
     * @return array{periods: array<int, array>, ok: bool, duration_ms: int}|null
     */
    public function refreshWithLock(?int $year = null, ?string $period = null, ?callable $progress = null): ?array
    {
        $lock = Cache::lock($this->lockName(), self::LOCK_SECONDS);

        if (! $lock->get()) {
            Log::info($this->logPrefix() . ' Recálculo omitido: ya hay uno en curso.');

            return null;
        }

        $this->setStatus('running');

        try {
            $result = $this->refresh($year, $period, $progress);

            $this->setStatus(
                $result['ok'] ? 'done' : 'failed',
                $result['ok'] ? null : 'Falló el cálculo de uno o más periodos. Revisa el log.'
            );

            return $result;
        } catch (\Throwable $e) {
            $this->setStatus('failed', $e->getMessage());
            throw $e;
        } finally {
            $lock->release();
        }
    }

    /**
     * Recalcula los semestres pedidos (por defecto, todos desde 2024) y su evolución,
     * y al final precalienta la caché de JobMarketStatus.
     *
     * UN RECORRIDO POR SEMESTRE: una consulta agregada por día y entidad (con
     * WITH ROLLUP para las vacantes únicas del día) y otra de reportes por día de alta.
     * De esos datos salen, por sumas prefijas:
     *   - el ranking del semestre (vacantes = suma de los conteos diarios del semestre;
     *     cada oferta tiene una sola fecha, así que es exacto), y
     *   - las fotos de evolución cuyo corte cae en ese semestre.
     * La memoria depende de un semestre, no del histórico completo.
     *
     * Transacciones: el ranking de cada semestre y sus fotos se reemplazan cada uno en
     * su propia transacción (borrar + insertar). Si algo falla se revierte solo eso y
     * se conservan los datos anteriores; quien lea durante el cálculo nunca ve la tabla
     * vacía ni a medias.
     *
     * Sin --year/--period se reconstruye toda la evolución; con ellos, solo las fotos
     * de los semestres pedidos (cada foto solo depende de su semestre).
     */
    public function refresh(?int $year = null, ?string $period = null, ?callable $progress = null): array
    {
        $start = microtime(true);
        [$laborWeight, $trendWeight] = $this->activeWeights();
        $periods = $this->periods($year, $period);
        $requested = array_flip(array_map(fn ($p) => "{$p[0]}-{$p[1]}", $periods));
        $allWindows = $year === null && $period === null;

        $today = now()->startOfDay();
        $plan = $this->windowPlan($today);

        Log::info($this->logPrefix() . ' Inicio del recálculo', [
            'periods' => array_keys($requested),
            // true = se ignoran las asociaciones de entity_association_exclusions
            'exclusiones' => $this->exclusionSql() !== '',
            // true = se ignoran las ofertas excluidas por título (job_offers.title_exclusion_id)
            'exclusion_titulos' => self::titleExclusionSql() !== '',
        ]);

        $entities = DB::table('market_entities')
            ->where('entity_type', $this->entityType())
            ->where('is_active', 1)
            ->get(['id', 'name', 'has_isil', 'has_trend', 'created_at'])
            ->keyBy('id');
        $activeIds = $this->activeIdsSql();

        $results = [];
        $windows = ['ok' => true, 'windows' => 0, 'rows' => 0, 'duration_ms' => 0];

        foreach ($this->periods() as [$y, $p]) {
            $key = "{$y}-{$p}";
            $wantPeriod = isset($requested[$key]);
            $semWindows = ($allWindows || $wantPeriod) ? ($plan[$key] ?? []) : [];

            if (! $wantPeriod && empty($semWindows)) {
                continue;
            }

            $semStart = microtime(true);

            try {
                $data = $this->loadSemester($y, $p, $semWindows, $activeIds, $today);
            } catch (\Throwable $e) {
                Log::error($this->logPrefix() . ' Error leyendo el semestre; se conservan los datos anteriores', [
                    'semester' => $key,
                    'error' => $e->getMessage(),
                ]);
                if ($wantPeriod) {
                    $results[] = ['year' => $y, 'period' => $p, 'ok' => false, 'error' => $e->getMessage()];
                    $progress && $progress(end($results));
                }
                if ($semWindows) {
                    $windows['ok'] = false;
                    $windows['error'] = $e->getMessage();
                }
                continue;
            }

            if ($wantPeriod) {
                try {
                    $results[] = $this->writePeriod($y, $p, $data, $entities, $laborWeight, $trendWeight, $semStart);
                } catch (\Throwable $e) {
                    Log::error($this->logPrefix() . ' Error en el periodo; se conservan los datos anteriores', [
                        'year' => $y,
                        'period' => $p,
                        'error' => $e->getMessage(),
                    ]);
                    $results[] = ['year' => $y, 'period' => $p, 'ok' => false, 'error' => $e->getMessage()];
                }
                $progress && $progress(end($results));
            }

            if ($semWindows) {
                $wStart = microtime(true);
                try {
                    [$w, $r] = $this->writeWindows($key, $semWindows, $data, $entities, $laborWeight, $trendWeight, $today);
                    $windows['windows'] += $w;
                    $windows['rows'] += $r;
                } catch (\Throwable $e) {
                    Log::error($this->logPrefix() . ' Error en la evolución del semestre; se conservan sus fotos anteriores', [
                        'semester' => $key,
                        'error' => $e->getMessage(),
                    ]);
                    $windows['ok'] = false;
                    $windows['error'] = $e->getMessage();
                }
                $windows['duration_ms'] += $this->msSince($wStart);
            }

            unset($data);
        }

        Log::info($this->logPrefix() . ' Evolución recalculada', $windows);

        if ($progress) {
            $progress(['step' => 'windows'] + $windows);
        }

        try {
            $warmStart = microtime(true);
            JobMarketStatusController::warm($periods);
            Log::info($this->logPrefix() . ' Caché de JobMarketStatus precalentada', [
                'duration_ms' => $this->msSince($warmStart),
            ]);
        } catch (\Throwable $e) {
            // El ranking ya quedó guardado; la caché se llenará en la próxima visita.
            Log::warning($this->logPrefix() . ' No se pudo precalentar JobMarketStatus', [
                'error' => $e->getMessage(),
            ]);
        }

        $ok = collect($results)->every(fn ($r) => $r['ok']) && $windows['ok'];
        $duration = $this->msSince($start);

        Log::info($this->logPrefix() . ' Fin del recálculo', [
            'ok' => $ok,
            'periods' => count($results),
            'rows' => collect($results)->sum(fn ($r) => $r['rows'] ?? 0),
            'window_rows' => $windows['rows'],
            'duration_ms' => $duration,
        ]);

        return ['periods' => $results, 'windows' => $windows, 'ok' => $ok, 'duration_ms' => $duration];
    }

    /**
     * Pares [año, semestre] a recalcular.
     *
     * @return array<int, array{0:int,1:string}>
     */
    public function periods(?int $year = null, ?string $period = null): array
    {
        $years = $year ? [$year] : range(self::FIRST_YEAR, (int) now()->year);
        $semesters = $period ? [$period] : ['s1', 's2'];

        $pairs = [];
        foreach ($years as $y) {
            foreach ($semesters as $s) {
                $pairs[] = [$y, $s];
            }
        }

        return $pairs;
    }

    /* ==================================================
       DATOS DE UN SEMESTRE (un solo recorrido)
    ================================================== */

    /**
     * Índice de día global: 0 = 1-ene del primer año (FIRST_YEAR).
     */
    private function dayIndex(string $date): int
    {
        static $base = null;
        static $cache = [];
        $base ??= Carbon::create(self::FIRST_YEAR, 1, 1)->startOfDay();

        return $cache[$date] ??= (int) round($base->diffInDays(Carbon::parse($date)->startOfDay(), false));
    }

    /**
     * Lee el semestre una vez y prepara sumas prefijas por día:
     *   - pref_labor[e][i]: ofertas de la entidad e desde $from hasta el día local i;
     *   - pref_unique[i]:   vacantes únicas (con al menos una entidad activa) idem;
     *   - trend_days/trend_cum[e]: reportes del semestre acumulados por día de alta.
     * El rango va desde el inicio del semestre (o el de la semana más temprana cuyo corte
     * cae en él: sus primeros días cuentan como "nuevas" de esa semana) hasta el fin del
     * semestre, para que el total del semestre sea exacto.
     *
     * Zona horaria: se agrupa por DATE(published_at) en la zona de la sesión por
     * defecto, la misma con la que Laravel escribe (hora de pared de America/Lima).
     */
    private function loadSemester(int $year, string $period, array $windows, string $activeIds, Carbon $today): array
    {
        $semStart = $period === 's1' ? "{$year}-01-01" : "{$year}-07-01";
        $semEndExclusive = $period === 's1' ? "{$year}-07-01" : ($year + 1) . '-01-01';
        $from = max(self::FIRST_YEAR . '-01-01', min(array_merge([$semStart], array_column($windows, 'start'))));

        $base = $this->dayIndex($from);
        $len = $this->dayIndex($semEndExclusive) - $base;
        $todayIdx = $this->dayIndex($today->toDateString());

        // Por día y entidad; WITH ROLLUP agrega el subtotal (día, NULL) = vacantes únicas
        // del día (una oferta con 3 entidades cuenta 1 vez). El IN (ids activos) filtra
        // igual que un JOIN a market_entities pero es 2-3 veces más rápido, y garantiza
        // que no haya market_entity_id nulos que se confundan con los subtotales.
        $rows = DB::select(
            'SELECT STRAIGHT_JOIN DATE(j.published_at) AS d, lj.market_entity_id AS entity_id,
                    COUNT(DISTINCT lj.job_offer_id) AS offers
               FROM job_offers j
               JOIN ' . $this->pivotTable() . ' lj ON lj.job_offer_id = j.id
              WHERE j.published_at >= ? AND j.published_at < ?' . self::titleExclusionSql() . '
                AND lj.market_entity_id IN (' . $activeIds . ')' . $this->exclusionSql() . '
              GROUP BY d, lj.market_entity_id WITH ROLLUP',
            [$from, $semEndExclusive]
        );

        $zeros = array_fill(0, $len, 0);
        $prefLabor = [];
        $prefUnique = $zeros;
        foreach ($rows as $r) {
            if ($r->d === null) {
                continue;                                   // total general
            }
            $i = $this->dayIndex($r->d) - $base;
            if ($i < 0 || $i >= $len) {
                continue;
            }
            if ($r->entity_id === null) {
                $prefUnique[$i] += (int) $r->offers;         // subtotal del día
            } else {
                $prefLabor[(int) $r->entity_id] ??= $zeros;
                $prefLabor[(int) $r->entity_id][$i] += (int) $r->offers;
            }
        }
        unset($rows);
        foreach ($prefLabor as $e => $arr) {
            for ($i = 1; $i < $len; $i++) {
                $arr[$i] += $arr[$i - 1];
            }
            $prefLabor[$e] = $arr;
        }
        for ($i = 1; $i < $len; $i++) {
            $prefUnique[$i] += $prefUnique[$i - 1];
        }

        // Reportes del semestre (year + trimestres, como el ranking principal) por día de
        // alta. Índice global; alta anterior a 2024 o sin fecha cuenta desde el primer
        // día, posterior a hoy desde hoy (para que el corte de hoy coincida con el ranking
        // del semestre, que no mira created_at).
        $trendRows = DB::select(
            'SELECT et.market_entity_id AS entity_id, DATE(et.created_at) AS d, COUNT(DISTINCT et.id) AS reports
               FROM entity_trends et
               JOIN market_entities me ON me.id = et.market_entity_id ' . $this->activeJoin() . '
              WHERE et.year = ? AND et.quarter IN (' . ($period === 's1' ? '1, 2' : '3, 4') . ')
              GROUP BY et.market_entity_id, d',
            [$year]
        );
        $byDay = [];
        foreach ($trendRows as $r) {
            $i = $r->d === null ? 0 : max(0, min($todayIdx, $this->dayIndex($r->d)));
            $byDay[(int) $r->entity_id][$i] = ($byDay[(int) $r->entity_id][$i] ?? 0) + (int) $r->reports;
        }
        $trendDays = [];
        $trendCum = [];
        foreach ($byDay as $e => $days) {
            ksort($days);
            $acc = 0;
            foreach ($days as $i => $n) {
                $acc += $n;
                $trendDays[$e][] = $i;
                $trendCum[$e][] = $acc;
            }
        }

        return [
            'base' => $base,
            'len' => $len,
            'sem_start_idx' => $this->dayIndex($semStart),
            'pref_labor' => $prefLabor,
            'pref_unique' => $prefUnique,
            'trend_days' => $trendDays,
            'trend_cum' => $trendCum,
        ];
    }

    /** Suma de un arreglo de sumas prefijas entre los índices locales $a y $b (inclusive). */
    private static function rangeSum(array $pref, int $a, int $b): int
    {
        return $b < $a ? 0 : $pref[$b] - ($a > 0 ? $pref[$a - 1] : 0);
    }

    /** Reportes de la entidad $e disponibles hasta el día global $iCut (búsqueda binaria). */
    private static function reportsUntil(array $data, int $e, int $iCut): int
    {
        $keys = $data['trend_days'][$e] ?? null;
        if ($keys === null || $keys[0] > $iCut) {
            return 0;
        }
        [$lo, $hi] = [0, count($keys) - 1];
        while ($lo < $hi) {                                  // último día <= corte
            $mid = intdiv($lo + $hi + 1, 2);
            $keys[$mid] <= $iCut ? $lo = $mid : $hi = $mid - 1;
        }

        return $data['trend_cum'][$e][$lo];
    }

    /* ==================================================
       RANKING DEL SEMESTRE
    ================================================== */

    private function writePeriod(int $year, string $period, array $data, $entities, float $laborWeight, float $trendWeight, float $start): array
    {
        /* ---------- 1. LABORAL y 2. TENDENCIAS: totales del semestre ----------
           Solo cuentan entidades activas del indicador (market_entities.is_active): las
           ofertas sin entidad o de entidades inactivas no entran ni en el máximo ni en
           las vacantes analizadas. */
        $iS = $data['sem_start_idx'] - $data['base'];
        $iE = $data['len'] - 1;

        $offersByEntity = [];
        $maxLabor = 1;
        foreach ($data['pref_labor'] as $e => $pref) {
            $offers = self::rangeSum($pref, $iS, $iE);
            if ($offers > 0) {
                $offersByEntity[$e] = $offers;
                $maxLabor = max($maxLabor, $offers);
            }
        }
        $vacantes = self::rangeSum($data['pref_unique'], $iS, $iE);

        $reportsByEntity = [];
        foreach ($data['trend_cum'] as $e => $cum) {
            $reportsByEntity[$e] = end($cum);
        }
        $maxTrend = max(1, ...array_values($reportsByEntity ?: [0]));
        $totalReports = array_sum($reportsByEntity);

        /* ---------- 3. FILAS DEL RANKING ---------- */
        $now = now();
        $logLabor = log($maxLabor + 1);
        $logTrend = log($maxTrend + 1);

        $rows = $entities
            ->map(function ($me) use ($offersByEntity, $reportsByEntity, $logLabor, $logTrend, $laborWeight, $trendWeight, $year, $period, $now) {
                $jobs = $offersByEntity[$me->id] ?? 0;
                $reports = $reportsByEntity[$me->id] ?? 0;
                $labor = log($jobs + 1) / $logLabor * 100;
                $trend = log($reports + 1) / $logTrend * 100;

                return [
                    'year' => $year,
                    'period' => $period,
                    'market_entity_id' => $me->id,
                    'name' => $me->name,
                    'has_isil' => (bool) $me->has_isil,
                    'has_trend' => (bool) $me->has_trend,
                    'classification' => $this->classification((bool) $me->has_isil, (bool) $me->has_trend),
                    'total_jobs' => $jobs,
                    'trend_reports' => $reports,
                    'labor_score' => round($labor, 4),
                    'trend_score' => round($trend, 4),
                    'final_score' => round($labor * $laborWeight + $trend * $trendWeight, 4),
                    'calculated_at' => $now,
                ];
            })
            ->sortBy([['final_score', 'desc'], ['total_jobs', 'desc'], ['name', 'asc']])
            ->values()
            ->map(fn ($row, $i) => $row + ['position' => $i + 1])
            ->all();

        /* ---------- 4. REEMPLAZO ATÓMICO ----------
           Borrar + insertar en una transacción: quien lea mientras tanto sigue
           viendo los datos anteriores completos (InnoDB, lectura consistente). */
        DB::transaction(function () use ($rows, $year, $period, $vacantes, $totalReports, $maxLabor, $maxTrend, $laborWeight, $trendWeight, $now, $start) {
            DB::table($this->table('rankings'))
                ->where('year', $year)
                ->where('period', $period)
                ->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table($this->table('rankings'))->insert($chunk);
            }

            DB::table($this->table('meta'))->updateOrInsert(
                ['year' => $year, 'period' => $period],
                [
                    'vacantes_analizadas' => $vacantes,
                    'reportes_analizados' => $totalReports,
                    'max_labor' => $maxLabor,
                    'max_trend' => $maxTrend,
                    'labor_weight' => $laborWeight,
                    'trend_weight' => $trendWeight,
                    'rows_count' => count($rows),
                    'duration_ms' => $this->msSince($start),
                    'calculated_at' => $now,
                ]
            );
        });

        $result = [
            'year' => $year,
            'period' => $period,
            'ok' => true,
            'rows' => count($rows),
            'vacantes' => $vacantes,
            'reportes' => $totalReports,
            'duration_ms' => $this->msSince($start),
        ];

        Log::info($this->logPrefix() . ' Periodo recalculado', $result);

        return $result;
    }

    /* ==================================================
       EVOLUCIÓN: fotos del ranking semestral en cada corte
    ================================================== */

    /**
     * Ventanas (semana ISO lunes-domingo, quincena 1-15 / 16-fin, mes) desde 2024 hasta
     * hoy, agrupadas por el semestre de su fecha de corte (= último día de la ventana, o
     * hoy si está en curso). is_semester_start marca la primera foto de un semestre
     * (el acumulado se reinicia).
     *
     * @return array<string, array<int, array>>  '2026-s2' => [ventanas…]
     */
    private function windowPlan(Carbon $today): array
    {
        $todayStr = $today->toDateString();
        $firstDay = Carbon::create(self::FIRST_YEAR, 1, 1)->startOfDay();
        $plan = [];

        foreach (self::WINDOW_TYPES as $type) {
            $previousSemester = null;

            foreach ($this->calendarWindows($type, $firstDay, $today) as $w) {
                $cutoff = min($w['end'], $todayStr);
                $cut = Carbon::parse($cutoff);
                $semester = $cut->year . '-' . ($cut->month <= 6 ? 's1' : 's2');

                $plan[$semester][] = $w + [
                    'type' => $type,
                    'cutoff' => $cutoff,
                    'semester' => $semester,
                    'semester_start' => Carbon::create($cut->year, $cut->month <= 6 ? 1 : 7, 1)->toDateString(),
                    'is_semester_start' => $previousSemester !== null && $previousSemester !== $semester,
                ];

                $previousSemester = $semester;
            }
        }

        return $plan;
    }

    /**
     * Reemplaza (en una transacción) las fotos de un semestre. Cada foto:
     *   - vacantes acumuladas desde el inicio del semestre hasta el corte;
     *   - reportes del semestre disponibles hasta el corte (entity_trends.created_at);
     *   - labor_score / trend_score normalizados con los máximos de ese corte, con la
     *     misma fórmula que writePeriod(); final_score y position se calculan al leer
     *     con los pesos vigentes (aquí quedan como referencia).
     *
     * @return array{0: int, 1: int}  [fotos, filas]
     */
    private function writeWindows(string $semester, array $windows, array $data, $entities, float $laborWeight, float $trendWeight, Carbon $today): array
    {
        $todayStr = $today->toDateString();
        $base = $data['base'];
        $now = now();
        $windowsCount = 0;
        $rowsCount = 0;

        DB::transaction(function () use ($semester, $windows, $data, $entities, $laborWeight, $trendWeight, $todayStr, $base, $now, &$windowsCount, &$rowsCount) {
            DB::table($this->table('windows'))->where('semester', $semester)->delete();
            DB::table($this->table('windows_meta'))->where('semester', $semester)->delete();

            foreach ($windows as $w) {
                $cutoff = $w['cutoff'];
                $iCutGlobal = $this->dayIndex($cutoff);
                $iCut = $iCutGlobal - $base;
                $iSem = $this->dayIndex($w['semester_start']) - $base;
                $iWin = max(0, $this->dayIndex($w['start']) - $base);

                // Acumulados del semestre al corte, por entidad
                $snap = [];
                foreach ($entities as $e) {
                    $pref = $data['pref_labor'][$e->id] ?? null;
                    $jobs = $pref ? self::rangeSum($pref, $iSem, $iCut) : 0;
                    $newJobs = $pref ? self::rangeSum($pref, $iWin, $iCut) : 0;
                    $reports = self::reportsUntil($data, (int) $e->id, $iCutGlobal);

                    // Una entidad entra en la foto desde que existe o desde que tiene actividad
                    $exists = $e->created_at === null || substr($e->created_at, 0, 10) <= $cutoff;
                    if (! $exists && $jobs === 0 && $reports === 0) {
                        continue;
                    }

                    $snap[$e->id] = ['jobs' => $jobs, 'new_jobs' => $newJobs, 'reports' => $reports];
                }

                $maxLabor = max(1, ...(array_column($snap, 'jobs') ?: [0]));
                $maxTrend = max(1, ...(array_column($snap, 'reports') ?: [0]));
                $logLabor = log($maxLabor + 1);
                $logTrend = log($maxTrend + 1);

                // Puntajes (misma fórmula que writePeriod) y posición de referencia
                $scored = [];
                foreach ($snap as $id => $s) {
                    $labor = log($s['jobs'] + 1) / $logLabor * 100;
                    $trend = log($s['reports'] + 1) / $logTrend * 100;
                    $scored[] = [
                        'id' => $id,
                        'name' => $entities[$id]->name,
                        'labor' => round($labor, 4),
                        'trend' => round($trend, 4),
                        'final' => round($labor * $laborWeight + $trend * $trendWeight, 4),
                    ] + $s;
                }
                usort($scored, fn ($a, $b) => [$b['final'], $b['jobs'], mb_strtolower($a['name'])] <=> [$a['final'], $a['jobs'], mb_strtolower($b['name'])]);

                DB::table($this->table('windows_meta'))->insert([
                    'period_type' => $w['type'],
                    'period_key' => $w['key'],
                    'year' => $w['year'],
                    'label' => $w['label'],
                    'start_date' => $w['start'],
                    'end_date' => $w['end'],
                    'cutoff_date' => $cutoff,
                    'semester' => $semester,
                    'is_semester_start' => $w['is_semester_start'],
                    'is_partial' => $w['end'] > $todayStr,
                    'unique_offers' => self::rangeSum($data['pref_unique'], $iSem, $iCut),
                    'new_unique_offers' => self::rangeSum($data['pref_unique'], $iWin, $iCut),
                    'max_labor' => $maxLabor,
                    'max_trend' => $maxTrend,
                    $this->config('windows_count_column') => count($scored),
                    'calculated_at' => $now,
                ]);

                $rows = [];
                foreach ($scored as $pos => $s) {
                    $rows[] = [
                        'period_type' => $w['type'],
                        'period_key' => $w['key'],
                        'year' => $w['year'],
                        'cutoff_date' => $cutoff,
                        'semester' => $semester,
                        'market_entity_id' => $s['id'],
                        'name' => $s['name'],
                        'jobs' => $s['jobs'],
                        'new_jobs' => $s['new_jobs'],
                        'trend_reports' => $s['reports'],
                        'labor_score' => $s['labor'],
                        'trend_score' => $s['trend'],
                        'final_score' => $s['final'],
                        'position' => $pos + 1,
                        'calculated_at' => $now,
                    ];
                }
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table($this->table('windows'))->insert($chunk);
                }

                $windowsCount++;
                $rowsCount += count($rows);
            }
        });

        return [$windowsCount, $rowsCount];
    }

    /* ==================================================
       LECTURA (página)
    ================================================== */

    /** Fila de language_ranking_meta del periodo, o null si nunca se calculó. */
    public function meta(int $year, string $period): ?object
    {
        return DB::table($this->table('meta'))
            ->where('year', $year)
            ->where('period', $period)
            ->first();
    }

    /**
     * Ranking paginado del periodo con los pesos vigentes.
     * position es la posición global del periodo (antes de filtros de carrera/búsqueda).
     */
    public function paginate(int $year, string $period, float $laborWeight, float $trendWeight, array $careers = [], ?string $search = null, int $perPage = 10, array $categories = []): LengthAwarePaginator
    {
        // Los pesos se validan como numéricos (0..1) al guardarlos; se pasan como bindings.
        $finalExpr = '(lr.labor_score * ? + lr.trend_score * ?)';

        // El join con market_entities aplica el filtro de activos también al leer:
        // si se desactiva un lenguaje, desaparece al instante sin esperar al recálculo.
        $ranked = DB::table($this->table('rankings') . ' as lr')
            ->join('market_entities as me', function ($j) {
                $j->on('me.id', '=', 'lr.market_entity_id')->where('me.is_active', 1);
            })
            ->where('lr.year', $year)
            ->where('lr.period', $period)
            ->select(
                'lr.market_entity_id',
                'lr.name',
                'lr.has_isil',
                'lr.has_trend',
                'lr.classification',
                'lr.total_jobs',
                'lr.trend_reports',
                'lr.labor_score',
                'lr.trend_score',
            )
            ->selectRaw("{$finalExpr} AS final_score_raw", [$laborWeight, $trendWeight])
            ->selectRaw(
                "ROW_NUMBER() OVER (ORDER BY {$finalExpr} DESC, lr.total_jobs DESC, lr.name ASC) AS position",
                [$laborWeight, $trendWeight]
            );

        $query = DB::query()->fromSub($ranked, 'r')
            ->select(
                DB::raw("'" . $this->entityType() . "' AS entity_type"),
                'r.market_entity_id AS id',
                'r.name',
                'r.has_isil',
                'r.has_trend',
                'r.classification',
                'r.total_jobs',
                'r.trend_reports',
                DB::raw('ROUND(r.labor_score, 1) AS labor_score'),
                DB::raw('ROUND(r.trend_score, 1) AS trend_score'),
                DB::raw('ROUND(r.final_score_raw, 1) AS final_score'),
                'r.position',
            );

        if ($search) {
            $query->where('r.name', 'like', "%{$search}%");
        }

        if (! empty($careers)) {
            $query->whereExists(function ($q) use ($careers) {
                $q->select(DB::raw(1))
                    ->from($this->config('career_table') . ' as mec')
                    ->join('careers as ca', 'ca.id', '=', 'mec.career_id')
                    ->whereColumn('mec.market_entity_id', 'r.market_entity_id')
                    ->whereIn('ca.slug', $careers);
            });
        }

        // Filtro por área (market_entities.category); lo usa Certificaciones
        if (! empty($categories)) {
            $query->whereExists(function ($q) use ($categories) {
                $q->select(DB::raw(1))
                    ->from('market_entities as mcat')
                    ->whereColumn('mcat.id', 'r.market_entity_id')
                    ->whereIn('mcat.category', $categories);
            });
        }

        return $query->orderBy('r.position')->paginate($perPage);
    }

    /**
     * Pesos vigentes [laboral, tendencias]. Si la tabla de ponderaciones no existe
     * o no hay una activa, usa 0.7 / 0.3 como antes.
     *
     * @return array{0: float, 1: float}
     */
    public function activeWeights(): array
    {
        try {
            $weights = Prueba::getActive($this->config('weights_context'));
        } catch (\Throwable $e) {
            $weights = null;
        }

        return [
            (float) ($weights?->labor_weight ?? self::DEFAULT_LABOR_WEIGHT),
            (float) ($weights?->trend_weight ?? self::DEFAULT_TREND_WEIGHT),
        ];
    }

    /**
     * Ventanas paginadas para el modal (más recientes primero), con el top N de cada
     * foto y el cambio de posición respecto a la ventana anterior.
     */
    public function windowsPage(int $year, string $type, int $page, int $perPage, int $top = 5): array
    {
        $paginator = DB::table($this->table('windows_meta'))
            ->where('period_type', $type)
            ->where('year', $year)
            ->orderByDesc('start_date')
            ->paginate($perPage, ['*'], 'page', $page);

        $metas = collect($paginator->items());
        $ranked = $this->rankedWindows($type, $metas->pluck('period_key')->all());

        $data = $metas->map(fn ($m) => $this->formatWindow($m) + [
            'top' => array_slice($ranked[$m->period_key] ?? [], 0, $top),
        ])->all();

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * Todas las fotos de un año y tipo de ventana (más antiguas primero), con todos
     * los lenguajes, para el export a Excel.
     */
    public function windowsForExport(int $year, string $type): array
    {
        return iterator_to_array($this->windowsForExportLazy($year, $type), false);
    }

    /**
     * Igual que windowsForExport(), pero de $chunk en $chunk ventanas, para el export
     * en streaming de indicadores grandes (Tecnologías: ~450 entidades × 45 semanas).
     *
     * @return \Generator<array>
     */
    public function windowsForExportLazy(int $year, string $type, int $chunk = 5): \Generator
    {
        $metas = DB::table($this->table('windows_meta'))
            ->where('period_type', $type)
            ->where('year', $year)
            ->orderBy('start_date')
            ->get();

        foreach ($metas->chunk($chunk) as $batch) {
            $ranked = $this->rankedWindows($type, $batch->pluck('period_key')->all());

            foreach ($batch as $m) {
                yield $this->formatWindow($m) + ['rows' => $ranked[$m->period_key] ?? []];
            }
        }
    }

    /**
     * Ranking de cada ventana con los pesos vigentes (como el ranking principal:
     * ORDER BY final DESC, vacantes DESC, nombre ASC, en SQL y con la misma
     * intercalación) y cambio de posición respecto a la ventana anterior del mismo tipo.
     *
     * @return array<string, array<int, array>>  [period_key => filas ordenadas]
     */
    private function rankedWindows(string $type, array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        [$laborWeight, $trendWeight] = $this->activeWeights();

        // Ventana anterior de cada una (puede ser de otro año); en un semestre nuevo no se compara
        $all = DB::table($this->table('windows_meta'))
            ->where('period_type', $type)
            ->orderBy('start_date')
            ->get(['period_key', 'is_semester_start'])
            ->values();
        $prevOf = [];
        foreach ($all as $i => $m) {
            $prevOf[$m->period_key] = $i > 0 && ! $m->is_semester_start ? $all[$i - 1]->period_key : null;
        }

        $needed = array_values(array_unique(array_merge($keys, array_filter(array_map(fn ($k) => $prevOf[$k] ?? null, $keys)))));
        $finalExpr = '(w.labor_score * ? + w.trend_score * ?)';

        $rows = DB::table($this->table('windows') . ' as w')
            ->join('market_entities as me', function ($j) {
                $j->on('me.id', '=', 'w.market_entity_id')->where('me.is_active', 1);
            })
            ->where('w.period_type', $type)
            ->whereIn('w.period_key', $needed)
            ->select('w.period_key', 'w.market_entity_id', 'w.name', 'w.jobs', 'w.new_jobs', 'w.trend_reports')
            ->selectRaw('ROUND(w.labor_score, 1) AS labor_score, ROUND(w.trend_score, 1) AS trend_score')
            ->selectRaw("ROUND({$finalExpr}, 1) AS final_score", [$laborWeight, $trendWeight])
            ->selectRaw(
                "ROW_NUMBER() OVER (PARTITION BY w.period_key ORDER BY {$finalExpr} DESC, w.jobs DESC, w.name ASC) AS position",
                [$laborWeight, $trendWeight]
            )
            ->orderBy('w.period_key')
            ->orderBy('position')
            ->get()
            ->groupBy('period_key');

        $result = [];
        foreach ($keys as $key) {
            $prevKey = $prevOf[$key] ?? null;
            $prevPos = $prevKey ? ($rows[$prevKey] ?? collect())->pluck('position', 'market_entity_id') : null;

            $result[$key] = ($rows[$key] ?? collect())->map(function ($r) use ($prevPos) {
                $before = $prevPos?->get($r->market_entity_id);

                return [
                    'id' => (int) $r->market_entity_id,
                    'name' => $r->name,
                    'jobs' => (int) $r->jobs,
                    'new_jobs' => (int) $r->new_jobs,
                    'trend_reports' => (int) $r->trend_reports,
                    'labor_score' => (float) $r->labor_score,
                    'trend_score' => (float) $r->trend_score,
                    'final_score' => (float) $r->final_score,
                    'ranking_position' => (int) $r->position,
                    // null = sin comparación (inicio de semestre o primera ventana)
                    'position_change' => $prevPos === null ? null : ($before === null ? 'nuevo' : (int) $before - (int) $r->position),
                ];
            })->values()->all();
        }

        return $result;
    }

    private function formatWindow(object $m): array
    {
        [$y, $s] = explode('-', $m->semester);

        return [
            'period' => $m->period_key,
            'label' => $m->label,
            'start_date' => $m->start_date,
            'end_date' => $m->end_date,
            'cutoff_date' => $m->cutoff_date,
            'semester' => $m->semester,
            'semester_label' => ($s === 's1' ? 'Semestre 1' : 'Semestre 2') . " {$y}",
            'semester_start' => $s === 's1' ? "{$y}-01-01" : "{$y}-07-01",
            'is_semester_start' => (bool) $m->is_semester_start,
            'is_partial' => (bool) $m->is_partial,
            'unique_offers' => (int) $m->unique_offers,          // acumuladas del semestre al corte
            'new_unique_offers' => (int) $m->new_unique_offers,  // publicadas dentro de la ventana
            'total_jobs' => (int) $m->unique_offers,             // compatibilidad
        ];
    }

    /**
     * Ventanas de calendario desde $from hasta la que contiene $today.
     *
     * @return \Generator<array{key: string, year: int, start: string, end: string, label: string}>
     */
    private function calendarWindows(string $type, Carbon $from, Carbon $today): \Generator
    {
        $cursor = match ($type) {
            'weekly' => $from->copy()->startOfWeek(Carbon::MONDAY),
            default => $from->copy()->startOfMonth(),
        };

        while ($cursor->lte($today)) {
            if ($type === 'weekly') {
                $end = $cursor->copy()->addDays(6);
                yield [
                    'key' => sprintf('%d-W%02d', $cursor->isoWeekYear, $cursor->isoWeek),
                    'year' => $cursor->isoWeekYear,
                    'start' => $cursor->toDateString(),
                    'end' => $end->toDateString(),
                    'label' => sprintf('Semana %d · %s → %s', $cursor->isoWeek, $this->shortDate($cursor), $this->shortDate($end)),
                ];
                $cursor = $cursor->copy()->addWeek();
            } elseif ($type === 'biweekly') {
                $firstHalf = $cursor->day === 1;
                $end = $firstHalf ? $cursor->copy()->day(15) : $cursor->copy()->endOfMonth()->startOfDay();
                yield [
                    'key' => $cursor->format('Y-m') . ($firstHalf ? '-Q1' : '-Q2'),
                    'year' => $cursor->year,
                    'start' => $cursor->toDateString(),
                    'end' => $end->toDateString(),
                    'label' => ($firstHalf ? '1ra' : '2da') . ' quincena ' . self::MONTHS_LONG[$cursor->month],
                ];
                $cursor = $firstHalf ? $cursor->copy()->day(16) : $cursor->copy()->addMonthNoOverflow()->startOfMonth();
            } else {
                yield [
                    'key' => $cursor->format('Y-m'),
                    'year' => $cursor->year,
                    'start' => $cursor->toDateString(),
                    'end' => $cursor->copy()->endOfMonth()->toDateString(),
                    'label' => ucfirst(self::MONTHS_LONG[$cursor->month]),
                ];
                $cursor = $cursor->copy()->addMonthNoOverflow()->startOfMonth();
            }
        }
    }

    /** "02-nov" */
    private function shortDate(Carbon $d): string
    {
        return $d->format('d') . '-' . self::MONTHS_SHORT[$d->month];
    }

    /* ==================================================
       ESTADO DEL RECÁLCULO (botón del frontend)
    ================================================== */

    /** true si hay un recálculo corriendo o encolado hace poco. */
    public function isBusy(): bool
    {
        $lock = Cache::lock($this->lockName(), self::LOCK_SECONDS);

        if (! $lock->get()) {
            return true;
        }
        $lock->release();

        $status = $this->status();

        // Un 'queued' viejo (worker caído) no debe bloquear el botón para siempre.
        return ($status['state'] ?? null) === 'queued'
            && Carbon::parse($status['updated_at'])->gt(now()->subSeconds(self::LOCK_SECONDS));
    }

    public function markQueued(): void
    {
        $this->setStatus('queued');
    }

    /** @return array{state: string|null, message: string|null, updated_at: string|null} */
    public function status(): array
    {
        return Cache::get($this->statusKey(), ['state' => null, 'message' => null, 'updated_at' => null]);
    }

    private function setStatus(string $state, ?string $message = null): void
    {
        Cache::put($this->statusKey(), [
            'state' => $state,
            'message' => $message,
            'updated_at' => now()->toIso8601String(),
        ], now()->addDay());
    }

    /* ==================================================
       UTILIDADES
    ================================================== */

    private function classification(bool $hasIsil, bool $hasTrend): string
    {
        return match (true) {
            $hasIsil && $hasTrend => 'isil+trend',
            $hasIsil => 'isil',
            $hasTrend => 'trend',
            default => 'market',
        };
    }

    private function msSince(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
