<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas del modal "Evolución" del Ranking de Lenguajes: una FOTO del ranking
 * semestral en la fecha de corte de cada ventana (semanas ISO, quincenas, meses).
 *
 * Idempotente: en producción lo aplica el DBA con
 * docs/produccion/ranking_lenguajes/06_ranking_languages_evolucion.sql.
 * Si existen con la estructura anterior (sin cutoff_date) se recrean: solo tienen
 * datos calculados que regenera ranking:refresh-languages.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['language_ranking_weekly', 'language_ranking_weekly_meta'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'cutoff_date')) {
                Schema::drop($table);
            }
        }

        if (! Schema::hasTable('language_ranking_weekly')) {
            Schema::create('language_ranking_weekly', function (Blueprint $table) {
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

                $table->unique(['period_type', 'period_key', 'market_entity_id'], 'uq_lrw_window_entity');
                $table->index(['period_type', 'year'], 'idx_lrw_type_year');
                $table->index('market_entity_id', 'idx_lrw_entity');
            });
        }

        if (! Schema::hasTable('language_ranking_weekly_meta')) {
            Schema::create('language_ranking_weekly_meta', function (Blueprint $table) {
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
                $table->unsignedInteger('languages_count')->default(0);
                $table->timestamp('calculated_at');

                $table->unique(['period_type', 'period_key'], 'uq_lrwm_window');
                $table->index(['period_type', 'year', 'start_date'], 'idx_lrwm_type_year_start');
                $table->index(['period_type', 'start_date'], 'idx_lrwm_type_start');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('language_ranking_weekly_meta');
        Schema::dropIfExists('language_ranking_weekly');
    }
};
