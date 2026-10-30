<?php

namespace App\Console\Commands\Silabos;

use App\Models\Course;
use App\Models\Syllabus;
use App\Services\Syllabus\SyllabusIngestService;
use Illuminate\Console\Command;

/**
 * Solo lectura. Exporta como SQL las vinculaciones hechas en esta base para replicarlas en otra
 * (por ejemplo, de local a producción):
 *
 *  1. Códigos de curso asignados fuera de la lista de 02_asignar_codigos.sql (desde la página
 *     "Vinculación de cursos" o el formulario de cursos). Cada UPDATE busca el curso por id y
 *     nombre exactos (o solo por nombre con --por-nombre) y no pisa códigos existentes.
 *  2. Sílabos marcados "No vincular" (ignorados que sí tenían una carrera mapeada: los ignorados
 *     automáticamente no tienen ninguna). Se identifican por (modelo, silabo_id de silabos_v2),
 *     que es el mismo en las dos bases, y solo cambian si allá siguen pendientes de vinculación.
 *
 * En la otra base, después de ejecutar el SQL: `php artisan silabos:procesar-vinculados`.
 */
class SilabosExportarVinculaciones extends Command
{
    protected $signature = 'silabos:exportar-vinculaciones
        {--salida= : Archivo de salida (por defecto storage/app/vinculaciones/vinculaciones-FECHA.sql)}
        {--lista=docs/sql/02_asignar_codigos.sql : Script con los códigos que ya se asignan en bloque (no se exportan)}
        {--por-nombre : Buscar los cursos solo por nombre exacto, no por id y nombre}';

    protected $description = 'Exporta como SQL los códigos de curso asignados y los "No vincular" hechos en esta base';

    public function handle(): int
    {
        $lista = base_path($this->option('lista'));
        if (!is_file($lista)) {
            $this->error("No existe {$lista}");
            return self::FAILURE;
        }
        preg_match_all("/SET c\\.code = '((?:[^']|'')*)'.*?WHERE c\\.id = (\\d+)/", file_get_contents($lista), $m, PREG_SET_ORDER);
        $enBloque = collect($m)->map(fn ($x) => $x[2] . '|' . str_replace("''", "'", $x[1]))->flip();

        $cursos = Course::whereNotNull('code')->orderBy('id')->get(['id', 'name', 'code'])
            ->reject(fn ($c) => $enBloque->has($c->id . '|' . $c->code))
            ->values();

        $noVincular = Syllabus::where('source', Syllabus::SOURCE_SILABOS_V2)
            ->where('status', 'ignored')
            ->orderBy('external_model')->orderBy('external_id')
            ->get(['id', 'external_model', 'external_id', 'course_code', 'course_name', 'careers'])
            ->filter(fn ($s) => SyllabusIngestService::hasMappedCareer([
                'external_model' => $s->external_model,
                'careers' => $s->careers ?? [],
            ]))
            ->values();

        $sql = $this->sql($cursos, $noVincular, (bool) $this->option('por-nombre'));

        $salida = $this->option('salida') ?: storage_path('app/vinculaciones/vinculaciones-' . now()->format('Ymd_His') . '.sql');
        if (!is_dir(dirname($salida))) {
            mkdir(dirname($salida), 0775, true);
        }
        file_put_contents($salida, $sql);

        $this->info("Códigos de curso: {$cursos->count()} · \"No vincular\": {$noVincular->count()}");
        $this->info("SQL escrito en: {$salida}");
        $this->line('En la otra base: ejecutar el archivo y después `php artisan silabos:procesar-vinculados`.');

        return self::SUCCESS;
    }

