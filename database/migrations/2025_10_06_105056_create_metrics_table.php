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
        Schema::create('metrics', function (Blueprint $table) {
            $table->id();

            // 🔹 Tiempo y ubicación
            $table->integer('year');
            $table->string('quarter', 5)->nullable();           // Q1, Q2, Q3, Q4
            $table->string('country')->nullable();
            $table->string('city')->nullable();

            // 🔹 Tipo general de métrica
            $table->string('metric_type');                      // Ej: demand, alignment, obsolescence

            // 🔹 Subtipo (dimensión)
            $table->enum('dimension', [
                'language', 'technology', 'methodology', 'role', 'modality', 'other'
            ])->default('other');

            // 🔹 Clave del indicador
            $table->string('metric_key');                       // Ej: Python, React, Scrum, etc.
            $table->decimal('metric_value', 12, 2)->default(0);

            // 🔹 Fuente del dato
            $table->string('source')->nullable();               // Computrabajo, Adzuna, ISIL, etc.

            // 🔹 Origen académico (opcional)
            $table->unsignedBigInteger('career_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();

            $table->timestamps();

            // 🔹 Índice único para evitar duplicados de la misma métrica
            $table->unique(
                ['year', 'quarter', 'country', 'city', 'metric_type', 'dimension', 'metric_key', 'source'],
                'unique_metric'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metrics');
    }
};
