<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\User;
use Database\Factories\ClientFactory;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\ProjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SalesLotsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionsSeeder::class, CatalogSeeder::class, ProjectSeeder::class]);
    }

    public function test_agente_atc_registra_lote_con_proyecto(): void
    {
        $client = ClientFactory::new()->create();
        $atc = User::factory()->create()->assignRole('ATC');

        $this->actingAs($atc)->postJson('/sales', [
            'id_cliente' => $client->id_cliente, 'project_id' => 3, 'mz_lote' => 'Mz. B Lt. 7',
        ])->assertOk()
            ->assertJsonPath('sale.project_id', 3)
            ->assertJsonPath('sale.project.descripcion', 'Brisas de Chilca')
            ->assertJsonPath('sale.client.id', $client->id_cliente);

        // El lote aparece en la búsqueda de clientes del formulario de solicitudes.
        $this->actingAs($atc)->getJson('/clients/search?q=' . $client->DNI)
            ->assertJsonPath('0.sales.0.project.descripcion', 'Brisas de Chilca');
    }

    public function test_proyecto_cliente_y_lote_son_obligatorios(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('ATC'))
            ->postJson('/sales', ['mz_lote' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_cliente', 'project_id', 'mz_lote']);
    }

    public function test_sin_permiso_clientes_no_registra_lotes(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/sales', [])->assertForbidden();
    }

    public function test_show_devuelve_cliente_y_proyecto_para_editar(): void
    {
        $client = ClientFactory::new()->create();
        $sale = Sale::create(['id_cliente' => $client->id_cliente, 'project_id' => 2, 'mz_lote' => 'Mz. A Lt. 1']);

        $this->actingAs(User::factory()->create()->assignRole('Administrador'))
            ->getJson("/sales/{$sale->id}")
            ->assertJsonPath('sale.client.id', $client->id_cliente)
            ->assertJsonPath('sale.client.names', $client->Razon_Social)
            ->assertJsonPath('sale.project_id', 2)
            ->assertJsonPath('sale.cliente', $client->Razon_Social);
    }

    public function test_editar_no_borra_el_cliente(): void
    {
        $client = ClientFactory::new()->create();
        $sale = Sale::create(['id_cliente' => $client->id_cliente, 'project_id' => 2, 'mz_lote' => 'Mz. A Lt. 1']);

        $this->actingAs(User::factory()->create()->assignRole('Administrador'))
            ->putJson("/sales/{$sale->id}", [
                'id_cliente' => $client->id_cliente, 'project_id' => 4, 'mz_lote' => 'Mz. C Lt. 9',
            ])->assertOk()->assertJsonPath('sale.proyecto', 'Praderas de Pachacámac');

        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'id_cliente' => $client->id_cliente, 'mz_lote' => 'Mz. C Lt. 9']);
    }

    public function test_pagina_envia_solo_proyectos_habilitados(): void
    {
        $this->withoutVite();

        $this->actingAs(User::factory()->create()->assignRole('Administrador'))
            ->get('/sales')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('projects', 7) // 8 en el catálogo, 1 deshabilitado
                ->has('sales.data'));
    }
}
