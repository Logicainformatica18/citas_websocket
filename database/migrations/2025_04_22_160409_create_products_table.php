<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable(); // Código interno o patrimonial
            $table->string('sku')->nullable(); // SKU del producto
            $table->string('description')->unique(); // Nombre del producto (ej. "Monitor LG 24”")
            $table->text('detail')->nullable(); // Detalles adicionales
            $table->string('brand')->nullable(); // Marca del producto (Dell, HP, etc)
            $table->string('model')->nullable(); // Modelo (ej. "P2419H")
            $table->string('serial_number')->nullable(); // Serie física
            $table->string('condition')->default('Nuevo'); // Estado (Nuevo, Usado, Reparado)
            $table->string('state')->default('Disponible'); // Estado en el sistema (Disponible, Asignado, Dañado)
            $table->integer('quantity')->default(1); // Cantidad disponible en stock
            $table->decimal('price', 10, 2)->nullable(); // Precio de compra o referencia
            $table->string('location')->nullable(); // Ubicación física en almacén (Estante A, etc.)
            $table->string('file_1')->nullable(); // Imagen o archivo
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
