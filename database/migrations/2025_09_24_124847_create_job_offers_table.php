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
        Schema::create('job_offers', function (Blueprint $table) {
    $table->id();

    // Datos básicos del empleo
    $table->string('title');                   // Título del empleo
    $table->string('company')->nullable();     // Empresa
    $table->string('country')->nullable();     // País
    $table->string('city')->nullable();        // Ciudad
    $table->string('location')->nullable();    // País/ciudad (legacy opcional)
    $table->string('modality')->nullable();    // Remoto, híbrido, presencial
    $table->string('workload')->nullable();    // Full-time, part-time, freelance

    // Salario
    $table->decimal('salary_min', 12, 2)->nullable();
    $table->decimal('salary_max', 12, 2)->nullable();
    $table->string('currency', 10)->nullable();

    // Fuente
    $table->string('source');                  // GetOnBoard, ISIL, etc.
    $table->string('external_id')->nullable(); // ID en la fuente
    $table->string('url')->nullable();         // Link a la oferta

    // Fechas
    $table->timestamp('published_at')->nullable();
    $table->timestamps(); // created_at, updated_at
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_offers');
    }
};
