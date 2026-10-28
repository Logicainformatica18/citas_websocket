<?php

namespace Tests\Feature;

use App\Events\SyllabusProcessed;
use App\Jobs\ProcessIngestedSyllabusJob;
use App\Models\Career;
use App\Models\Course;
use App\Models\Syllabus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyllabusIngestTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'clave-de-prueba';
    private string $reviewLog;

    protected function setUp(): void
    {
        parent::setUp();

        config(['silabos_v2.ingest_key' => self::KEY, 'silabos_v2.tbd_position' => 'last']);

        $this->reviewLog = storage_path('framework/testing/silabos-revision-' . getmypid() . '.log');
        @unlink($this->reviewLog);
        config(['logging.channels.silabos_revision.path' => $this->reviewLog]);

        Event::fake([SyllabusProcessed::class]);
        $this->fakeOpenAi(['PHP', 'SQL'], [['nombre' => 'Laravel', 'tipo' => 'framework']], ['Scrum']);
    }

    protected function tearDown(): void
    {
        @unlink($this->reviewLog);
        parent::tearDown();
    }

    // ── Autenticación ───────────────────────────────────────────

    public function test_rechaza_sin_cabecera_de_clave(): void
    {
        $this->postJson('/api/silabos/ingest', $this->payload())->assertStatus(401);
        $this->assertSame(0, Syllabus::count());
    }

    public function test_rechaza_clave_incorrecta(): void
    {
        $this->ingest($this->payload(), 'otra-clave')->assertStatus(401);
        $this->assertSame(0, Syllabus::count());
    }

    public function test_rechaza_todo_si_la_clave_no_esta_configurada(): void
    {
        config(['silabos_v2.ingest_key' => null]);

        $this->postJson('/api/silabos/ingest', $this->payload())->assertStatus(401);
        $this->ingest($this->payload(), '')->assertStatus(401);
    }

    // ── Validación ──────────────────────────────────────────────

    public function test_payload_invalido_devuelve_422_y_no_guarda_nada(): void
    {
        $payload = $this->payload();
        unset($payload['curso']['codigo'], $payload['contenido']['texto_plano']);
        $payload['modelo'] = 'otro';

        $this->ingest($payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['curso.codigo', 'contenido.texto_plano', 'modelo']);

        $this->assertSame(0, Syllabus::count());
        Http::assertNothingSent();
    }

    public function test_payload_invalido_devuelve_json_aunque_no_se_pida(): void
    {
        $this->post('/api/silabos/ingest', [], ['X-Api-Key' => self::KEY])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    // ── Ingesta válida ──────────────────────────────────────────

    public function test_payload_valido_se_guarda_procesa_y_actualiza_el_curso(): void
    {
        $this->ingest($this->payload())
            ->assertStatus(202)
            ->assertJson(['status' => 'queued']);

        $syllabus = Syllabus::sole();
        $this->assertSame('processed', $syllabus->status);
        $this->assertSame('silabos_v2', $syllabus->source);
        $this->assertSame('DESARROLLO WEB', $syllabus->structured_data['curso']);
        $this->assertSame('Curso detectado', $syllabus->structured_data['curso_detectado']);
        $this->assertNotEmpty($syllabus->content_hash);
        $this->assertNull($syllabus->superseded_at);

        $course = Course::where('code', 'IS-101')->sole();
        $this->assertSame($course->id, $syllabus->course_id);
        $this->assertEqualsCanonicalizing(['PHP', 'SQL'], $course->languages->pluck('name')->all());
        $this->assertSame(['Laravel'], $course->technologies->pluck('name')->all());
        $this->assertSame(['Scrum'], $course->methodologies->pluck('name')->all());

        Event::assertDispatched(SyllabusProcessed::class);
    }

    public function test_enlaza_un_curso_existente_por_nombre_y_le_asigna_el_codigo(): void
    {
        $existing = Course::create(['name' => 'Desarrollo Web']);

        $this->ingest($this->payload())->assertStatus(202);

        $this->assertSame('IS-101', $existing->fresh()->code);
        $this->assertSame(1, Course::count());
    }

    public function test_si_la_extraccion_falla_no_queda_processed(): void
    {
        Queue::fake();
        $this->ingest($this->payload())->assertStatus(202);
        Queue::assertPushed(ProcessIngestedSyllabusJob::class);

        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'no es json']]]])]);

        $syllabus = Syllabus::sole();
        $job = new ProcessIngestedSyllabusJob($syllabus->id);

        try {
            $job->handle(app(\App\Services\Syllabus\SyllabusExtractionService::class));
            $this->fail('Debió lanzar excepción');
        } catch (\Exception) {
            $job->failed(new \Exception('x'));
        }

        $this->assertSame('failed', $syllabus->fresh()->status);
        $this->assertSame(0, Course::count());
    }

    // ── Idempotencia y versiones ────────────────────────────────

    public function test_duplicado_con_el_mismo_contenido_no_se_reprocesa(): void
    {
        $this->ingest($this->payload())->assertStatus(202);
        $this->ingest($this->payload())->assertStatus(200)->assertJson(['status' => 'unchanged']);

        $this->assertSame(1, Syllabus::count());
        Http::assertSentCount(1);
    }

    public function test_mismo_silabo_con_contenido_nuevo_se_actualiza_sin_duplicarse(): void
    {
        $this->ingest($this->payload())->assertStatus(202);

        $this->fakeOpenAi(['Python'], [], []);
        $this->ingest($this->payload(contenido: 'Contenido corregido del sílabo con Python y pandas para análisis de datos.'))
            ->assertStatus(202);

        $this->assertSame(1, Syllabus::count());
        $this->assertSame(['Python'], Course::sole()->languages->pluck('name')->all());
    }

    public function test_version_nueva_reemplaza_a_la_anterior(): void
    {
        $this->ingest($this->payload(id: 10, version: 1))->assertStatus(202);

        $this->fakeOpenAi(['Python'], [], []);
        $this->ingest($this->payload(id: 11, version: 2))->assertStatus(202);

        $old = Syllabus::where('external_id', 10)->sole();
        $new = Syllabus::where('external_id', 11)->sole();
        $this->assertNotNull($old->superseded_at);
        $this->assertSame($new->id, $old->superseded_by);
        $this->assertNull($new->superseded_at);
        $this->assertSame(['Python'], Course::sole()->languages->pluck('name')->all());
    }

    public function test_version_menor_se_guarda_reemplazada_sin_tocar_pivotes(): void
    {
        $this->ingest($this->payload(id: 11, version: 2))->assertStatus(202);

        $this->fakeOpenAi(['COBOL'], [], []);
        $this->ingest($this->payload(id: 10, version: 1))
            ->assertStatus(200)
            ->assertJson(['status' => 'superseded']);

        $old = Syllabus::where('external_id', 10)->sole();
        $this->assertSame('superseded', $old->status);
        $this->assertNotNull($old->superseded_at);
        $this->assertNull($old->structured_data);
        Http::assertNothingSent();
        $this->assertEqualsCanonicalizing(['PHP', 'SQL'], Course::sole()->languages->pluck('name')->all());
    }

    public function test_empate_de_version_lo_desempata_el_periodo_y_no_la_fecha(): void
    {
        $this->ingest($this->payload(id: 20, version: 0, periodo: '202420', aprobadoAt: '2026-01-01T10:00:00-05:00'))
            ->assertStatus(202);
        // Periodo anterior pero aprobado después: no debe ganar.
        $this->ingest($this->payload(id: 30, version: 0, periodo: '202310', aprobadoAt: '2026-06-01T10:00:00-05:00'))
            ->assertStatus(200)
            ->assertJson(['status' => 'superseded']);

        $this->assertNull(Syllabus::where('external_id', 20)->value('superseded_at'));
    }

    public function test_empate_total_lo_desempata_el_id(): void
    {
        $this->ingest($this->payload(id: 40, version: 1, periodo: 'TBD'))->assertStatus(202);
        $this->ingest($this->payload(id: 41, version: 1, periodo: 'TBD'))->assertStatus(202);

        $this->assertNotNull(Syllabus::where('external_id', 40)->value('superseded_at'));
        $this->assertNull(Syllabus::where('external_id', 41)->value('superseded_at'));
    }

    public function test_empate_de_version_con_contenido_distinto_va_al_log_de_revision(): void
    {
        $this->ingest($this->payload(id: 50, version: 0, periodo: '202310'))->assertStatus(202);
        $this->ingest($this->payload(id: 51, version: 0, periodo: '202420', contenido: 'Otro contenido distinto del sílabo, con Java y Spring Boot.'))
            ->assertStatus(202);

        $this->assertStringContainsString('Empate de versión con contenido distinto', $this->reviewLogContents());
    }

    public function test_empate_de_version_con_el_mismo_contenido_no_va_al_log(): void
    {
        $this->ingest($this->payload(id: 50, version: 0, periodo: '202310'))->assertStatus(202);
        $this->ingest($this->payload(id: 51, version: 0, periodo: '202420'))->assertStatus(202);

        $this->assertStringNotContainsString('Empate de versión', $this->reviewLogContents());
    }

    public function test_version_que_baja_en_periodo_posterior_va_al_log_de_revision(): void
    {
        $this->ingest($this->payload(id: 2316, version: 1, periodo: '202520'))->assertStatus(202);
        $this->ingest($this->payload(id: 3847, version: 0, periodo: '202610'))
            ->assertJson(['status' => 'superseded']);

        $this->assertStringContainsString('Versión que baja en un periodo posterior', $this->reviewLogContents());
    }

    public function test_isil_no_reemplaza_a_scl_aunque_compartan_codigo_familia(): void
    {
        $this->ingest($this->payload(id: 60, version: 1, modelo: 'scl'))->assertStatus(202);
        $this->ingest($this->payload(id: 60, version: 5, modelo: 'isil'))->assertStatus(202);

        $this->assertSame(2, Syllabus::count());
        $this->assertSame(0, Syllabus::whereNotNull('superseded_at')->count());
    }

    public function test_si_el_vigente_baja_de_version_el_siguiente_vuelve_a_ser_vigente(): void
    {
        $this->ingest($this->payload(id: 70, version: 1))->assertStatus(202);
        $this->ingest($this->payload(id: 71, version: 2))->assertStatus(202);

        $this->fakeOpenAi(['Go'], [], []);
        $this->ingest($this->payload(id: 71, version: 0, contenido: 'Versión corregida a la baja del sílabo, con Go y gRPC.'))
            ->assertJson(['status' => 'superseded']);

        $this->assertNull(Syllabus::where('external_id', 70)->value('superseded_at'));
        $this->assertNotNull(Syllabus::where('external_id', 71)->value('superseded_at'));
        $this->assertSame(['Go'], Course::sole()->languages->pluck('name')->all());
    }

    // ── Convivencia con la carga por PDF ────────────────────────

    public function test_la_ingesta_reemplaza_los_pdf_del_mismo_curso(): void
    {
        $pdf = Syllabus::create([
            'filename' => 'web.pdf', 'path' => 'syllabus/web.pdf', 'status' => 'processed',
            'structured_data' => ['curso' => 'Desarrollo Web', 'lenguajes' => ['PHP']],
        ]);
        $otherPdf = Syllabus::create([
            'filename' => 'redes.pdf', 'path' => 'syllabus/redes.pdf', 'status' => 'processed',
            'structured_data' => ['curso' => 'Redes I'],
        ]);

        $this->ingest($this->payload())->assertStatus(202);

        $this->assertSame(Syllabus::where('source', 'silabos_v2')->value('id'), $pdf->fresh()->superseded_by);
        $this->assertNull($otherPdf->fresh()->superseded_at);
    }

    public function test_un_pdf_nuevo_no_pisa_los_pivotes_de_un_curso_con_silabo_de_silabos_v2(): void
    {
        $this->ingest($this->payload())->assertStatus(202);

        $this->fakeOpenAi(['COBOL'], [], [], curso: 'Desarrollo Web');
        $pdf = Syllabus::create(['filename' => 'viejo.pdf', 'path' => 'syllabus/viejo.pdf', 'raw_text' => 'texto del pdf']);
        app(\App\Services\Syllabus\SyllabusExtractionService::class)->process($pdf);

        $this->assertNotNull($pdf->fresh()->superseded_at);
        $this->assertEqualsCanonicalizing(['PHP', 'SQL'], Course::sole()->languages->pluck('name')->all());
    }

    public function test_la_carga_por_pdf_sigue_funcionando_y_marca_processed_tras_los_pivotes(): void
    {
        $this->fakeOpenAi(['Java'], [], ['Kanban'], curso: 'programación orientada a objetos');
        $pdf = Syllabus::create(['filename' => 'poo.pdf', 'path' => 'syllabus/poo.pdf', 'raw_text' => 'texto del pdf']);

        app(\App\Services\Syllabus\SyllabusExtractionService::class)->process($pdf);

        $pdf->refresh();
        $course = Course::sole();
        $this->assertSame('processed', $pdf->status);
        $this->assertSame('upload', $pdf->source);
        $this->assertSame('Programacion Orientada A Objetos', $course->name);
        $this->assertNull($course->code);
        $this->assertSame(['Java'], $course->languages->pluck('name')->all());
    }

    // ── Carreras ────────────────────────────────────────────────

    public function test_vincula_solo_las_carreras_mapeadas_y_registra_las_demas(): void
    {
        $career = Career::create(['name' => 'Ciberseguridad', 'slug' => 'ciberseguridad']);
        config(['silabos_v2.career_map' => ['isil:CSEG' => 'ciberseguridad']]);

        $payload = $this->payload();
        $payload['carreras'] = [
            ['codigo' => 'CSEG', 'nombre' => 'CIBERSEGURIDAD'],
            ['codigo' => 'CMAR', 'nombre' => 'MARKETING'],
        ];
        $this->ingest($payload)->assertStatus(202);

        $this->assertSame([$career->id], Course::sole()->careers->pluck('id')->all());
        $this->assertStringContainsString('isil:CMAR', $this->reviewLogContents());
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function ingest(array $payload, ?string $key = self::KEY)
    {
        return $this->postJson('/api/silabos/ingest', $payload, ['X-Api-Key' => $key]);
    }

    private function payload(
        int $id = 1234,
        int $version = 3,
        string $periodo = '202610',
        string $modelo = 'isil',
        string $aprobadoAt = '2026-10-28T15:04:05-05:00',
        string $contenido = 'Sumilla: el curso desarrolla aplicaciones web con PHP, Laravel y SQL usando Scrum.',
    ): array {
        return [
            'schema_version' => 1,
            'origen' => 'silabos_v2',
            'modelo' => $modelo,
            'silabo_id' => $id,
            'codigo_familia' => 'IS-101',
            'version' => $version,
            'curso' => [
                'codigo' => 'IS-101',
                'nombre' => 'DESARROLLO WEB',
                'creditos' => 4,
                'tipo_plantilla' => 1,
                'periodo' => $periodo,
            ],
            'carreras' => [],
            'aprobado_at' => $aprobadoAt,
            'aprobado_at_fuente' => 'finalizar_revision',
            'contenido' => [
                'secciones' => ['sumilla' => $contenido],
                'texto_plano' => $contenido,
            ],
        ];
    }

    private function fakeOpenAi(array $lenguajes, array $tecnologias, array $metodologias, string $curso = 'Curso detectado'): void
    {
        // Http::fake acumula; se reinicia el factory para que la respuesta nueva gane.
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'curso' => $curso,
                    'lenguajes' => $lenguajes,
                    'tecnologias' => $tecnologias,
                    'metodologias' => $metodologias,
                ])]]],
            ]),
        ]);
    }

    private function reviewLogContents(): string
    {
        return is_file($this->reviewLog) ? file_get_contents($this->reviewLog) : '';
    }
}