    private function sql($cursos, $noVincular, bool $porNombre): string
    {
        $q = fn (?string $s) => "'" . str_replace(['\\', "'"], ['\\\\', "''"], (string) $s) . "'";
        $busqueda = $porNombre ? 'nombre exacto' : 'id y nombre exactos';
        $fecha = now()->format('Y-m-d H:i');
        $base = config('database.connections.' . config('database.default') . '.database');

        $out = [];
        $out[] = <<<SQL
-- =====================================================================
-- Vinculaciones exportadas de la base {$base} el {$fecha}
-- (php artisan silabos:exportar-vinculaciones).
--
-- BASE DESTINO: la del observatorio (citas_websocket) donde se replican.
-- Selecciónala como predeterminada; el script no usa USE. Ejecutar el
-- archivo completo con auto-commit (Workbench: Ctrl+Shift+Enter).
--
-- Ejecutar DESPUÉS de la carga inicial en esa base (para que existan los
-- sílabos pendientes de vinculación) y luego, en el servidor:
--   php artisan silabos:procesar-vinculados
-- que envía a procesar los pendientes cuyo código ya tiene curso.
--
-- Seguro de correr dos veces: no pisa códigos existentes ni cambia
-- sílabos que ya no estén pendientes.
-- =====================================================================

SET NAMES utf8mb4;

SQL;

        $out[] = "-- ---------------------------------------------------------------------";
        $out[] = "-- 1. Códigos de curso ({$cursos->count()}), buscando el curso por {$busqueda}.";
        $out[] = "--    Si el curso no existe, ya tiene código u otro curso tiene ese código,";
        $out[] = "--    el UPDATE afecta 0 filas; la consulta del final los lista.";
        $out[] = "-- ---------------------------------------------------------------------";
        $out[] = '';
        foreach ($cursos as $c) {
            $where = $porNombre
                ? "c.name COLLATE utf8mb4_bin = {$q($c->name)}"
                : "c.id = {$c->id} AND c.name COLLATE utf8mb4_bin = {$q($c->name)}";
            $out[] = "UPDATE courses c LEFT JOIN courses o ON o.code = {$q($c->code)} SET c.code = {$q($c->code)}, c.updated_at = NOW() "
                . "WHERE {$where} AND c.code IS NULL AND o.id IS NULL;";
        }

        $out[] = '';
        $out[] = "-- ---------------------------------------------------------------------";
        $out[] = "-- 2. \"No vincular\" ({$noVincular->count()}): sílabos que quedan ignorados.";
        $out[] = "--    Solo cambian si siguen pendientes de vinculación.";
        $out[] = "-- ---------------------------------------------------------------------";
        $out[] = '';
        foreach ($noVincular as $s) {
            $out[] = "UPDATE syllabus SET status = 'ignored', updated_at = NOW() WHERE source = 'silabos_v2' "
                . "AND external_model = {$q($s->external_model)} AND external_id = {$s->external_id} AND status = 'pending_link';"
                . "  -- {$s->course_code} " . str_replace(["\n", "\r"], ' ', (string) $s->course_name);
        }

        $out[] = '';
        $out[] = "-- ---------------------------------------------------------------------";
        $out[] = "-- 3. Comprobación (solo lectura): cursos de la sección 1 que quedaron sin";
        $out[] = "--    ese código (no encontrados o en conflicto). Esperado: ninguna fila.";
        $out[] = "-- ---------------------------------------------------------------------";
        $out[] = '';
        if ($cursos->isEmpty()) {
            $out[] = '-- (sin códigos que comprobar)';
        } else {
            $filas = $cursos->values()->map(fn ($c, $i) => ($i === 0
                ? "    SELECT {$c->id} AS id_origen, {$q($c->code)} AS code, {$q($c->name)} COLLATE utf8mb4_bin AS name"
                : "    UNION ALL SELECT {$c->id}, {$q($c->code)}, {$q($c->name)}"))->implode("\n");
            $out[] = "SELECT l.id_origen, l.code, l.name,";
            $out[] = "       (SELECT GROUP_CONCAT(c.id) FROM courses c WHERE c.code = l.code) AS curso_con_ese_codigo";
            $out[] = "FROM (\n{$filas}\n) l";
            $out[] = "WHERE NOT EXISTS (SELECT 1 FROM courses c WHERE c.code = l.code AND c.name COLLATE utf8mb4_bin = l.name);";
        }
        $out[] = '';

        return implode("\n", $out);
    }
}
