<?php

namespace App\Console\Commands\Dashboards;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Reemplaza el SQL de widgets lentos por su versión optimizada, definida en
 * database/sql/widgets/manifest.php.
 *
 * Con --desactivar-reintentos desactiva, en vez de eso, los entrenamientos IA de
 * los reintentos que hizo la IA con la misma pregunta (sin eliminar nada).
 *
 * Los registros se ubican por columnas de negocio y nunca por id, porque los id
 * cambian entre entornos. Antes de escribir se valida todo; si un solo elemento
 * no cuadra, no se modifica nada.
 */
class OptimizarSqlWidgets extends Command
{
    protected $signature = 'observatorio:optimizar-sql-widgets
                            {--dry-run : Muestra qué registros encontró y qué SQL reemplazaría, sin escribir nada}
                            {--rollback : Restaura el SQL anterior desde un respaldo}
                            {--backup= : Archivo de respaldo a restaurar con --rollback (por defecto, el más reciente)}
                            {--desactivar-reintentos : Desactiva los entrenamientos de los reintentos de la IA con la misma pregunta}
                            {--force : No pide confirmación al desactivar reintentos}';

    protected $description = 'Aplica (o revierte) los SQL optimizados de los widgets del dashboard IA y desactiva los reintentos de la IA';

    private const CARPETA_SQL = 'database/sql/widgets';

    private const PREFIJO_RESPALDO = 'sql-widgets-';

    /** Tipos de respaldo: reemplazo de SQL o desactivación de reintentos. */
    private const TIPO_SQL        = 'sql';
    private const TIPO_REINTENTOS = 'reintentos';

    private const ORIGINAL   = 'ORIGINAL';
    private const OPTIMIZADO = 'OPTIMIZADO';
    private const SIN_SQL    = 'SIN SQL PROPIO';
    private const OTRO       = 'DESCONOCIDO';

    public function handle(): int
    {
        try {
            if ($this->option('rollback') && $this->option('desactivar-reintentos')) {
                throw new RuntimeException('Use --rollback o --desactivar-reintentos, no ambas a la vez.');
            }

            return match (true) {
                (bool) $this->option('rollback')              => $this->revertir(),
                (bool) $this->option('desactivar-reintentos') => $this->desactivarReintentos(),
                default                                         => $this->aplicar(),
            };
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            $this->line('No se modificó ningún registro.');

            return self::FAILURE;
        }
    }

    /* =====================================================
       APLICAR
    ===================================================== */

    private function aplicar(): int
    {
        $manifiesto = $this->manifiesto();
        $elementos  = array_map(
            fn (string $clave, array $def) => $this->resolver($clave, $def),
            array_keys($manifiesto),
            $manifiesto
        );

        $this->mostrarResumen($elementos);

        $desconocidos = array_filter($elementos, fn ($e) => in_array(self::OTRO, [$e['estado_widget'], $e['estado_training']], true));
        if ($desconocidos !== []) {
            foreach ($desconocidos as $e) {
                $this->mostrarSqlDesconocido($e);
            }

            throw new RuntimeException(
                'Hay SQL que no coincide ni con el original ni con el optimizado. '
                . 'Puede haberse editado a mano en este entorno; revíselo antes de continuar.'
            );
        }

        $pendientes = array_filter($elementos, fn ($e) => $e['estado_widget'] === self::ORIGINAL || $e['estado_training'] === self::ORIGINAL);
        if ($pendientes === []) {
            $this->info('Todos los SQL ya están optimizados. No hay nada que hacer.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            foreach ($pendientes as $e) {
                $this->mostrarReemplazo($e);
            }
            $this->warn('Simulación (--dry-run): no se escribió nada.');

            return self::SUCCESS;
        }

        $archivo = $this->guardarRespaldo($pendientes);
        $this->info("Respaldo guardado en {$archivo}");

        DB::transaction(function () use ($pendientes) {
            foreach ($pendientes as $e) {
                // Se vuelve a leer con bloqueo: entre la validación y la escritura
                // otro proceso pudo cambiar el SQL.
                $this->escribir($e, $e['sql_original'], $e['sql_optimizado'], $e['sql_optimizado']);
            }
        });

        $this->info(count($pendientes) . ' elemento(s) optimizado(s) correctamente.');
        $this->line('Para revertir: php artisan observatorio:optimizar-sql-widgets --rollback --backup=' . basename($archivo));

        return self::SUCCESS;
    }

