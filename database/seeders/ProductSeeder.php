<?php

namespace Database\Seeders;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            Product::create([
                'code' => 'CODE-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'sku' => 'SKU-' . strtoupper(Str::random(6)),
                'description' => 'Producto de prueba ' . $i,
                'detail' => 'Este es un detalle simulado para el producto número ' . $i,
                'brand' => ['HP', 'Lenovo', 'Dell', 'Asus'][rand(0, 3)],
                'model' => 'Model-' . rand(100, 999),
                'serial_number' => strtoupper(Str::random(10)),
                'condition' => ['Nuevo', 'Usado', 'Reparado'][rand(0, 2)],
                'state' => ['Disponible', 'Asignado', 'Dañado'][rand(0, 2)],
                'quantity' => rand(1, 10),
                'price' => rand(100, 2000),
                'location' => 'Estante ' . chr(rand(65, 70)) . rand(1, 10),
                'file_1' => null,
            ]);
        }
    }
}
