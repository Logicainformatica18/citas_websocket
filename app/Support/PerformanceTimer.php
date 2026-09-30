<?php

namespace App\Support;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Medición de rendimiento por petición.
 *
 * Registra en el canal "performance" un único registro JSON por petición con:
 * ruta, método del controlador, filtros, tiempo total, tiempo por sección,
 * consultas SQL (cantidad y tiempo acumulado), consultas lentas, memoria pico
 * y, si hubo error, el mensaje de la excepción y la sección en curso.
 *
 * Se activa con PERFORMANCE_LOG=true en .env. Si está desactivada, measure()
 * ejecuta la función directamente sin ningún costo adicional.
 *
 * Uso:
 *   $timer = PerformanceTimer::begin('index', $filtros);
 *   $total = $timer->measure('kpi_total', fn () => $query->count());
 *   $timer->finish();
 *
 * Desde servicios sin acceso a la instancia:
 *   PerformanceTimer::section('estado_mercado.global', fn () => ...);
 */
class PerformanceTimer
{
    /** Umbral a partir del cual una consulta se registra como lenta. */
    private const SLOW_QUERY_MS = 200;

    /** Instancia activa en la petición actual. */
    private static ?self $current = null;

    /** Indica si el listener de consultas ya fue registrado en este proceso. */
    private static bool $listenerRegistered = false;

    private float $startedAt;

    /** @var array<string, array{ms: float, consultas: int, sql_ms: float}> */
    private array $sections = [];

    /** @var array<int, array{label: string, started: float}> */
    private array $stack = [];

    private int $queryCount = 0;

    private float $queryTimeMs = 0.0;

    /** @var array<int, array{seccion: ?string, ms: float, sql: string, bindings: array}> */
    private array $slowQueries = [];

    private ?string $error = null;

    private ?string $errorSection = null;

    private bool $written = false;

    private function __construct(
        private readonly string $action,
        private readonly array $filters,
    ) {
        $this->startedAt = microtime(true);
    }

    public static function enabled(): bool
    {
        return (bool) config('logging.performance_log', false);
    }

    /**
     * Inicia la medición de la petición actual.
     * Si la instrumentación está desactivada, devuelve una instancia inerte.
     */
    public static function begin(string $action, array $filters = []): self
    {
        $timer = new self($action, $filters);

        if (! self::enabled()) {
            return $timer;
        }

        self::$current = $timer;
        self::registerQueryListener();

        // El timeout de PHP es un error fatal que no pasa por try/catch:
        // al apagarse el proceso se registra la sección en la que se cortó.
        register_shutdown_function(static function () use ($timer) {
            if ($timer->written) {
                return;
            }

            $fatal = error_get_last();
            $isFatal = $fatal && in_array($fatal['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true);

            $timer->error ??= $isFatal
                ? $fatal['message'] . ' en ' . $fatal['file'] . ':' . $fatal['line']
                : 'La petición terminó sin llamar a finish()';
            $timer->errorSection ??= $timer->currentSection();

            $timer->write();
        });

        return $timer;
    }

    /**
     * Ejecuta una sección con nombre en la instancia activa, si existe.
     */
    public static function section(string $label, callable $fn): mixed
    {
        return self::$current
            ? self::$current->measure($label, $fn)
            : $fn();
    }

    /**
     * Mide el tiempo y las consultas de una sección con nombre.
     * Si la sección lanza una excepción, se registra y se vuelve a lanzar.
     */
    public function measure(string $label, callable $fn): mixed
    {
        if (self::$current !== $this) {
            return $fn();
        }

        $this->start($label);

        try {
            return $fn();
        } catch (Throwable $e) {
            $this->fail($e);
            throw $e;
        } finally {
            $this->stop();
        }
    }

    public function start(string $label): void
    {
        if (self::$current !== $this) {
            return;
        }

        $this->stack[] = ['label' => $label, 'started' => microtime(true)];
        $this->sections[$label] ??= ['ms' => 0.0, 'consultas' => 0, 'sql_ms' => 0.0];
    }

    public function stop(): void
    {
        if (self::$current !== $this || ! $this->stack) {
            return;
        }

        $frame = array_pop($this->stack);
        $this->sections[$frame['label']]['ms'] += (microtime(true) - $frame['started']) * 1000;
    }

    /**
     * Registra una excepción; conserva solo la primera (la causa original).
     */
    public function fail(Throwable $e): void
    {
        if ($this->error !== null) {
            return;
        }

        $this->error = get_class($e) . ': ' . $e->getMessage();
        $this->errorSection = $this->currentSection();
    }

    /**
     * Cierra la medición y escribe el registro.
     */
    public function finish(): void
    {
        if (self::$current !== $this) {
            return;
        }

        while ($this->stack) {
            $this->stop();
        }

        $this->write();
    }

    private function currentSection(): ?string
    {
        return $this->stack ? end($this->stack)['label'] : null;
    }

    private static function registerQueryListener(): void
    {
        if (self::$listenerRegistered) {
            return;
        }

        self::$listenerRegistered = true;

        DB::listen(static function (QueryExecuted $query) {
            self::$current?->recordQuery($query);
        });
    }

    private function recordQuery(QueryExecuted $query): void
    {
        $this->queryCount++;
        $this->queryTimeMs += $query->time;

        // Una consulta cuenta para todas las secciones abiertas (tiempos inclusivos).
        foreach ($this->stack as $frame) {
            $this->sections[$frame['label']]['consultas']++;
            $this->sections[$frame['label']]['sql_ms'] += $query->time;
        }

        if ($query->time >= self::SLOW_QUERY_MS) {
            $this->slowQueries[] = [
                'seccion'  => $this->currentSection(),
                'ms'       => round($query->time, 1),
                'sql'      => preg_replace('/\s+/', ' ', trim($query->sql)),
                'bindings' => $query->bindings,
            ];
        }
    }

    private function write(): void
    {
        if ($this->written) {
            return;
        }

        $this->written = true;

        $request = request();
        $route = $request?->route();

        $sections = array_map(static fn (array $s) => [
            'ms'        => round($s['ms'], 1),
            'consultas' => $s['consultas'],
            'sql_ms'    => round($s['sql_ms'], 1),
        ], $this->sections);

        $record = [
            'ruta'             => $request ? $request->method() . ' /' . ltrim($request->path(), '/') : null,
            'controlador'      => $route?->getActionName(),
            'accion'           => $this->action,
            'filtros'          => $this->filters,
            'total_ms'         => round((microtime(true) - $this->startedAt) * 1000, 1),
            'secciones'        => $sections,
            'consultas'        => $this->queryCount,
            'sql_ms'           => round($this->queryTimeMs, 1),
            'consultas_lentas' => $this->slowQueries,
            'memoria_pico_mb'  => round(memory_get_peak_usage(true) / 1048576, 1),
            'error'            => $this->error,
            'seccion_error'    => $this->errorSection,
        ];

        try {
            Log::channel('performance')->info('Medición de rendimiento', $record);
        } catch (Throwable) {
            // La instrumentación nunca debe interrumpir la respuesta.
        }

        if (self::$current === $this) {
            self::$current = null;
        }
    }
}
