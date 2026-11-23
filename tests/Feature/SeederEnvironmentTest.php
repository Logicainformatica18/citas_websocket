<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\SupportSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeederEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    private function runSeeder(string $class): void
    {
        // Se invoca directo: `db:seed` en producción pediría confirmación.
        app($class)->setContainer(app())->__invoke();
    }

    public function test_en_produccion_solo_siembra_datos_base_y_es_idempotente(): void
    {
        $this->app['env'] = 'production';

        $this->runSeeder(DatabaseSeeder::class);
        $this->runSeeder(DatabaseSeeder::class); // segunda vez: sin errores ni duplicados

        $this->assertSame(0, DB::table('users')->count(), 'Sin usuarios demo ni contraseñas conocidas');
        $this->assertSame(0, DB::table('clientes')->count());
        $this->assertSame(0, DB::table('supports')->count());
        $this->assertSame(0, DB::table('proyecto')->count(), 'Proyectos ficticios solo en demo');
        $this->assertSame(9, DB::table('areas')->count());
        $this->assertSame(4, DB::table('internal_states')->count());
        $this->assertSame(4, Role::count());
    }

    public function test_seeders_demo_invocados_directamente_no_corren_en_produccion(): void
    {
        $this->app['env'] = 'production';

        $this->runSeeder(DemoUserSeeder::class);
        $this->runSeeder(SupportSeeder::class);

        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(0, DB::table('support_details')->count());
    }

    public function test_no_modifica_permisos_de_roles_existentes(): void
    {
        $this->runSeeder(DatabaseSeeder::class);
        Role::findByName('ATC')->syncPermissions(['solicitudes.ver']); // configurado a mano en /roles

        $this->app['env'] = 'production';
        $this->runSeeder(DatabaseSeeder::class);

        $this->assertSame(['solicitudes.ver'], Role::findByName('ATC')->permissions->pluck('name')->all());
    }

    public function test_en_local_siembra_la_demo_completa(): void
    {
        $this->app['env'] = 'local';

        $this->runSeeder(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'admin@demo.com']);
        $this->assertGreaterThanOrEqual(50, DB::table('support_details')->count());
    }
}
