<?php

namespace Tests\Feature;

use App\Jobs\ProcessIngestedSyllabusJob;
use App\Models\Career;
use App\Models\Course;
use App\Models\Syllabus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SilabosCommandsTest extends TestCase
{
    use RefreshDatabase;

    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->csv = 'storage/framework/testing/correspondencia-' . getmypid() . '.csv';
    }

    protected function tearDown(): void
    {
        @unlink(base_path($this->csv));
        parent::tearDown();
    }

    // ── silabos:asignar-codigos ─────────────────────────────────

    public function test_asigna_solo_exactos_sin_aviso_y_sin_sobrescribir(): void
    {
        Queue::fake();
        $exacto = Course::create(['name' => 'Redes I']);
        $conAviso = Course::create(['name' => 'Redes II']);
        $parecido = Course::create(['name' => 'Redes III']);
        $yaTiene = Course::create(['name' => 'Redes IV', 'code' => 'R-9']);
        $codigoOcupado = Course::create(['name' => 'Redes V']);
        Course::create(['name' => 'Otro', 'code' => 'R-5']);
        $pendiente = $this->syllabus('R-1', Syllabus::STATUS_PENDING_LINK);

        $this->writeCsv([
            [$exacto->id, 'Redes I', 'exacto', 'R-1', ''],
            [$conAviso->id, 'Redes II', 'exacto', 'R-2', 'código propuesto también para curso(s) 99'],
            [$parecido->id, 'Redes III', 'parecido', 'R-3', ''],
            [$yaTiene->id, 'Redes IV', 'exacto', 'R-4', ''],
            [$codigoOcupado->id, 'Redes V', 'exacto', 'R-5', ''],
        ]);

        $this->artisan('silabos:asignar-codigos', ['csv' => $this->csv])->assertSuccessful();

        $this->assertSame('R-1', $exacto->fresh()->code);
        $this->assertNull($conAviso->fresh()->code);
        $this->assertNull($parecido->fresh()->code);
        $this->assertSame('R-9', $yaTiene->fresh()->code);
        $this->assertNull($codigoOcupado->fresh()->code);

        // El pendiente con ese código se envía a procesar.
        $this->assertSame('pending', $pendiente->fresh()->status);
        Queue::assertPushed(ProcessIngestedSyllabusJob::class, fn ($job) => $job->syllabusId === $pendiente->id);
    }

    public function test_asignar_codigos_en_dry_run_no_guarda(): void
    {
        $course = Course::create(['name' => 'Redes I']);
        $this->writeCsv([[$course->id, 'Redes I', 'exacto', 'R-1', '']]);

        $this->artisan('silabos:asignar-codigos', ['csv' => $this->csv, '--dry-run' => true])->assertSuccessful();

        $this->assertNull($course->fresh()->code);
    }

    // ── silabos:reporte-carreras ────────────────────────────────

    public function test_reporte_por_carrera(): void
    {
        config(['silabos_v2.career_map' => ['isil:CADT' => 'arquitectura-de-datos']]);

        $career = Career::create(['name' => 'Arquitectura de datos', 'slug' => 'arquitectura-de-datos']);
        $course = Course::create(['name' => 'Curso B-1', 'code' => 'B-1']);
        $course->careers()->attach($career->id);

        $this->syllabus('A-1', 'processed', [['codigo' => 'CADT', 'nombre' => 'ARQUITECTURA DE DATOS']]);
        $this->syllabus('A-2', Syllabus::STATUS_PENDING_LINK, [['codigo' => 'CADT', 'nombre' => 'ARQUITECTURA DE DATOS']]);
        $this->syllabus('A-3', 'ignored', [['codigo' => 'CMAR', 'nombre' => 'MARKETING']]);
        $this->syllabus('A-4', 'processed', [['codigo' => 'CADT', 'nombre' => 'ARQUITECTURA DE DATOS']], superseded: true);
        // Condición (b): sin carrera mapeada, entra porque su curso está en la carrera.
        $this->syllabus('B-1', 'processed', [['codigo' => 'CMAR', 'nombre' => 'MARKETING']])
            ->update(['course_id' => $course->id]);

        $columns = ['Procesados (a) carrera del sílabo', 'Procesados (b) curso en la carrera', 'Ignorados', 'Pend. vinculación', 'Otros (en cola / error)'];
        $this->artisan('silabos:reporte-carreras')
            ->expectsTable(['Carrera', 'silabos_v2', ...$columns], [['Arquitectura de datos', 'isil:CADT', 1, 1, 0, 1, 0]])
            ->expectsTable(['silabos_v2', 'Nombre', ...$columns], [['isil:CMAR', 'MARKETING', 0, 0, 1, 0, 0]])
            ->assertSuccessful();
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function writeCsv(array $rows): void
    {
        $f = fopen(base_path($this->csv), 'w');
        fwrite($f, "\xEF\xBB\xBF");
        fputcsv($f, ['course_id', 'curso_observatorio', 'tipo', 'codigo_propuesto', 'observacion'], ';');
        foreach ($rows as $row) {
            fputcsv($f, $row, ';');
        }
        fclose($f);
    }

    private function syllabus(string $code, string $status, array $careers = [], bool $superseded = false): Syllabus
    {
        static $id = 0;
        $id++;

        return Syllabus::create([
            'source' => Syllabus::SOURCE_SILABOS_V2,
            'external_model' => 'isil',
            'external_id' => $id,
            'codigo_familia' => $code,
            'external_version' => 1,
            'periodo' => '202610',
            'periodo_orden' => 202610,
            'course_code' => $code,
            'course_name' => "CURSO {$code}",
            'careers' => $careers,
            'status' => $status,
            'superseded_at' => $superseded ? now() : null,
        ]);
    }
}
