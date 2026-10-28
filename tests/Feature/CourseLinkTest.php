<?php

namespace Tests\Feature;

use App\Events\SyllabusProcessed;
use App\Models\Course;
use App\Models\Syllabus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CourseLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        config([
            'silabos_v2.career_map' => ['isil:CADT' => 'arquitectura-de-datos'],
            'logging.channels.silabos_revision.path' => storage_path('framework/testing/silabos-revision-' . getmypid() . '.log'),
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::create(['name' => 'administrar', 'guard_name' => 'web']);
        $this->admin = $this->user('admin@isil.test');
        $this->admin->givePermissionTo('administrar');

        Event::fake([SyllabusProcessed::class]);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'curso' => 'Curso detectado',
                    'lenguajes' => ['Python'],
                    'tecnologias' => [],
                    'metodologias' => [],
                ])]]],
            ]),
        ]);
    }

    // ── Página y permisos ───────────────────────────────────────

    public function test_lista_los_pendientes_con_la_sugerencia_y_los_cursos_sin_codigo(): void
    {
        $suggested = Course::create(['name' => 'Arquitectura De Datos En La Nube']);
        Course::create(['name' => 'Redes I']);
        Course::create(['name' => 'Ya Vinculado', 'code' => 'X-1']);
        $pending = $this->pendingSyllabus('ARQUITECTURA DE DATOS EN NUBE');
        $this->pendingSyllabus('OTRO', status: 'processed');

        $this->actingAs($this->admin)
            ->get('/course-linking')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('course-linking/index')
                ->has('pending', 1)
                ->where('pending.0.id', $pending->id)
                ->where('pending.0.code', 'CX-100')
                ->where('pending.0.careers.0.codigo', 'CADT')
                ->where('pending.0.careers.0.mapped', true)
                ->where('pending.0.careers.1.mapped', false)
                ->where('pending.0.suggestion.course_id', $suggested->id)
                ->where('pending.0.suggestion.similarity', fn ($pct) => $pct >= 60 && $pct < 100)
                ->has('courses', 2)
                ->where('pendingCourseLinks', 1));
    }

    public function test_sin_permiso_no_puede_ver_ni_vincular(): void
    {
        $user = $this->user('docente@isil.test');
        $course = Course::create(['name' => 'Redes I']);
        $pending = $this->pendingSyllabus();

        $this->actingAs($user)->get('/course-linking')->assertForbidden();
        $this->actingAs($user)
            ->postJson("/course-linking/{$pending->id}", ['action' => 'link', 'course_id' => $course->id])
            ->assertForbidden();

        $this->assertNull($course->fresh()->code);
        $this->assertSame('pending_link', $pending->fresh()->status);
    }

    public function test_sin_sesion_redirige_al_login(): void
    {
        $this->get('/course-linking')->assertRedirect('/login');
    }

    public function test_el_contador_del_menu_es_cero_sin_permiso(): void
    {
        $this->pendingSyllabus();

        $shared = fn (User $user) => value(app(\App\Http\Middleware\HandleInertiaRequests::class)
            ->share(\Illuminate\Http\Request::create('/')->setUserResolver(fn () => $user))['pendingCourseLinks']);

        $this->assertSame(0, $shared($this->user('docente@isil.test')));
        $this->assertSame(1, $shared($this->admin));
    }

    // ── Vincular ────────────────────────────────────────────────

    public function test_vincular_asigna_el_codigo_y_procesa_el_silabo(): void
    {
        $course = Course::create(['name' => 'Redes I']);
        $pending = $this->pendingSyllabus();

        $this->actingAs($this->admin)
            ->postJson("/course-linking/{$pending->id}", ['action' => 'link', 'course_id' => $course->id])
            ->assertOk()
            ->assertJson(['queued' => [$pending->id]]);

        $this->assertSame('CX-100', $course->fresh()->code);
        $pending->refresh();
        $this->assertSame('processed', $pending->status);
        $this->assertSame($course->id, $pending->course_id);
        $this->assertSame(['Python'], $course->languages->pluck('name')->all());
        $this->assertSame(['Arquitectura de datos'], $course->careers->pluck('name')->all());
        Http::assertSentCount(1);
    }

    public function test_vincular_exige_elegir_un_curso(): void
    {
        $pending = $this->pendingSyllabus();

        $this->actingAs($this->admin)
            ->postJson("/course-linking/{$pending->id}", ['action' => 'link'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['course_id']);

        $this->assertSame('pending_link', $pending->fresh()->status);
    }

    public function test_no_se_puede_vincular_si_el_codigo_ya_esta_en_otro_curso(): void
    {
        Course::create(['name' => 'Redes Avanzadas', 'code' => 'CX-100']);
        $course = Course::create(['name' => 'Redes I']);
        $pending = $this->pendingSyllabus();

        $this->actingAs($this->admin)
            ->postJson("/course-linking/{$pending->id}", ['action' => 'link', 'course_id' => $course->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.course_id.0', 'El código CX-100 ya está asignado al curso «Redes Avanzadas».');

        $this->assertNull($course->fresh()->code);
        $this->assertSame('pending_link', $pending->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_no_se_puede_vincular_a_un_curso_que_ya_tiene_otro_codigo(): void
    {
        $course = Course::create(['name' => 'Redes I', 'code' => 'CX-999']);
        $pending = $this->pendingSyllabus();

        $this->actingAs($this->admin)
            ->postJson("/course-linking/{$pending->id}", ['action' => 'link', 'course_id' => $course->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.course_id.0', 'El curso «Redes I» ya tiene el código CX-999.');

        $this->assertSame('CX-999', $course->fresh()->code);
        $this->assertSame('pending_link', $pending->fresh()->status);
    }

    // ── No vincular ─────────────────────────────────────────────

    public function test_no_vincular_lo_marca_como_ignorado_sin_tocar_cursos(): void
    {
        $course = Course::create(['name' => 'Redes I']);
        $pending = $this->pendingSyllabus();

        $this->actingAs($this->admin)
            ->postJson("/course-linking/{$pending->id}", ['action' => 'ignore'])
            ->assertOk();

        $this->assertSame('ignored', $pending->fresh()->status);
        $this->assertNull($course->fresh()->code);
        Http::assertNothingSent();
    }

    public function test_un_silabo_que_ya_no_esta_pendiente_no_se_puede_vincular(): void
    {
        $course = Course::create(['name' => 'Redes I']);
        $processed = $this->pendingSyllabus(status: 'processed');

        $this->actingAs($this->admin)
            ->postJson("/course-linking/{$processed->id}", ['action' => 'link', 'course_id' => $course->id])
            ->assertStatus(422);

        $this->assertNull($course->fresh()->code);
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function user(string $email): User
    {
        return User::forceCreate([
            'firstname' => 'Prueba',
            'lastname' => 'ISIL',
            'names' => 'Prueba ISIL',
            'email' => $email,
            'password' => bcrypt('secret'),
        ]);
    }

    private function pendingSyllabus(string $name = 'REDES I', string $status = 'pending_link'): Syllabus
    {
        static $id = 500;
        $id++;

        \App\Models\Career::firstOrCreate(['slug' => 'arquitectura-de-datos'], ['name' => 'Arquitectura de datos']);

        return Syllabus::create([
            'source' => Syllabus::SOURCE_SILABOS_V2,
            'external_model' => 'isil',
            'external_id' => $id,
            'codigo_familia' => 'CX-' . ($status === 'pending_link' ? '100' : $id),
            'external_version' => 1,
            'periodo' => '202610',
            'periodo_orden' => 202610,
            'tipo_plantilla' => 1,
            'course_code' => 'CX-' . ($status === 'pending_link' ? '100' : $id),
            'course_name' => $name,
            'careers' => [
                ['codigo' => 'CADT', 'nombre' => 'ARQUITECTURA DE DATOS'],
                ['codigo' => 'CMAR', 'nombre' => 'MARKETING'],
            ],
            'content' => ['secciones' => ['sumilla' => 'x'], 'texto_plano' => 'x'],
            'raw_text' => 'Sumilla: redes con Python.',
            'status' => $status,
        ]);
    }
}
