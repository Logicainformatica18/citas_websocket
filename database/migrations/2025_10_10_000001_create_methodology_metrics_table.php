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
        Schema::create('methodology_metrics', function (Blueprint $table) {
            $table->id();

            // 🔗 Relación con methodologies
            $table->foreignId('methodology_id')->constrained('methodologies')->onDelete('cascade');

            // 📊 Datos agregados
            $table->string('methodology_name')->nullable();
            $table->integer('jobs_found_count')->default(0);
            $table->integer('jobs_new_count')->default(0);

            // 🌍 JSON breakdowns
            $table->json('countries_breakdown')->nullable();
            $table->json('modality_breakdown')->nullable();

            // 🕒 Fecha de ejecución y fuente
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
        Schema::dropIfExists('methodology_metrics');
    }
};
