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
        Schema::create('technology_trend_enricheds', function (Blueprint $table) {
            $table->id();

            // 🔹 Identificación general
            $table->string('language');                        // Ej: Python, JavaScript
            $table->string('language_type')->default('programming'); // Ej: markup / programming / scripting
            $table->string('iso2_code', 4);                    // Código de país ISO2 (ej: PE)
            $table->year('year');                              // Ej: 2025
            $table->tinyInteger('quarter');                    // 1–4

            // 🔹 Métricas cuantitativas
            $table->integer('num_repos')->default(0);          // Cantidad de repositorios analizados
            $table->integer('num_users')->default(0);          // Cantidad de usuarios asociados
            $table->bigInteger('num_pushes')->default(0);      // Total de pushes o commits (si se desea medir actividad)
            $table->bigInteger('total_bytes')->default(0);     // Tamaño total del código fuente (en bytes)
            $table->decimal('popularity_index', 8, 2)->default(0.00); // Índice ponderado 0–100

            // 🔹 Origen de los datos
            $table->string('source')->default('GitHub');       // Ej: GitHub, GitLab, Bitbucket, etc.

            $table->timestamps();

            // 🔹 Índices útiles para filtrado rápido
            $table->index(['language', 'iso2_code']);
            $table->index(['year', 'quarter']);
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technology_trends_enriched');
    }
};
