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
 * Los registros se ubican por columnas de negocio y nunca por id, porque los id
 * cambian entre entornos. Antes de escribir se valida todo; si un solo elemento
 * no cuadra, no se modifica nada.
 */
class OptimizarSqlWidgets extends Command
{
    protected $signature = 'observatorio:optimizar-sql-widgets
                            {--dry-run : Muestra qué registros encontró y qué SQL reemplazaría, sin escribir nada}
                            {--rollback : Restaura el SQL anterior desde un respaldo}
                            {--backup= : Archivo de respaldo a restaurar con --rollback (por defecto, el más reciente)}';

    protected $description = 'Aplica (o revierte) los SQL optimizados de los widgets del dashboard IA';

    private const CARPETA_SQL = 'database/sql/widgets';

    private const PREFIJO_RESPALDO = 'sql-widgets-';

    private const ORIGINAL   = 'ORIGINAL';
    private const OPTIMIZADO = 'OPTIMIZADO';
    private const SIN_SQL    = 'SIN SQL PROPIO';
    private const OTRO       = 'DESCONOCIDO';

    public function handle(): int
    {
        try {
            return $this->option('rollback') ? $this->revertir() : $this->aplicar();
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

        if (!is_array($respaldo) || empty($respaldo['elementos'])) {
            throw new RuntimeException("El respaldo {$archivo} no tiene el formato esperado.");
        }

        $this->info('Respaldo: ' . basename($archivo) . " (creado el {$respaldo['creado_en']})");

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
        $carpeta = storage_path('app' . DIRECTORY_SEPARATOR . 'backups');
        File::ensureDirectoryExists($carpeta);

        $archivo = $carpeta . DIRECTORY_SEPARATOR . self::PREFIJO_RESPALDO . now()->format('Ymd_His') . '.json';

        $contenido = [
            'creado_en'     => now()->toDateTimeString(),
            'entorno'       => app()->environment(),
            'base_de_datos' => DB::connection()->getDatabaseName(),
            'elementos'     => array_values(array_map(fn ($e) => [
                'clave'                 => $e['clave'],
                'dashboard_slug'        => $e['dashboard_slug'],
                'widget_title'          => $e['widget_title'],
                'widget_id'             => $e['widget_id'],
                'training_id'           => $e['training_id'],
                'widget_sql_anterior'   => $e['sql_widget'],
                'training_sql_anterior' => $e['sql_training'],
            ], $elementos)),
        ];

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
