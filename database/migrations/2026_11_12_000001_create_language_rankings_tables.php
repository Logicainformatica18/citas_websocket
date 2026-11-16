<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas de resumen del Ranking de Lenguajes (/dashboard/ranking/languages).
 *
 * Las llena el comando `ranking:refresh-languages` (App\Services\LanguageRankingService).
 * La página solo lee de aquí: calcular el ranking en vivo tardaba ~60 s y superaba
 * el max_execution_time de 30 s.
 *
 * - language_rankings: una fila por lenguaje y semestre.
 * - language_ranking_meta: una fila por semestre con los totales de la cabecera
 *   y la fecha del último cálculo.
 */
return new class extends Migration
{
    /**
     * Idempotente: en producción estas tablas las crea el DBA con
     * docs/produccion/ranking_lenguajes/01_ranking_languages_tablas.sql.
     * Si después alguien ejecuta `migrate`, no falla ni las toca.
     */
    public function up(): void
    {
        if (! Schema::hasTable('language_rankings')) {
            $this->createLanguageRankings();
        }

        if (! Schema::hasTable('language_ranking_meta')) {
            $this->createLanguageRankingMeta();
        }
    }

    private function createLanguageRankings(): void
    {
        Schema::create('language_rankings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->enum('period', ['s1', 's2']);
            $table->unsignedBigInteger('market_entity_id');

            // Copia de market_entities al momento del cálculo
            $table->string('name');
            $table->boolean('has_isil')->default(false);
            $table->boolean('has_trend')->default(false);
            $table->string('classification', 20);

            // Métricas crudas
            $table->unsignedInteger('total_jobs')->default(0);
            $table->unsignedInteger('trend_reports')->default(0);

            // Puntajes normalizados 0-100 sin redondear. final_score se recalcula
            // al leer con los pesos vigentes; aquí queda el valor con los pesos
            // usados en el cálculo, solo como referencia.
            $table->decimal('labor_score', 9, 4)->default(0);
            $table->decimal('trend_score', 9, 4)->default(0);
            $table->decimal('final_score', 9, 4)->default(0);
            $table->unsignedInteger('position')->default(0);

            $table->timestamp('calculated_at');

            $table->unique(['year', 'period', 'market_entity_id'], 'uq_language_rankings_period_entity');
            $table->index(['year', 'period', 'final_score'], 'idx_language_rankings_period_score');
            $table->index('market_entity_id', 'idx_language_rankings_entity');
        });
    }

    private function createLanguageRankingMeta(): void
    {
        Schema::create('language_ranking_meta', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->enum('period', ['s1', 's2']);

            $table->unsignedInteger('vacantes_analizadas')->default(0);
            $table->unsignedInteger('reportes_analizados')->default(0);
            $table->unsignedInteger('max_labor')->default(1);
            $table->unsignedInteger('max_trend')->default(1);
            $table->decimal('labor_weight', 4, 3);
            $table->decimal('trend_weight', 4, 3);
            $table->unsignedInteger('rows_count')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);

            $table->timestamp('calculated_at');

            $table->unique(['year', 'period'], 'uq_language_ranking_meta_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('language_ranking_meta');
        Schema::dropIfExists('language_rankings');
    }
};
