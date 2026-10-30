<?php

namespace App\Console\Commands\Silabos;

use App\Models\Course;
use App\Models\Syllabus;
use App\Services\Syllabus\CourseLinkService;
use Illuminate\Console\Command;

/**
 * Envía a procesar los sílabos pendientes de vinculación cuyo código ya tiene curso.
 *
 * La página "Vinculación de cursos" lo hace sola al vincular. Este comando es para cuando
 * los códigos se asignan por SQL (02b_observatorio_asignar_codigos_por_nombre.sql o el
 * archivo de silabos:exportar-vinculaciones). Necesita el worker de la cola corriendo.
 */
class SilabosProcesarVinculados extends Command
{
    protected $signature = 'silabos:procesar-vinculados
        {--dry-run : Solo mostrar qué sílabos se enviarían a procesar}';

    protected $description = 'Envía a procesar los sílabos pendientes de vinculación cuyo código ya tiene curso';

    public function handle(CourseLinkService $links): int
    {
        $pendientes = Syllabus::pendingLink()->current()->orderBy('course_code')
            ->get(['id', 'external_model', 'external_id', 'course_code', 'course_name']);
        $cursos = Course::whereIn('code', $pendientes->pluck('course_code')->unique())->get(['id', 'name', 'code'])->keyBy('code');

        $listos = $pendientes->filter(fn ($s) => $cursos->has($s->course_code));
        $siguen = $pendientes->count() - $listos->count();

        if ($listos->isEmpty()) {
            $this->info("Ningún pendiente tiene curso todavía ({$pendientes->count()} siguen pendientes de vinculación).");
            return self::SUCCESS;
        }

        $this->table(['Sílabo', 'Código', 'Nombre en silabos_v2', 'Curso del observatorio'], $listos->map(fn ($s) => [
            "{$s->external_model}:{$s->external_id}", $s->course_code, $s->course_name,
            $cursos[$s->course_code]->id . ' ' . $cursos[$s->course_code]->name,
        ])->all());

        if ($this->option('dry-run')) {
            $this->warn("--dry-run: no se envió nada. Se enviarían {$listos->count()}; {$siguen} seguirían pendientes.");
            return self::SUCCESS;
        }

        $encolados = 0;
        foreach ($listos->pluck('course_code')->unique() as $code) {
            $encolados += count($links->processPendingWithCode($code));
        }

        $this->info("Enviados a procesar: {$encolados}. Siguen pendientes de vinculación: {$siguen}.");

        return self::SUCCESS;
    }
}
