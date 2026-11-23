<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 *
 * Una "venta" = lote adquirido por un cliente en un proyecto.
 * Requiere id_cliente; si no se pasa project_id toma uno al azar.
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        $faker = fake('es_ES');

        return [
            'project_id' => fn () => Project::inRandomOrder()->value('id_proyecto'),
            'code' => 'VTA-' . $faker->unique()->numerify('######'),
            'holder' => null,
            'stage' => $faker->randomElement(['Etapa 1', 'Etapa 2', 'Etapa 3']),
            'mz_lote' => 'Mz. ' . $faker->randomElement(range('A', 'P')) . ' Lt. ' . $faker->numberBetween(1, 40),
            'state' => $faker->randomElement(['Al día', 'Al día', 'Al día', 'Con deuda', 'Cancelado']),
        ];
    }
}
