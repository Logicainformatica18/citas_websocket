<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Campo "Código en silabos_v2" del formulario de cursos. */
class CourseCodeFormTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // certifications y certification_course existen en la base sin migración; el formulario las usa.
        if (!Schema::hasTable('certifications')) {
            Schema::create('certifications', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('certification_course')) {
            Schema::create('certification_course', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('course_id');
                $t->unsignedBigInteger('certification_id');
                $t->string('relevance_level')->nullable();
                $t->decimal('weight', 3, 2)->nullable();
                $t->timestamps();
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::create(['name' => 'administrar', 'guard_name' => 'web']);
        $this->admin = User::forceCreate([
            'firstname' => 'Prueba', 'lastname' => 'ISIL', 'names' => 'Prueba ISIL',
            'email' => 'admin@isil.test', 'password' => bcrypt('secret'),
        ]);
        $this->admin->givePermissionTo('administrar');
    }

    public function test_crear_un_curso_con_codigo(): void
    {
        $this->actingAs($this->admin)->postJson('/courses', ['name' => 'Redes I', 'code' => ' 30653 '])->assertOk();

        $this->assertSame('30653', Course::sole()->code);
    }

    public function test_codigo_vacio_se_guarda_como_null(): void
    {
        $this->actingAs($this->admin)->postJson('/courses', ['name' => 'Redes I', 'code' => ''])->assertOk();

        $this->assertNull(Course::sole()->code);
    }

    public function test_no_permite_un_codigo_que_ya_tiene_otro_curso(): void
    {
        Course::create(['name' => 'Redes Avanzadas', 'code' => '30653']);
        $course = Course::create(['name' => 'Redes I']);

        $this->actingAs($this->admin)
            ->putJson("/courses/{$course->id}", ['name' => 'Redes I', 'code' => '30653'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertNull($course->fresh()->code);
    }

    public function test_editar_sin_cambiar_el_codigo_lo_conserva(): void
    {
        $course = Course::create(['name' => 'Redes I', 'code' => '30653']);

        $this->actingAs($this->admin)
            ->putJson("/courses/{$course->id}", ['name' => 'Redes I (nuevo)', 'code' => '30653'])
            ->assertOk();

        $this->assertSame('30653', $course->fresh()->code);
        $this->assertSame('Redes I (nuevo)', $course->fresh()->name);
    }

    public function test_un_cliente_que_no_envia_code_no_lo_borra(): void
    {
        $course = Course::create(['name' => 'Redes I', 'code' => '30653']);

        $this->actingAs($this->admin)->putJson("/courses/{$course->id}", ['name' => 'Redes I'])->assertOk();

        $this->assertSame('30653', $course->fresh()->code);
    }

    public function test_borrar_un_curso_deja_sus_silabos_sin_curso(): void
    {
        $course = Course::create(['name' => 'Redes I', 'code' => '30653']);
        $syllabus = \App\Models\Syllabus::create(['source' => 'silabos_v2', 'status' => 'processed', 'course_id' => $course->id]);

        $this->actingAs($this->admin)->deleteJson("/courses/{$course->id}")->assertOk();

        $this->assertNull($syllabus->fresh()->course_id);
    }
}
