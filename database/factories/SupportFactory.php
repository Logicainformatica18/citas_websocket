<?php

namespace Database\Factories;

use App\Models\Support;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Support>
 *
 * Cabecera de la solicitud (ticket). Requiere client_id y created_by.
 * status_global = '¿Con cita?' en el frontend: 'Sí' | 'No'.
 */
class SupportFactory extends Factory
{
    protected $model = Support::class;

    public function definition(): array
    {
        $createdAt = fake('es_ES')->dateTimeBetween('-90 days', 'now');

        return [
            'cellphone' => null,
            'state' => null,
            'status_global' => 'No',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }
}
