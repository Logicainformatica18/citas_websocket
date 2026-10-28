<?php

namespace App\Console\Commands\Silabos;

use App\Models\Career;
use App\Models\Syllabus;
use App\Services\Syllabus\SyllabusIngestService;
use Illuminate\Console\Command;

/**
 * Cuántos sílabos de silabos_v2 hay por carrera: procesados, ignorados y pendientes de vinculación.
 *
 * Los procesados se separan por la condición que los hizo entrar:
 *  a) el sílabo trae la carrera (mapeada en config('silabos_v2.career_map'));
 *  b) el sílabo no trae ninguna carrera mapeada, pero su curso ya pertenece a la carrera.
 *     Cuenta en las carreras mapeadas de su curso.
 *
 * Cuenta solo los vigentes (no reemplazados). Un sílabo con varias carreras suma en cada una.
 * Las carreras de silabos_v2 que no están en el mapeo se muestran aparte.
 */
class SilabosReporteCarreras extends Command
{
    protected $signature = 'silabos:reporte-carreras
        {--modelo= : Solo un modelo (isil o scl)}';

    protected $description = 'Sílabos de silabos_v2 por carrera: procesados (por condición), ignorados y pendientes de vinculación';

    private const COLUMNS = [
        'por_carrera' => 'Procesados (a) carrera del sílabo',
        'por_curso' => 'Procesados (b) curso en la carrera',
        'ignored' => 'Ignorados',
        Syllabus::STATUS_PENDING_LINK => 'Pend. vinculación',
        'otros' => 'Otros (en cola / error)',
    ];

    public function handle(): int
    {
        $map = config('silabos_v2.career_map', []);
        $names = Career::whereIn('slug', array_values($map))->pluck('name', 'slug');
        $keyBySlug = array_flip($map);

        $query = Syllabus::where('source', Syllabus::SOURCE_SILABOS_V2)
            ->current()
            ->with('course.careers:id,slug');
        if ($modelo = $this->option('modelo')) {
            $query->where('external_model', $modelo);
        }

        $mapped = [];
        $unmapped = [];
        $withoutCareers = $this->emptyRow();
        $totals = $this->emptyRow();

        foreach ($query->get(['id', 'external_model', 'status', 'careers', 'course_id']) as $s) {
            $byCareer = SyllabusIngestService::hasMappedCareer([
                'external_model' => $s->external_model,
                'careers' => $s->careers ?? [],
            ]);

            $column = match (true) {
                $s->status === 'processed' => $byCareer ? 'por_carrera' : 'por_curso',
                array_key_exists($s->status, self::COLUMNS) => $s->status,
                default => 'otros',
            };
            $totals[$column]++;

            // (b): cuenta en las carreras mapeadas del curso, no en las del sílabo.
            if ($column === 'por_curso') {
                foreach ($s->course?->careers ?? [] as $career) {
                    if (isset($keyBySlug[$career->slug])) {
                        $key = $keyBySlug[$career->slug];
                        $mapped[$key] ??= $this->emptyRow();
                        $mapped[$key][$column]++;
                    }
                }
                continue;
            }

            $careers = collect($s->careers ?? [])->pluck('nombre', 'codigo');
            if ($careers->isEmpty()) {
                $withoutCareers[$column]++;
                continue;
            }

            foreach ($careers as $codigo => $nombre) {
                $key = "{$s->external_model}:{$codigo}";
                if (isset($map[$key])) {
                    $mapped[$key] ??= $this->emptyRow();
                    $mapped[$key][$column]++;
                } else {
                    $unmapped[$key] ??= ['nombre' => $nombre] + $this->emptyRow();
                    $unmapped[$key][$column]++;
                }
            }
        }

        $this->info('Carreras del observatorio');
        $rows = [];
        foreach ($map as $key => $slug) {
            $rows[] = [$names[$slug] ?? "{$slug} (no existe)", $key, ...array_values($mapped[$key] ?? $this->emptyRow())];
        }
        $this->table(['Carrera', 'silabos_v2', ...array_values(self::COLUMNS)], $rows);

        if ($unmapped) {
            ksort($unmapped);
            $this->newLine();
            $this->info('Carreras de silabos_v2 sin mapeo (sus sílabos solo se procesan por la condición a de otra carrera o por la b)');
            $this->table(
                ['silabos_v2', 'Nombre', ...array_values(self::COLUMNS)],
                collect($unmapped)->map(fn ($r, $key) => [$key, $r['nombre'], ...array_values(array_diff_key($r, ['nombre' => 1]))])->values()->all()
            );
        }

        $this->newLine();
        $this->table(['', ...array_values(self::COLUMNS)], [
            ['Sin carreras en silabos_v2', ...array_values($withoutCareers)],
            ['Total de sílabos vigentes', ...array_values($totals)],
        ]);

        return self::SUCCESS;
    }

    private function emptyRow(): array
    {
        return array_fill_keys(array_keys(self::COLUMNS), 0);
    }
}
