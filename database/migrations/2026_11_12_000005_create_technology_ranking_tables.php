<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas de resumen del Ranking de Tecnologías (misma estructura que las de Lenguajes).
 * Las llena `ranking:refresh-technologies` (App\Services\TechnologyRankingService).
 *
 * Idempotente: en producción las crea el DBA con
 * docs/produccion/ranking_tecnologias/01_ranking_technologies_tablas.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('technology_rankings')) {
            Schema::create('technology_rankings', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('year');
                $table->enum('period', ['s1', 's2']);
                $table->unsignedBigInteger('market_entity_id');
                $table->string('name');
                $table->boolean('has_isil')->default(false);
                $table->boolean('has_trend')->default(false);
                $table->string('classification', 20);
                $table->unsignedInteger('total_jobs')->default(0);
                $table->unsignedInteger('trend_reports')->default(0);
                $table->decimal('labor_score', 9, 4)->default(0);
                $table->decimal('trend_score', 9, 4)->default(0);
                $table->decimal('final_score', 9, 4)->default(0);
                $table->unsignedInteger('position')->default(0);
                $table->timestamp('calculated_at');

                $table->unique(['year', 'period', 'market_entity_id'], 'uq_technology_rankings_period_entity');
                $table->index(['year', 'period', 'final_score'], 'idx_technology_rankings_period_score');
                $table->index('market_entity_id', 'idx_technology_rankings_entity');
            });
        }

        if (! Schema::hasTable('technology_ranking_meta')) {
            Schema::create('technology_ranking_meta', function (Blueprint $table) {
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

                $table->unique(['year', 'period'], 'uq_technology_ranking_meta_period');
            });
        }

        if (! Schema::hasTable('technology_ranking_weekly')) {
            Schema::create('technology_ranking_weekly', function (Blueprint $table) {
                $table->id();
                $table->enum('period_type', ['weekly', 'biweekly', 'monthly']);
                $table->string('period_key', 16);
                $table->unsignedSmallInteger('year');
                $table->date('cutoff_date');
                $table->char('semester', 7);
                $table->unsignedBigInteger('market_entity_id');
                $table->string('name');
                $table->unsignedInteger('jobs')->default(0);
                $table->unsignedInteger('new_jobs')->default(0);
                $table->unsignedInteger('trend_reports')->default(0);
                $table->decimal('labor_score', 9, 4)->default(0);
                $table->decimal('trend_score', 9, 4)->default(0);
                $table->decimal('final_score', 9, 4)->default(0);
                $table->unsignedInteger('position')->default(0);
                $table->timestamp('calculated_at');

                $table->unique(['period_type', 'period_key', 'market_entity_id'], 'uq_trw_window_entity');
                $table->index(['period_type', 'year'], 'idx_trw_type_year');
                $table->index('market_entity_id', 'idx_trw_entity');
            });
        }

        if (! Schema::hasTable('technology_ranking_weekly_meta')) {
            Schema::create('technology_ranking_weekly_meta', function (Blueprint $table) {
                $table->id();
                $table->enum('period_type', ['weekly', 'biweekly', 'monthly']);
                $table->string('period_key', 16);
                $table->unsignedSmallInteger('year');
                $table->string('label', 80);
                $table->date('start_date');
                $table->date('end_date');
                $table->date('cutoff_date');
                $table->char('semester', 7);
                $table->boolean('is_semester_start')->default(false);
                $table->boolean('is_partial')->default(false);
                $table->unsignedInteger('unique_offers')->default(0);
                $table->unsignedInteger('new_unique_offers')->default(0);
                $table->unsignedInteger('max_labor')->default(1);
                $table->unsignedInteger('max_trend')->default(1);
                $table->unsignedInteger('entities_count')->default(0);
                $table->timestamp('calculated_at');

                $table->unique(['period_type', 'period_key'], 'uq_trwm_window');
                $table->index(['period_type', 'year', 'start_date'], 'idx_trwm_type_year_start');
                $table->index(['period_type', 'start_date'], 'idx_trwm_type_start');
            });
        }
    }

    public function down(): void
    {
        foreach (['technology_ranking_weekly_meta', 'technology_ranking_weekly', 'technology_ranking_meta', 'technology_rankings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
