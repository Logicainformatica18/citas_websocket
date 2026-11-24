<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas resumen de los indicadores de análisis (todos menos los rankings de Lenguajes,
 * Tecnologías y Certificaciones, que tienen las suyas). Las llena indicators:refresh-all
 * (App\Services\Indicators\IndicatorFacts) y las páginas solo las leen.
 *
 *   indicator_facts      una fila por (indicador, año, periodo, combinación de dimensiones) con
 *                        sus conteos; los filtros de la página se aplican al leer (SUM … GROUP BY).
 *   indicator_fact_runs  último cálculo de cada (indicador, año, periodo): fecha y hora, filas,
 *                        duración y memoria. Si falta, la página calcula en vivo.
 *
 * Idempotente: no falla si las tablas ya existen. down() las quita: son datos derivados que se
 * regeneran con indicators:refresh-all.
 * Equivale a docs/produccion/indicadores_optimizacion/01_tablas_resumen.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('indicator_facts')) {
            Schema::create('indicator_facts', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('indicator', 40);
                $t->unsignedSmallInteger('year');
                $t->char('period', 2);                       // s1 | s2
                $t->unsignedTinyInteger('month')->nullable();
                $t->unsignedBigInteger('career_id')->nullable();
                $t->string('seniority', 30)->nullable();
                $t->string('modality', 40)->nullable();
                $t->string('region', 100)->nullable();
                $t->string('country', 150)->nullable();
                $t->string('city', 150)->nullable();
                $t->string('source', 80)->nullable();
                $t->string('company', 255)->nullable();
                $t->unsignedInteger('n')->default(0);       // conteo principal (ofertas o menciones)
                $t->unsignedInteger('n2')->nullable();      // conteo secundario (p. ej. ofertas únicas)
                $t->double('lat_sum')->nullable();
                $t->double('lng_sum')->nullable();
                $t->dateTime('calculated_at');
                $t->index(['indicator', 'year', 'period', 'career_id'], 'idx_if_ind_period');
            });
        }

        if (! Schema::hasTable('indicator_fact_runs')) {
            Schema::create('indicator_fact_runs', function (Blueprint $t) {
                $t->string('indicator', 40);
                $t->unsignedSmallInteger('year');
                $t->char('period', 2);
                $t->dateTime('calculated_at');
                $t->unsignedInteger('rows')->default(0);
                $t->unsignedInteger('ms')->default(0);
                $t->unsignedInteger('peak_mb')->default(0);
                $t->primary(['indicator', 'year', 'period']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_fact_runs');
        Schema::dropIfExists('indicator_facts');
    }
};
