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

/** Listado de "Gestión de Cursos": código de silabos_v2, búsqueda por código y filtro con/sin código. */
class CourseListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // certifications y certification_course existen en la base sin migración; el listado las carga.
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

        Course::create(['name' => 'Redes I', 'code' => '30653']);
        Course::create(['name' => 'Base de Datos', 'code' => '30701']);
        Course::create(['name' => 'Redes Avanzadas']);
    }

    // ── Listado ─────────────────────────────────────────────────

    public function test_el_listado_incluye_el_codigo(): void
    {
        $data = $this->actingAs($this->admin)->getJson('/courses/fetch')->assertOk()->json('courses.data');

        $this->assertSame(['Redes Avanzadas' => null, 'Base de Datos' => '30701', 'Redes I' => '30653'],
            array_column($data, 'code', 'name'));
    }

    public function test_filtro_solo_con_codigo(): void
    {
        $this->assertSame(['Base de Datos', 'Redes I'], $this->nombres('/courses/fetch?codigo=con'));
    }

    public function test_filtro_solo_sin_codigo(): void
    {
        $this->assertSame(['Redes Avanzadas'], $this->nombres('/courses/fetch?codigo=sin'));
    }

    public function test_filtro_vacio_o_desconocido_muestra_todos(): void
    {
        $this->assertCount(3, $this->nombres('/courses/fetch?codigo='));
        $this->assertCount(3, $this->nombres('/courses/fetch?codigo=otro'));
    }

    public function test_la_paginacion_conserva_el_filtro(): void
    {
        foreach (range(1, 11) as $i) {
            Course::create(['name' => "Curso $i", 'code' => "C-$i"]);
        }

        $next = $this->actingAs($this->admin)->getJson('/courses/fetch?codigo=con')->json('courses.next_page_url');

        $this->assertStringContainsString('codigo=con', $next);
    }

    // ── Buscador ────────────────────────────────────────────────

    public function test_busca_por_codigo(): void
    {
        $this->assertSame(['Base de Datos'], $this->nombres('/courses/search?name=30701'));
        $this->assertSame(['Redes I'], $this->nombres('/courses/search?name=306'));
    }

    public function test_sigue_buscando_por_nombre(): void
    {
        $this->assertSame(['Redes Avanzadas', 'Redes I'], $this->nombres('/courses/search?name=redes'));
    }

    public function test_busqueda_y_filtro_combinados(): void
    {
        $this->assertSame(['Redes I'], $this->nombres('/courses/search?name=redes&codigo=con'));
        $this->assertSame(['Redes Avanzadas'], $this->nombres('/courses/search?name=redes&codigo=sin'));
    }

    public function test_la_busqueda_devuelve_el_codigo(): void
    {
        $data = $this->actingAs($this->admin)->getJson('/courses/search?name=30653')->json('courses.data');

        $this->assertSame('30653', $data[0]['code']);
    }

    // ── Helpers ─────────────────────────────────────────────────

    /** Nombres devueltos, ordenados alfabéticamente para no depender del orden del listado. */
    private function nombres(string $url): array
    {
        $nombres = array_column($this->actingAs($this->admin)->getJson($url)->assertOk()->json('courses.data'), 'name');
        sort($nombres);

        return $nombres;
    }
}
