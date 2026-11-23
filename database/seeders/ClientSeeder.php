<?php

namespace Database\Seeders;

use Database\Factories\ClientFactory;
use Database\Factories\SaleFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Clientes y sus lotes (tabla sales). Cada cliente tiene 1-2 lotes en
 * proyectos habilitados; las solicitudes se registran sobre esos lotes.
 */
class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $projectIds = DB::table('proyecto')->where('habilitado', true)->pluck('id_proyecto');

        ClientFactory::new()->count(60)->create()->each(function ($client) use ($projectIds) {
            $n = fake()->randomElement([1, 1, 1, 2]);
            foreach ($projectIds->random($n) as $projectId) {
                SaleFactory::new()->create([
                    'id_cliente' => $client->id_cliente,
                    'project_id' => $projectId,
                    'holder' => $client->Razon_Social,
                ]);
            }
        });

        // "T.Doc." no se puede escribir con el query builder (lo divide por los
        // puntos), por eso se actualiza con SQL crudo.
        $col = DB::getDriverName() === 'mysql' ? '`T.Doc.`' : '"T.Doc."';
        DB::statement("UPDATE clientes SET {$col} = 'DNI'");
    }
}
