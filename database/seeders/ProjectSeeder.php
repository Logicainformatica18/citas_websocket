<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\OnlyInDemoEnvironments;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Proyectos inmobiliarios ficticios para la demo (el último, deshabilitado). */
class ProjectSeeder extends Seeder
{
    use OnlyInDemoEnvironments;

    public const PROJECTS = [
        'Los Jardines de Carabayllo',
        'Villa del Sol - Huaral',
        'Brisas de Chilca',
        'Praderas de Pachacámac',
        'Mirador de Cieneguilla',
        'Alameda de Lurín',
        'Valle Hermoso - Cañete',
        'Las Lomas de Ancón',
    ];

    public function run(): void
    {
        if ($this->skipOutsideDemo()) {
            return;
        }

        DB::table('proyecto')->insertOrIgnore(collect(self::PROJECTS)->map(fn ($d, $i) => [
            'id_proyecto' => $i + 1,
            'descripcion' => $d,
            'habilitado' => $i !== count(self::PROJECTS) - 1,
        ])->all());
    }
}
