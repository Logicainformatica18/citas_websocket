<?php

namespace Tests\Feature;

use App\Models\Career;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Modal "Sincronizar cursos con carrera": código de silabos_v2 de los cursos disponibles y asociados. */
class CareerCoursesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Career $career;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::create(['name' => 'administrar', 'guard_name' => 'web']);
        $this->admin = User::forceCreate([
            'firstname' => 'Prueba', 'lastname' => 'ISIL', 'names' => 'Prueba ISIL',
            'email' => 'admin@isil.test', 'password' => bcrypt('secret'),
        ]);
        $this->admin->givePermissionTo('administrar');

        $this->career = Career::create(['name' => 'Arquitectura de datos', 'slug' => 'arquitectura-de-datos']);
    }

    // ── Cursos disponibles (/api/courses/list) ──────────────────

    public function test_la_lista_de_cursos_incluye_el_codigo(): void
    {
        Course::create(['name' => 'Redes I', 'code' => '30653']);
        Course::create(['name' => 'Base de Datos']);

        $courses = $this->getJson('/api/courses/list')->assertOk()->json('courses');

        $this->assertSame([
            ['name' => 'Base de Datos', 'code' => null],
            ['name' => 'Redes I', 'code' => '30653'],
        ], array_map(fn ($c) => ['name' => $c['name'], 'code' => $c['code']], $courses));
    }

    // ── Cursos asociados (/careers/{id}) ────────────────────────

    public function test_los_cursos_asociados_incluyen_el_codigo(): void
    {
        $conCodigo = Course::create(['name' => 'Redes I', 'code' => '30653']);
        $sinCodigo = Course::create(['name' => 'Base de Datos']);
        $this->career->courses()->attach([
            $conCodigo->id => ['semester' => '1er ciclo', 'is_mandatory' => true],
            $sinCodigo->id => ['semester' => null, 'is_mandatory' => true],
        ]);

        $courses = collect($this->actingAs($this->admin)->getJson("/careers/{$this->career->id}")->assertOk()->json('courses'))
            ->keyBy('id');

        $this->assertSame('30653', $courses[$conCodigo->id]['code']);
        $this->assertSame('1er ciclo', $courses[$conCodigo->id]['semester']);
        $this->assertNull($courses[$sinCodigo->id]['code']);
    }

    // ── La lógica del modal no cambia ───────────────────────────

    public function test_agregar_y_quitar_un_curso_sigue_funcionando(): void
    {
        $course = Course::create(['name' => 'Redes I', 'code' => '30653']);

        $this->actingAs($this->admin)
            ->postJson("/careers/{$this->career->id}/attach-course", [
                'course_id' => $course->id, 'semester' => '2do ciclo', 'is_mandatory' => true,
            ])->assertOk();

        $this->assertSame('2do ciclo', $this->career->courses()->sole()->pivot->semester);

        $this->actingAs($this->admin)
            ->deleteJson("/careers/{$this->career->id}/detach-course/{$course->id}")
            ->assertOk();

        $this->assertSame(0, $this->career->courses()->count());
        $this->assertSame('30653', $course->fresh()->code); // quitar de la carrera no toca el código
    }
}
