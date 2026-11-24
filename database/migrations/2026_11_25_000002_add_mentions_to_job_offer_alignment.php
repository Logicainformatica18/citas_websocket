<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * job_offer_alignment.mentions: cuántas entidades (tecnologías, lenguajes o metodologías, según
 * source) de los cursos de la carrera menciona la oferta. El ranking de ciudades de Demanda
 * geográfica suma menciones (criterio de los indicadores que llegan a una carrera por sus
 * tecnologías). La llena jobs:build-alignment; las filas existentes quedan con 1 hasta reconstruir.
 * Idempotente; tabla de ~500 000 filas: ALGORITHM=INSTANT (MySQL 8), no bloquea.
 * Equivale a docs/produccion/indicadores_optimizacion/02_alineamiento_menciones.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('job_offer_alignment') && ! Schema::hasColumn('job_offer_alignment', 'mentions')) {
            DB::statement('ALTER TABLE job_offer_alignment ADD COLUMN mentions SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER source, ALGORITHM=INSTANT');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('job_offer_alignment', 'mentions')) {
            DB::statement('ALTER TABLE job_offer_alignment DROP COLUMN mentions, ALGORITHM=INSTANT');
        }
    }
};
