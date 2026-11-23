<?php

namespace Database\Factories;

use App\Models\SupportDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportDetail>
 *
 * Valores por defecto equivalentes a los de SupportController@store
 * (detalle recién creado, en ATC, pendiente). El SupportSeeder sobreescribe
 * los campos para generar flujos coherentes (derivado, atendido, cerrado).
 */
class SupportDetailFactory extends Factory
{
    protected $model = SupportDetail::class;

    public const SUBJECTS = [
        'Avance de Proyecto', 'Boletas', 'Cesion', 'Cita con legal', 'Certificado de lote',
        'Constancia de no adeudo', 'Desestimiento', 'EE.CC', 'Formalización',
        'Información de su lote', 'Pagos', 'Recojo de contrato', 'Recojo de Letras',
        'Traspaso de aportes', 'Visita a proyecto',
    ];

    public function definition(): array
    {
        $faker = fake('es_ES');
        $createdAt = $faker->dateTimeBetween('-90 days', 'now');

        return [
            'subject' => $faker->randomElement(self::SUBJECTS),
            'description' => $faker->sentence(12),
            'priority' => $faker->randomElement(['Baja', 'Media', 'Alta']),
            'type' => 'Consulta',
            'status' => 'Pendiente',
            'reservation_time' => $createdAt,
            'attended_at' => (clone $createdAt)->modify('+1 hour'),
            'area_id' => 1,              // ATC
            'internal_state_id' => 3,    // Pendiente
            'external_state_id' => 1,    // Por Asignar
            'ticket' => 'TK-',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }
}
