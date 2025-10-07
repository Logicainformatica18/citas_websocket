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
        Schema::create('technology_trends', function (Blueprint $table) {
            $table->id();
            $table->string('language');                // Nombre del lenguaje (ej: Python, JavaScript)
            $table->string('language_type')->nullable(); // Tipo (programming, markup, data, etc.)
            $table->string('iso2_code', 5)->nullable(); // Código país ISO2 (ej: PE, US, AE)
            $table->integer('year');                   // Año
            $table->tinyInteger('quarter');            // Trimestre (1 a 4)
            $table->bigInteger('num_pushers')->default(0); // Cantidad de usuarios
            $table->string('source')->default('Github');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technology_trends');
    }
};
