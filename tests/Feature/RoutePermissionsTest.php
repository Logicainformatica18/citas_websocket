<?php

namespace Tests\Feature;

use App\Models\Support;
use App\Models\User;
use Database\Factories\ClientFactory;
use Database\Factories\SupportDetailFactory;
use Database\Factories\SupportFactory;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoutePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionsSeeder::class, CatalogSeeder::class]);
    }

    private function userWithRole(?string $role): User
    {
        $user = User::factory()->create();
        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function makeSupport(): Support
    {
        $client = ClientFactory::new()->create();
        $support = SupportFactory::new()->create([
            'client_id' => $client->id_cliente,
            'created_by' => User::factory()->create()->id,
        ]);
        SupportDetailFactory::new()->create(['support_id' => $support->id]);

        return $support;
    }

    public function test_solo_administrar_gestiona_usuarios_y_roles(): void
    {
        foreach (['ATC', 'ATC_Oficina', 'Asistente', null] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->getJson('/users/fetch')->assertForbidden();
            $this->actingAs($this->userWithRole($role))
                ->postJson('/roles', ['name' => 'Hack'])->assertForbidden();
        }

        $this->actingAs($this->userWithRole('Administrador'))
            ->getJson('/users/fetch')->assertOk();
    }

    public function test_un_admin_solo_con_permiso_administrar_conserva_acceso(): void
    {
        // Como el rol Administrador del seeder original (solo "administrar").
        Role::findByName('Administrador')->syncPermissions(['administrar']);
        $admin = $this->userWithRole('Administrador');
        $support = $this->makeSupport();

        $this->actingAs($admin)->getJson('/supports/fetch')->assertOk();
        $this->actingAs($admin)->deleteJson("/supports/{$support->id}")->assertOk();
    }

    public function test_borrar_solicitudes_requiere_solicitudes_eliminar(): void
    {
        $support = $this->makeSupport();

        foreach (['ATC', 'ATC_Oficina', 'Asistente'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->deleteJson("/supports/{$support->id}")->assertForbidden();
            $this->actingAs($this->userWithRole($role))
                ->postJson('/supports/bulk-delete', ['ids' => [$support->id]])->assertForbidden();
        }
        $this->assertDatabaseHas('supports', ['id' => $support->id]);

        $this->actingAs($this->userWithRole('Administrador'))
            ->deleteJson("/supports/{$support->id}")->assertOk();
        $this->assertDatabaseMissing('supports', ['id' => $support->id]);
    }

    public function test_ver_solicitudes_requiere_solicitudes_ver(): void
    {
        $this->actingAs($this->userWithRole(null))->getJson('/supports/fetch')->assertForbidden();
        $this->actingAs($this->userWithRole('Asistente'))->getJson('/supports/fetch')->assertOk();
        $this->actingAs($this->userWithRole('ATC'))->getJson('/clients/search?q=x')->assertOk();
    }

    public function test_cambiar_area_requiere_acciones_avanzadas(): void
    {
        $detail = $this->makeSupport()->details()->first();

        $this->actingAs($this->userWithRole('ATC_Oficina'))
            ->putJson("/support-details/{$detail->id}/area-motivo", ['area_id' => 2])
            ->assertForbidden();
    }

    public function test_escritura_de_catalogos_solo_administrar(): void
    {
        $atc = $this->userWithRole('ATC');
        $this->actingAs($atc)->getJson('/areas/all')->assertOk();
        $this->actingAs($atc)->postJson('/areas', ['descripcion' => 'X', 'habilitado' => true])->assertForbidden();
        $this->actingAs($atc)->deleteJson('/projects/1')->assertForbidden();
    }

    public function test_rutas_de_debug_ya_no_son_publicas(): void
    {
        $this->get('/debug-broadcast')->assertRedirect('/login');
        $this->actingAs($this->userWithRole('ATC'))->getJson('/debug-broadcast')->assertForbidden();
    }

    public function test_api_sanctum_aplica_los_mismos_permisos(): void
    {
        Sanctum::actingAs($this->userWithRole('ATC'));
        $this->getJson('/api/users')->assertForbidden();
        $this->getJson('/api/supports/fetch')->assertOk();

        Sanctum::actingAs($this->userWithRole('Administrador'));
        $this->getJson('/api/users')->assertOk();
    }
}
