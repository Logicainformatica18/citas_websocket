<?php

namespace App\Console\Commands\Silabos;

use App\Models\Course;
use App\Services\Syllabus\CourseLinkService;
use Illuminate\Console\Command;

/**
 * Llena courses.code desde el reporte de correspondencia (docs/correspondencia-cursos.csv).
 *
 * Solo toma las filas de tipo "exacto" sin aviso en "observacion". Nunca sobrescribe:
 * se salta los cursos que ya tienen código y los códigos que ya tiene otro curso.
 * Si había sílabos pendientes de vinculación con esos códigos, los envía a procesar.
 */
class SilabosAsignarCodigos extends Command
{
    protected $signature = 'silabos:asignar-codigos
        {csv=docs/correspondencia-cursos.csv : Reporte de correspondencia (separado por ;)}
        {--dry-run : Muestra lo que haría sin guardar nada}';

    protected $description = 'Asigna courses.code a los cursos con correspondencia exacta del reporte';

    public function handle(CourseLinkService $links): int
    {
        $path = base_path($this->argument('csv'));
        if (!is_file($path)) {
            $this->error("No existe {$path}");
            return self::FAILURE;
        }

        $rows = $this->readCsv($path);
        $candidates = array_filter($rows, fn ($r) => ($r['tipo'] ?? '') === 'exacto'
            && trim($r['observacion'] ?? '') === ''
            && trim($r['codigo_propuesto'] ?? '') !== '');

        $dry = (bool) $this->option('dry-run');
        $this->info(($dry ? '[dry-run] ' : '') . count($candidates) . ' fila(s) exactas sin aviso de ' . count($rows) . '.');

        $assigned = [];
        $skipped = [];

        foreach ($candidates as $r) {
            $code = trim($r['codigo_propuesto']);
            $course = Course::find((int) $r['course_id']);

            $reason = match (true) {
                !$course => 'el curso ya no existe',
                $course->code === $code => 'ya tenía este código',
                $course->code !== null => "ya tiene el código {$course->code}",
                isset($assigned[$code]) || Course::where('code', $code)->exists() => 'el código ya está en otro curso',
                default => null,
            };

            if ($reason) {
                $skipped[] = [$r['course_id'], $r['curso_observatorio'], $code, $reason];
                continue;
            }

            if (!$dry) {
                $course->update(['code' => $code]);
            }
            $assigned[$code] = [$course->id, $course->name, $code];
        }

        $this->table(['course_id', 'Curso', 'Código'], array_values($assigned));
        if ($skipped) {
            $this->warn('Omitidos:');
            $this->table(['course_id', 'Curso', 'Código', 'Motivo'], $skipped);
        }

        $queued = [];
        if (!$dry) {
            foreach (array_keys($assigned) as $code) {
                array_push($queued, ...$links->processPendingWithCode((string) $code));
            }
        }

        $this->info(sprintf(
            '%s%d código(s) asignados, %d omitidos, %d sílabo(s) pendientes enviados a procesar.',
            $dry ? '[dry-run] ' : '',
            count($assigned),
            count($skipped),
            count($queued)
        ));

        return self::SUCCESS;
    }

    private function readCsv(string $path): array
    {
        $f = fopen($path, 'r');
        $header = fgetcsv($f, 0, ';');
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); // BOM

        $rows = [];
        while (($line = fgetcsv($f, 0, ';')) !== false) {
            if (count($line) === count($header)) {
                $rows[] = array_combine($header, $line);
            }
        }
        fclose($f);

        return $rows;
    }
}