    /* =====================================================
       REVERTIR
    ===================================================== */

    private function revertir(): int
    {
        $archivo    = $this->archivoRespaldo();
        $respaldo   = json_decode(File::get($archivo), true);
        $manifiesto = $this->manifiesto();

        if (!is_array($respaldo) || (empty($respaldo['elementos']) && empty($respaldo['reintentos']))) {
            throw new RuntimeException("El respaldo {$archivo} no tiene el formato esperado.");
        }

        $this->info('Respaldo: ' . basename($archivo) . " (creado el {$respaldo['creado_en']})");

        if (($respaldo['tipo'] ?? self::TIPO_SQL) === self::TIPO_REINTENTOS) {
            return $this->revertirReintentos($respaldo);
        }

        $elementos = [];
        foreach ($respaldo['elementos'] as $r) {
            if (!isset($manifiesto[$r['clave']])) {
                throw new RuntimeException("El respaldo contiene «{$r['clave']}», que ya no figura en el manifiesto.");
            }

            // Se ubica de nuevo por columnas de negocio y se exige que el resultado
            // sea el mismo registro del respaldo, para no restaurar sobre otro.
            $e = $this->resolver($r['clave'], $manifiesto[$r['clave']]);

            if ($e['widget_id'] !== $r['widget_id'] || $e['training_id'] !== $r['training_id']) {
                throw new RuntimeException(
                    "«{$r['clave']}»: los registros actuales (widget {$e['widget_id']}, entrenamiento {$e['training_id']}) "
                    . "no son los del respaldo (widget {$r['widget_id']}, entrenamiento {$r['training_id']})."
                );
            }

            $e['sql_restaurar_widget']   = $r['widget_sql_anterior'];
            $e['sql_restaurar_training'] = $r['training_sql_anterior'];
            $elementos[] = $e;
        }

        $this->mostrarResumen($elementos);

        // Solo se restaura sobre el SQL optimizado. Si ya está el anterior, se omite;
        // si hay otro distinto, se detiene para no pisar un cambio posterior.
        $pendientes = [];
        foreach ($elementos as $e) {
            $widgetYaRestaurado   = $this->normalizar($e['sql_widget']) === $this->normalizar($e['sql_restaurar_widget']);
            $trainingYaRestaurado = $this->normalizar($e['sql_training']) === $this->normalizar($e['sql_restaurar_training']);

            if ($widgetYaRestaurado && $trainingYaRestaurado) {
                continue;
            }

            foreach ([
                'widget'        => [$e['estado_widget'], $widgetYaRestaurado],
                'entrenamiento' => [$e['estado_training'], $trainingYaRestaurado],
            ] as $tipo => [$estado, $yaRestaurado]) {
                if (!$yaRestaurado && $estado !== self::OPTIMIZADO) {
                    throw new RuntimeException(
                        "«{$e['clave']}»: el SQL actual del {$tipo} no es el optimizado (estado: {$estado}). "
                        . 'Se cambió después de aplicar el respaldo; revíselo antes de revertir.'
                    );
                }
            }

            $pendientes[] = $e;
        }

        if ($pendientes === []) {
            $this->info('Los registros ya tienen el SQL del respaldo. No hay nada que hacer.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('Simulación (--dry-run): se restaurarían ' . count($pendientes) . ' elemento(s). No se escribió nada.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($pendientes) {
            foreach ($pendientes as $e) {
                $this->escribir($e, $e['sql_optimizado'], $e['sql_restaurar_widget'], $e['sql_restaurar_training']);
            }
        });

        $this->info(count($pendientes) . ' elemento(s) restaurado(s) desde el respaldo.');

        return self::SUCCESS;
    }

    /* =====================================================
       REINTENTOS DE LA IA
    ===================================================== */

    /**
     * Desactiva (is_active = 0) los entrenamientos IA ligados a los reintentos.
     *
     * Un reintento es otro registro de sqltrainings con la misma pregunta que el
     * entrenamiento vigente del widget. No se elimina nada: desactivar basta para
     * que no aparezcan en las sugerencias del chat y es reversible con --rollback.
     */
    private function desactivarReintentos(): int
    {
        $filas = collect();

        foreach ($this->manifiesto() as $clave => $def) {
            $filas = $filas->merge($this->buscarReintentos($this->resolver($clave, $def)));
        }

        if ($filas->isEmpty()) {
            $this->info('No hay reintentos para las preguntas del manifiesto.');

            return self::SUCCESS;
        }

        $this->table(
            ['Clave', 'SQL (id)', 'Estado SQL', 'Entrenamiento IA (id)', 'Etapa', 'Activo', 'Acción'],
            $filas->map(fn ($f) => [
                $f['clave'], $f['sql_training_id'], $f['test_status'],
                $f['ai_training_id'] ?? '—', $f['training_stage'] ?? '—',
                $f['is_active'] === null ? '—' : ($f['is_active'] ? 'sí' : 'no'),
                $f['accion'],
            ])->all()
        );

        $afectados = $filas->where('accion', 'DESACTIVAR')->values();

        if ($afectados->isEmpty()) {
            $this->info('No hay entrenamientos que desactivar.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("Simulación (--dry-run): se desactivarían {$afectados->count()} entrenamiento(s) IA. No se escribió nada.");

            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm("¿Desactivar {$afectados->count()} entrenamiento(s) IA?", false)) {
            $this->line('Operación cancelada. No se modificó ningún registro.');

            return self::SUCCESS;
        }

        $archivo = $this->escribirRespaldo(self::TIPO_REINTENTOS, [
            'reintentos' => $afectados->map(fn ($f) => [
                'clave'              => $f['clave'],
                'query_text'         => $f['query_text'],
                'sql_training_id'    => $f['sql_training_id'],
                'ai_training_id'     => $f['ai_training_id'],
                'is_active_anterior' => $f['is_active'],
            ])->all(),
        ]);
        $this->info("Respaldo guardado en {$archivo}");

        $total = DB::transaction(fn () => DB::table('aitrainings')
            ->whereIn('id', $afectados->pluck('ai_training_id'))
            ->where('is_active', 1)
            ->update(['is_active' => 0, 'updated_at' => now()]));

        $this->info("{$total} entrenamiento(s) IA desactivado(s).");
        $this->line('Para revertir: php artisan observatorio:optimizar-sql-widgets --rollback --backup=' . basename($archivo));

        return self::SUCCESS;
    }

    /**
     * Lista los reintentos de la pregunta del widget y decide qué hacer con cada
     * entrenamiento IA. Nunca se tocan los que estén en etapa final ni los que
     * use algún widget, aunque cuelguen de un reintento.
     */
    private function buscarReintentos(array $e): array
    {
        $pregunta = (string) DB::table('sqltrainings')->where('id', $e['training_id'])->value('query_text');

        $reintentos = DB::table('sqltrainings')
            ->whereRaw('LOWER(TRIM(query_text)) = ?', [mb_strtolower(trim($pregunta))])
            ->where('id', '!=', $e['training_id'])
            ->orderBy('created_at')
            ->get(['id', 'test_status']);

        if ($reintentos->isEmpty()) {
            return [];
        }

        $aiTrainings = DB::table('aitrainings')
            ->whereIn('sql_training_id', $reintentos->pluck('id'))
            ->get(['id', 'sql_training_id', 'training_stage', 'is_active'])
            ->groupBy('sql_training_id');

        $aiUsadosPorWidgets = DB::table('dashboard_widgets')
            ->whereIn('ai_training_id', $aiTrainings->flatten()->pluck('id'))
            ->pluck('ai_training_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $sqlUsadosPorWidgets = DB::table('dashboard_widgets')
            ->whereIn(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(data_source, '$.sql_training_id'))"), $reintentos->pluck('id')->map(fn ($id) => (string) $id))
            ->pluck(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(data_source, '$.sql_training_id'))"))
            ->map(fn ($id) => (int) $id)
            ->all();

        $filas = [];
        foreach ($reintentos as $s) {
            $base = [
                'clave'           => $e['clave'],
                'query_text'      => $pregunta,
                'sql_training_id' => (int) $s->id,
                'test_status'     => $s->test_status,
            ];

            $ligados = $aiTrainings->get($s->id, collect());

            if ($ligados->isEmpty()) {
                $filas[] = $base + ['ai_training_id' => null, 'training_stage' => null, 'is_active' => null, 'accion' => 'SIN ENTRENAMIENTO IA'];
                continue;
            }

            foreach ($ligados as $a) {
                $accion = match (true) {
                    in_array((int) $a->id, $aiUsadosPorWidgets, true)
                        || in_array((int) $s->id, $sqlUsadosPorWidgets, true) => 'SE CONSERVA: lo usa un widget',
                    $a->training_stage === 'final'                           => 'SE CONSERVA: etapa final',
                    !$a->is_active                                           => 'YA INACTIVO',
                    default                                                  => 'DESACTIVAR',
                };

                $filas[] = $base + [
                    'ai_training_id' => (int) $a->id,
                    'training_stage' => $a->training_stage,
                    'is_active'      => (int) $a->is_active,
                    'accion'         => $accion,
                ];
            }
        }

        return $filas;
    }

    /**
     * Reactiva los entrenamientos IA de un respaldo de reintentos. Antes verifica
     * que cada uno siga ligado al mismo SQL y a la misma pregunta.
     */
    private function revertirReintentos(array $respaldo): int
    {
        $pendientes = [];
        $filas      = [];

        foreach ($respaldo['reintentos'] as $r) {
            $actual = DB::table('aitrainings as a')
                ->join('sqltrainings as s', 's.id', '=', 'a.sql_training_id')
                ->where('a.id', $r['ai_training_id'])
                ->first(['a.id', 'a.is_active', 'a.sql_training_id', 's.query_text']);

            if (!$actual
                || (int) $actual->sql_training_id !== (int) $r['sql_training_id']
                || mb_strtolower(trim($actual->query_text)) !== mb_strtolower(trim($r['query_text']))) {
                throw new RuntimeException(
                    "El entrenamiento IA {$r['ai_training_id']} ya no está ligado al SQL {$r['sql_training_id']} "
                    . "con la pregunta «{$r['query_text']}». Revíselo antes de revertir."
                );
            }

            $yaRestaurado = (int) $actual->is_active === (int) $r['is_active_anterior'];
            $filas[]      = [$r['clave'], $r['sql_training_id'], $r['ai_training_id'], $yaRestaurado ? 'YA RESTAURADO' : 'REACTIVAR'];

            if (!$yaRestaurado) {
                $pendientes[] = $r;
            }
        }

        $this->table(['Clave', 'SQL (id)', 'Entrenamiento IA (id)', 'Acción'], $filas);

        if ($pendientes === []) {
            $this->info('Los registros ya tienen el estado del respaldo. No hay nada que hacer.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('Simulación (--dry-run): se reactivarían ' . count($pendientes) . ' entrenamiento(s) IA. No se escribió nada.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($pendientes) {
            foreach ($pendientes as $r) {
                DB::table('aitrainings')->where('id', $r['ai_training_id'])->update([
                    'is_active'  => (int) $r['is_active_anterior'],
                    'updated_at' => now(),
                ]);
            }
        });

        $this->info(count($pendientes) . ' entrenamiento(s) IA reactivado(s) desde el respaldo.');

        return self::SUCCESS;
    }

    /* =====================================================
       UBICACIÓN DE REGISTROS
    ===================================================== */

    /**
     * Ubica el widget y su entrenamiento. Se detiene si no hay exactamente uno.
     *
     * - Dashboard: dashboards.slug (índice único uk_dashboards_slug).
     * - Widget: dashboard + title + type = 'chart'. title no tiene índice único,
     *   por eso se cuenta.
     * - Entrenamiento: sqltrainings.query_text se repite en cada reintento de la
     *   IA, así que se toma el que referencia el propio widget (data_source y
     *   aitrainings) y se exige que su pregunta sea el título del widget.
     */
    private function resolver(string $clave, array $def): array
    {
        $dashboards = DB::table('dashboards')->where('slug', $def['dashboard_slug'])->get(['id']);
        if ($dashboards->count() !== 1) {
            throw new RuntimeException("«{$clave}»: se esperaba 1 dashboard con slug «{$def['dashboard_slug']}» y se encontraron {$dashboards->count()}.");
        }

        $widgets = DB::table('dashboard_widgets')
            ->where('dashboard_id', $dashboards[0]->id)
            ->where('title', $def['widget_title'])
            ->where('type', 'chart')
            ->get(['id', 'ai_training_id', 'data_source']);

        if ($widgets->count() !== 1) {
            throw new RuntimeException(
                "«{$clave}»: se esperaba 1 widget «{$def['widget_title']}» en el dashboard «{$def['dashboard_slug']}» "
                . "y se encontraron {$widgets->count()}" . ($widgets->isNotEmpty() ? ' (id: ' . $widgets->pluck('id')->implode(', ') . ')' : '') . '.'
            );
        }

        $widget     = $widgets[0];
        $dataSource = json_decode($widget->data_source ?? '{}', true) ?? [];

        $referencias = collect([
            $dataSource['sql_training_id'] ?? null,
            $widget->ai_training_id
                ? DB::table('aitrainings')->where('id', $widget->ai_training_id)->value('sql_training_id')
                : null,
        ])->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($referencias->count() !== 1) {
            throw new RuntimeException(
                "«{$clave}»: el widget debe referenciar exactamente 1 entrenamiento y referencia "
                . ($referencias->isEmpty() ? 'ninguno' : $referencias->implode(', ')) . '.'
            );
        }

        $training = DB::table('sqltrainings')->where('id', $referencias[0])->first(['id', 'query_text', 'sql_validated']);
        if (!$training) {
            throw new RuntimeException("«{$clave}»: el entrenamiento que referencia el widget no existe.");
        }

        if (mb_strtolower(trim((string) $training->query_text)) !== mb_strtolower(trim($def['widget_title']))) {
            throw new RuntimeException("«{$clave}»: la pregunta del entrenamiento («{$training->query_text}») no coincide con el título del widget.");
        }

        [$original, $optimizado] = $this->leerSql($def['archivo']);

        return [
            'clave'           => $clave,
            'dashboard_slug'  => $def['dashboard_slug'],
            'widget_title'    => $def['widget_title'],
            'widget_id'       => (int) $widget->id,
            'training_id'     => (int) $training->id,
            'sql_widget'      => $dataSource['sql_query'] ?? null,
            'sql_training'    => $training->sql_validated,
            'estado_widget'   => $this->estado($dataSource['sql_query'] ?? null, $original, $optimizado),
            'estado_training' => $this->estado($training->sql_validated, $original, $optimizado),
            'sql_original'    => $original,
            'sql_optimizado'  => $optimizado,
        ];
    }

    /* =====================================================
       ESCRITURA
    ===================================================== */

    /**
     * Reemplaza el SQL del widget y del entrenamiento dentro de la transacción
     * abierta. Solo escribe donde el SQL actual sea $esperado; lo demás se deja
     * como está.
     */
    private function escribir(array $e, string $esperado, ?string $nuevoWidget, ?string $nuevoTraining): void
    {
        $widget     = DB::table('dashboard_widgets')->where('id', $e['widget_id'])->lockForUpdate()->first(['data_source']);
        $dataSource = json_decode($widget->data_source ?? '{}', true) ?? [];

        if ($this->normalizar($dataSource['sql_query'] ?? null) === $this->normalizar($esperado) && $nuevoWidget !== null) {
            $dataSource['sql_query'] = $nuevoWidget;

            DB::table('dashboard_widgets')->where('id', $e['widget_id'])->update([
                'data_source' => json_encode($dataSource, JSON_UNESCAPED_UNICODE),
                'updated_at'  => now(),
            ]);
        }

        $actual = DB::table('sqltrainings')->where('id', $e['training_id'])->lockForUpdate()->value('sql_validated');

        if ($this->normalizar($actual) === $this->normalizar($esperado) && $nuevoTraining !== null) {
            DB::table('sqltrainings')->where('id', $e['training_id'])->update([
                'sql_validated' => $nuevoTraining,
                'updated_at'    => now(),
            ]);
        }
    }

    private function guardarRespaldo(array $elementos): string
    {
        return $this->escribirRespaldo(self::TIPO_SQL, [
            'elementos' => array_values(array_map(fn ($e) => [
                'clave'                 => $e['clave'],
                'dashboard_slug'        => $e['dashboard_slug'],
                'widget_title'          => $e['widget_title'],
                'widget_id'             => $e['widget_id'],
                'training_id'           => $e['training_id'],
                'widget_sql_anterior'   => $e['sql_widget'],
                'training_sql_anterior' => $e['sql_training'],
            ], $elementos)),
        ]);
    }

    private function escribirRespaldo(string $tipo, array $datos): string
    {
        $carpeta = storage_path('app' . DIRECTORY_SEPARATOR . 'backups');
        File::ensureDirectoryExists($carpeta);

        $archivo = $carpeta . DIRECTORY_SEPARATOR . self::PREFIJO_RESPALDO . now()->format('Ymd_His') . '.json';

        $contenido = [
            'tipo'          => $tipo,
            'creado_en'     => now()->toDateTimeString(),
            'entorno'       => app()->environment(),
            'base_de_datos' => DB::connection()->getDatabaseName(),
        ] + $datos;

        File::put($archivo, json_encode($contenido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $archivo;
    }

    /* =====================================================
       APOYO
    ===================================================== */

    private function manifiesto(): array
    {
        $ruta = base_path(self::CARPETA_SQL . '/manifest.php');

        if (!File::exists($ruta)) {
            throw new RuntimeException("No existe el manifiesto {$ruta}.");
        }

        return require $ruta;
    }

    /** @return array{0: string, 1: string} SQL original y optimizado. */
    private function leerSql(string $archivo): array
    {
        return array_map(function (string $sufijo) use ($archivo) {
            $ruta = base_path(self::CARPETA_SQL . "/{$archivo}.{$sufijo}.sql");

            if (!File::exists($ruta)) {
                throw new RuntimeException("No existe el archivo {$ruta}.");
            }

            return trim(File::get($ruta));
        }, ['original', 'optimizado']);
    }

    private function archivoRespaldo(): string
    {
        $carpeta = storage_path('app' . DIRECTORY_SEPARATOR . 'backups');

        if ($nombre = $this->option('backup')) {
            $ruta = $carpeta . DIRECTORY_SEPARATOR . basename($nombre);

            if (!File::exists($ruta)) {
                throw new RuntimeException("No existe el respaldo {$ruta}.");
            }

            return $ruta;
        }

        $archivos = collect(File::glob($carpeta . DIRECTORY_SEPARATOR . self::PREFIJO_RESPALDO . '*.json'))->sort()->values();

        if ($archivos->isEmpty()) {
            throw new RuntimeException("No hay respaldos en {$carpeta}.");
        }

        return $archivos->last();
    }

    private function estado(?string $sql, string $original, string $optimizado): string
    {
        $sql = $this->normalizar($sql);

        return match (true) {
            $sql === ''                             => self::SIN_SQL,
            $sql === $this->normalizar($original)   => self::ORIGINAL,
            $sql === $this->normalizar($optimizado) => self::OPTIMIZADO,
            default                                => self::OTRO,
        };
    }

    /** Compara sin importar espacios, saltos de línea ni el punto y coma final. */
    private function normalizar(?string $sql): string
    {
        return rtrim(preg_replace('/\s+/', ' ', trim((string) $sql)), '; ');
    }

    private function mostrarResumen(array $elementos): void
    {
        $this->table(
            ['Clave', 'Widget (id)', 'Entrenamiento (id)', 'SQL del widget', 'SQL del entrenamiento'],
            array_map(fn ($e) => [
                $e['clave'],
                $e['widget_id'],
                $e['training_id'],
                $e['estado_widget'],
                $e['estado_training'],
            ], $elementos)
        );
    }

    private function mostrarReemplazo(array $e): void
    {
        $this->newLine();
        $this->line("<comment>«{$e['clave']}»</comment> — widget {$e['widget_id']}, entrenamiento {$e['training_id']}");
        $this->line('<fg=red>SQL actual:</>');
        $this->line($e['sql_original']);
        $this->line('<fg=green>SQL que lo reemplazaría:</>');
        $this->line($e['sql_optimizado']);
    }

    private function mostrarSqlDesconocido(array $e): void
    {
        $this->newLine();
        $this->line("<comment>«{$e['clave']}»</comment> — SQL no reconocido:");

        if ($e['estado_widget'] === self::OTRO) {
            $this->line("Widget {$e['widget_id']}: " . $e['sql_widget']);
        }
        if ($e['estado_training'] === self::OTRO) {
            $this->line("Entrenamiento {$e['training_id']}: " . $e['sql_training']);
        }
    }
}
