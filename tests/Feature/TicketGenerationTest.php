<?php

namespace Tests\Feature;

use App\Models\Support;
use App\Models\SupportDetail;
use App\Models\User;
use App\Services\TicketNumberGenerator;
use Database\Factories\ClientFactory;
use Database\Factories\SupportDetailFactory;
use Database\Factories\SupportFactory;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\ProjectSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionsSeeder::class, CatalogSeeder::class, ProjectSeeder::class]);
    }

    private function storePayload(int $clientId, array $subjects): array
    {
        return [
            'client_id' => $clientId,
            'status_global' => 'No',
            'details' => json_encode(array_map(fn ($s) => [
                'subject' => $s, 'priority' => 'Media', 'type' => 'Consulta', 'status' => 'Pendiente',
            ], $subjects)),
        ];
    }

    public function test_cada_detalle_recibe_su_propio_ticket(): void
    {
        $client = ClientFactory::new()->create();
        $atc = User::factory()->create()->assignRole('ATC');

        $this->actingAs($atc)
            ->post('/supports', $this->storePayload($client->id_cliente, ['Pagos', 'Boletas', 'EE.CC']))
            ->assertOk();

        $tickets = SupportDetail::pluck('ticket');
        $this->assertSame(['TK-00001', 'TK-00002', 'TK-00003'], $tickets->sort()->values()->all());
        $this->assertSame(1, Support::count()); // un solo TR (solicitud) con 3 TK
    }

    public function test_sin_permiso_generar_ticket_queda_placeholder(): void
    {
        $client = ClientFactory::new()->create();
        $asistente = User::factory()->create()->assignRole('Asistente');

        $this->actingAs($asistente)
            ->post('/supports', $this->storePayload($client->id_cliente, ['Pagos', 'Boletas']))
            ->assertOk();

        $this->assertSame(['TK-', 'TK-'], SupportDetail::pluck('ticket')->all());
    }

    public function test_la_secuencia_continua_despues_de_tickets_existentes(): void
    {
        $support = SupportFactory::new()->create([
            'client_id' => ClientFactory::new()->create()->id_cliente,
            'created_by' => User::factory()->create()->id,
        ]);
        SupportDetailFactory::new()->create(['support_id' => $support->id, 'ticket' => 'TK-00041']);

        $generator = app(TicketNumberGenerator::class);
        $generator->syncWithExisting();

        $this->assertSame('TK-00042', $generator->next());
        $this->assertSame('TK-00043', $generator->next());
    }

    public function test_generate_ticket_no_regenera_si_ya_tiene_uno(): void
    {
        $support = SupportFactory::new()->create([
            'client_id' => ClientFactory::new()->create()->id_cliente,
            'created_by' => User::factory()->create()->id,
        ]);
        $detail = SupportDetailFactory::new()->create(['support_id' => $support->id, 'ticket' => 'TK-']);
        $atc = User::factory()->create()->assignRole('ATC');

        $this->actingAs($atc)->postJson('/support-details/generate-ticket', ['support_id' => $detail->id])->assertOk();
        $first = $detail->fresh()->ticket;
        $this->actingAs($atc)->postJson('/support-details/generate-ticket', ['support_id' => $detail->id])->assertOk();

        $this->assertSame('TK-00001', $first);
        $this->assertSame($first, $detail->fresh()->ticket);
    }

    public function test_el_indice_unico_rechaza_tickets_repetidos_pero_permite_varios_placeholder(): void
    {
        $support = SupportFactory::new()->create([
            'client_id' => ClientFactory::new()->create()->id_cliente,
            'created_by' => User::factory()->create()->id,
        ]);
        SupportDetailFactory::new()->count(3)->create(['support_id' => $support->id, 'ticket' => 'TK-']);
        SupportDetailFactory::new()->create(['support_id' => $support->id, 'ticket' => 'TK-00007']);

        $this->expectException(QueryException::class);
        SupportDetailFactory::new()->create(['support_id' => $support->id, 'ticket' => 'TK-00007']);
    }
}
