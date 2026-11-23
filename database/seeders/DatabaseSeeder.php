<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\OnlyInDemoEnvironments;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use OnlyInDemoEnvironments;

    /**
     * Base (todos los entornos, idempotente): roles/permisos y catálogos.
     * Demo (solo local/testing): usuarios con contraseña conocida y datos falsos.
     * Orden según dependencias de claves foráneas.
     */
    public function run(): void
    {
        $this->call([
            PermissionsSeeder::class,
            CatalogSeeder::class,
        ]);

        if ($this->skipOutsideDemo()) {
            return;
        }

        $this->call([
            DemoUserSeeder::class,
            ProjectSeeder::class,
            ClientSeeder::class,
            SupportSeeder::class,
            ChatMessageSeeder::class,
            // Módulo de inventario (existente)
            TransferSeeder::class,
            ProductSeeder::class,
            // ArticleSeeder::class, // ArticleFactory descarga imágenes y no define product_id
        ]);
    }
}
