<?php

namespace Tests\Feature;

use App\Jobs\ProcessIngestedSyllabusJob;
use App\Models\Course;
use App\Models\Syllabus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SilabosVinculacionesTest extends TestCase
{
    use RefreshDatabase;

    private string $lista;
    private string $salida;

    protected function setUp(): void
    {
        parent::setUp();
        config(['silabos_v2.career_map' => ['isil:CADT' => 'arquitectura-de-datos']]);

        // Lista de códigos que se asignan en bloque (mismo formato que docs/sql/02_asignar_codigos.sql).
        $this->lista = storage_path('framework/testing/lista-' . getmypid() . '.sql');
        file_put_contents($this->lista,
            "UPDATE courses c LEFT JOIN courses o ON o.code = 'EN-BLOQUE' SET c.code = 'EN-BLOQUE', c.updated_at = NOW() WHERE c.id = 1 AND c.name = 'Curso en bloque' AND c.code IS NULL AND o.id IS NULL;\n");
        $this->salida = storage_path('framework/testing/vinculaciones-' . getmypid() . '.sql');
        @unlink($this->salida);
    }

    protected function tearDown(): void
    {
        @unlink($this->lista);
        @unlink($this->salida);
        parent::tearDown();
    }

    // ── silabos:exportar-vinculaciones ──────────────────────────

    public function test_exporta_los_codigos_fuera_de_la_lista_y_los_no_vincular(): void
    {
        Course::create(['name' => 'Curso en bloque', 'code' => 'EN-BLOQUE']);          // id 1: ya va en 02
        Course::create(['name' => "Diseño d'Interiores", 'code' => '30999']);           // vinculado en la página
        Course::create(['name' => 'Sin código']);
        $this->syllabus(10, 'ignored', [['codigo' => 'CADT']]);                          // "No vincular"
        $this->syllabus(11, 'ignored', [['codigo' => 'CMAR']]);                          // ignorado automático
        $this->syllabus(12, 'pending_link', [['codigo' => 'CADT']]);

        $this->artisan('silabos:exportar-vinculaciones', ['--salida' => $this->salida, '--lista' => $this->relativa($this->lista)])
            ->expectsOutputToContain('Códigos de curso: 1 · "No vincular": 1')
            ->assertSuccessful();

        $sql = file_get_contents($this->salida);
        $this->assertStringContainsString(
            "SET c.code = '30999', c.updated_at = NOW() WHERE c.id = 2 AND c.name COLLATE utf8mb4_bin = 'Diseño d''Interiores' AND c.code IS NULL AND o.id IS NULL;",
            $sql);
        $this->assertStringNotContainsString("'EN-BLOQUE'", $sql);
        $this->assertStringContainsString("AND external_model = 'isil' AND external_id = 10 AND status = 'pending_link';", $sql);
        $this->assertStringNotContainsString('external_id = 11', $sql);
        $this->assertStringNotContainsString('external_id = 12', $sql);
        $this->assertStringContainsString('silabos:procesar-vinculados', $sql);
        $this->assertStringNotContainsString('USE ', $sql);
    }

    public function test_por_nombre_no_usa_el_id(): void
    {
        Course::create(['name' => 'Redes', 'code' => 'R-1']);

        $this->artisan('silabos:exportar-vinculaciones', [
            '--salida' => $this->salida, '--lista' => $this->relativa($this->lista), '--por-nombre' => true,
        ])->assertSuccessful();

        $this->assertStringContainsString("WHERE c.name COLLATE utf8mb4_bin = 'Redes' AND c.code IS NULL", file_get_contents($this->salida));
    }

    public function test_exportar_no_modifica_la_base(): void
    {
        Course::create(['name' => 'Redes', 'code' => 'R-1']);
        $this->syllabus(10, 'ignored', [['codigo' => 'CADT']]);
        $antes = [Course::all()->toArray(), Syllabus::all()->toArray()];

        $this->artisan('silabos:exportar-vinculaciones', ['--salida' => $this->salida, '--lista' => $this->relativa($this->lista)])
            ->assertSuccessful();

        $this->assertSame($antes, [Course::all()->toArray(), Syllabus::all()->toArray()]);
    }

    // ── silabos:procesar-vinculados ─────────────────────────────

    public function test_procesa_solo_los_pendientes_cuyo_codigo_ya_tiene_curso(): void
    {
        Queue::fake();
        Course::create(['name' => 'Redes', 'code' => 'R-1']);
        $listo = $this->syllabus(20, 'pending_link', [['codigo' => 'CADT']], 'R-1');
        $sigue = $this->syllabus(21, 'pending_link', [['codigo' => 'CADT']], 'SIN-CURSO');

        $this->artisan('silabos:procesar-vinculados')
            ->expectsOutputToContain('Enviados a procesar: 1. Siguen pendientes de vinculación: 1.')
            ->assertSuccessful();

        $this->assertSame('pending', $listo->fresh()->status);
        $this->assertSame('pending_link', $sigue->fresh()->status);
        Queue::assertPushed(ProcessIngestedSyllabusJob::class, 1);
    }

    public function test_procesar_vinculados_en_dry_run_no_envia_nada(): void
    {
        Queue::fake();
        Course::create(['name' => 'Redes', 'code' => 'R-1']);
        $listo = $this->syllabus(20, 'pending_link', [['codigo' => 'CADT']], 'R-1');

        $this->artisan('silabos:procesar-vinculados', ['--dry-run' => true])
            ->expectsOutputToContain('Se enviarían 1')
            ->assertSuccessful();

        $this->assertSame('pending_link', $listo->fresh()->status);
        Queue::assertNothingPushed();
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function syllabus(int $externalId, string $status, array $careers, string $code = 'X-1'): Syllabus
    {
        return Syllabus::create([
            'source' => Syllabus::SOURCE_SILABOS_V2, 'external_model' => 'isil', 'external_id' => $externalId,
            'codigo_familia' => $code . '-' . $externalId, 'external_version' => 1, 'course_code' => $code,
            'course_name' => 'CURSO ' . $externalId, 'careers' => $careers, 'raw_text' => 'texto', 'status' => $status,
        ]);
    }

    private function relativa(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), '\\/');
    }
}
