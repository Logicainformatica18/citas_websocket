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
        Schema::create('technology_metrics', function (Blueprint $table) {
            $table->id();

            // 🔗 Relación con la tabla 'technologies'
            $table->foreignId('technology_id')->constrained('technologies')->onDelete('cascade');

            // 🧠 Datos de la métrica
            $table->string('technology_name')->nullable(); // opcional pero útil para reportes
            $table->integer('jobs_found_count')->default(0);
            $table->integer('jobs_new_count')->default(0);

            // 🌍 Distribuciones en formato JSON
            $table->json('countries_breakdown')->nullable();
            $table->json('modality_breakdown')->nullable();

            // 📅 Fecha de ejecución y fuente
            $table->timestamp('run_date');
            $table->string('source')->default('GetOnBoard');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technology_metrics');
    }
};
