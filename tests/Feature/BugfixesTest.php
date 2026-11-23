<?php

namespace Tests\Feature;

use App\Http\Controllers\SupportDetailController;
use App\Models\Client;
use App\Models\Support;
use App\Models\SupportDetail;
use App\Models\User;
use Database\Factories\ClientFactory;
use Database\Factories\SupportDetailFactory;
use Database\Factories\SupportFactory;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BugfixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionsSeeder::class, CatalogSeeder::class]);
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole('Administrador');
    }

    private function supportWithDetails(int $n): Support
    {
        $support = SupportFactory::new()->create([
            'client_id' => ClientFactory::new()->create()->id_cliente,
            'created_by' => User::factory()->create()->id,
        ]);
        SupportDetailFactory::new()->count($n)->create(['support_id' => $support->id]);

        return $support;
    }

    public function test_chat_index_lista_usuarios_con_names(): void
    {
        User::factory()->create(['names' => 'Lucía']);

        // La página Chat/Index no existe en el frontend; aquí solo se valida el backend.
        $this->withoutVite();

        $this->actingAs($this->admin())->get('/chat')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('users', 1)
                ->where('users.0.names', 'Lucía'));
    }

    public function test_cliente_se_crea_con_los_campos_del_formulario(): void
    {
        // Mismo payload que resources/js/pages/clients/modal.tsx
        $payload = [
            'Codigo' => 'CLI-1', 'Razon_Social' => 'PEREZ GOMEZ ANA', 'DNI' => '12345678',
            'Email' => 'ana@example.com', 'Telefono' => '987654321', 'Direccion' => 'Av. Lima 123',
            'canal' => 'whatsapp', 'habilitado' => true, 'id_rol' => 2,
        ];

        $this->actingAs($this->admin())->post('/clients', $payload)
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('clientes', ['DNI' => '12345678', 'Razon_Social' => 'PEREZ GOMEZ ANA']);
    }

    public function test_tipo_documento_se_guarda_en_columna_t_doc(): void
    {
        $client = ClientFactory::new()->create();

        $this->actingAs($this->admin())->put("/clients/{$client->id_cliente}", [
            'Codigo' => $client->Codigo, 'Razon_Social' => $client->Razon_Social, 'DNI' => $client->DNI,
            'Email' => 'nuevo@example.com', 'Telefono' => $client->Telefono, 'Direccion' => $client->Direccion,
            'habilitado' => true, 'tipo_documento' => 'CE',
        ])->assertSessionHasNoErrors();

        $this->assertSame('nuevo@example.com', Client::find($client->id_cliente)->Email);
        $this->assertSame('CE', DB::selectOne('select "T.Doc." as t from clientes where id_cliente = ?', [$client->id_cliente])->t);
    }

    public function test_support_detail_show_carga_relaciones_existentes(): void
    {
        $support = $this->supportWithDetails(2);

        $data = app(SupportDetailController::class)->show($support->id)->getData(true);

        $this->assertSame($support->id, $data['support']['id']);
        $this->assertCount(2, $data['support']['details']);
        $this->assertSame('ATC', $data['support']['details'][0]['area']['descripcion']);
    }

    public function test_support_detail_bulk_delete_borra_detalles_no_supports(): void
    {
        $support = $this->supportWithDetails(3);
        $ids = $support->details()->pluck('id')->take(2)->all();

        app(SupportDetailController::class)->bulkDelete(new Request(['ids' => $ids]));

        $this->assertDatabaseHas('supports', ['id' => $support->id]);
        $this->assertSame(1, SupportDetail::where('support_id', $support->id)->count());
    }
}
