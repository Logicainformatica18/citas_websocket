<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Orden según dependencias de claves foráneas:
     * roles/permisos → usuarios → catálogos → clientes/lotes → solicitudes/detalles/comentarios.
     */
    public function run(): void
    {
        $this->call([
            PermissionsSeeder::class,
            DemoUserSeeder::class,
            CatalogSeeder::class,
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
