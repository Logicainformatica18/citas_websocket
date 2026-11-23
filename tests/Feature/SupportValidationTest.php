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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupportValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $atc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionsSeeder::class, CatalogSeeder::class]);
        $this->atc = User::factory()->create()->assignRole('ATC');
    }

    private function payload(array $details, array $extra = []): array
    {
        return [
            'client_id' => ClientFactory::new()->create()->id_cliente,
            'status_global' => 'No',
            'details' => json_encode($details),
            ...$extra,
        ];
    }

    public function test_store_acepta_el_formato_del_formulario(): void
    {
        // El formulario envía '' en campos vacíos y objetos auxiliares dentro del JSON.
        $this->actingAs($this->atc)->post('/supports', $this->payload([[
            'subject' => 'Pagos', 'priority' => 'Alta', 'status' => 'Pendiente', 'project_id' => '',
            'area_id' => '', 'reservation_time' => '', 'internal_state' => ['id' => 3, 'description' => 'Pendiente'],
        ]]))->assertOk();

        $this->assertSame('Alta', SupportDetail::first()->priority);
        $this->assertSame(1, SupportDetail::first()->area_id); // default ATC del controlador
    }

    public function test_store_sin_detalles_no_crea_nada(): void
    {
        $this->actingAs($this->atc)->postJson('/supports', $this->payload([]))
            ->assertUnprocessable()->assertJsonValidationErrors('details');

        $this->assertSame(0, Support::count());
    }

    public function test_solo_se_aceptan_prioridades_baja_media_alta(): void
    {
        foreach (['Normal', 'Urgente', 'Preferencial'] as $priority) {
            $this->actingAs($this->atc)
                ->postJson('/supports', $this->payload([['subject' => 'Pagos', 'priority' => $priority]]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['details.0.priority']);
        }
        $this->assertSame(0, Support::count());
    }

    public function test_store_valida_claves_foraneas_y_longitudes(): void
    {
        $this->actingAs($this->atc)->postJson('/supports', $this->payload([[
            'subject' => 'Pagos', 'area_id' => 999, 'comment' => str_repeat('x', 300),
        ]]))->assertUnprocessable()
            ->assertJsonValidationErrors(['details.0.area_id', 'details.0.comment']);
    }

    public function test_si_falla_un_detalle_se_revierte_todo(): void
    {
        // El segundo número de ticket lanza una excepción a mitad del store.
        $this->app->instance(TicketNumberGenerator::class, new class extends TicketNumberGenerator {
            private int $calls = 0;

            public function next(): string
            {
                if (++$this->calls === 2) {
                    throw new \RuntimeException('fallo simulado');
                }

                return parent::next();
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->atc)->post('/supports', $this->payload([
                ['subject' => 'Pagos'], ['subject' => 'Boletas'],
            ]));
            $this->fail('Se esperaba la excepción simulada');
        } catch (\RuntimeException $e) {
            $this->assertSame('fallo simulado', $e->getMessage());
        }

        $this->assertSame(0, Support::count(), 'No debe quedar una solicitud huérfana');
        $this->assertSame(0, SupportDetail::count());
        $this->assertSame(0, (int) DB::table('ticket_sequences')->value('last_number'), 'No se consumen tickets');
    }

    public function test_update_valida_prioridad(): void
    {
        $support = SupportFactory::new()->create([
            'client_id' => ClientFactory::new()->create()->id_cliente,
            'created_by' => $this->atc->id,
        ]);
        SupportDetailFactory::new()->create(['support_id' => $support->id]);

        $this->actingAs($this->atc)->putJson("/supports/{$support->id}", [
            'status_global' => 'No',
            'details' => json_encode([['subject' => 'Pagos', 'priority' => 'Urgente']]),
        ])->assertUnprocessable()->assertJsonValidationErrors(['details.0.priority']);

        $this->actingAs($this->atc)->putJson("/supports/{$support->id}", [
            'status_global' => 'Sí',
            'details' => json_encode([['subject' => 'Pagos', 'priority' => 'Media']]),
        ])->assertOk();
        $this->assertSame('Media', $support->details()->first()->priority);
        $this->assertSame('Sí', $support->fresh()->status_global);
    }

    public function test_update_area_motivo_requiere_area_valida(): void
    {
        $support = SupportFactory::new()->create([
            'client_id' => ClientFactory::new()->create()->id_cliente,
            'created_by' => $this->atc->id,
        ]);
        $detail = SupportDetailFactory::new()->create(['support_id' => $support->id]);

        $this->actingAs($this->atc)->putJson("/support-details/{$detail->id}/area-motivo", [])
            ->assertUnprocessable()->assertJsonValidationErrors('area_id');
        $this->actingAs($this->atc)->putJson("/support-details/{$detail->id}/area-motivo", ['area_id' => 999])
            ->assertUnprocessable()->assertJsonValidationErrors('area_id');
    }
}
