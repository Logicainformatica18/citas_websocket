<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exclusión de ofertas por título (roles que no son de TI). Todo en una migración para poder
 * ejecutarla sola:
 *   php artisan migrate --path=database/migrations/2026_11_23_000001_create_job_title_exclusions.php
 *
 *   job_title_exclusion_keywords   lista de patrones (excluir / excepción), con la semilla de
 *                                  database/data/job_title_exclusions/seed.php
 *   job_offer_processes            procesos en cola del módulo (aplicar, analizar, eliminar,
 *                                  restaurar, sugerir con IA, exportar): progreso y registro
 *   job_title_exclusion_candidates ofertas que excluiría la lista en un análisis
 *   job_offer_deletion_backups     respaldo (JSON por fila) de lo que borra "Eliminar definitivamente"
 *   job_offers.title_exclusion_id  NULL = oferta válida; si no, el patrón que la excluyó
 *
 * Idempotente. Equivalente para el DBA: docs/produccion/ofertas_exclusion/01_exclusion_titulos.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('job_title_exclusion_keywords')) {
            Schema::create('job_title_exclusion_keywords', function (Blueprint $table) {
                $table->id();
                $table->string('pattern', 255)->collation('utf8mb4_bin');
                $table->string('type', 10)->default('exclude');        // exclude | exception
                $table->string('match_mode', 10)->default('text');     // text | regex
                $table->boolean('whole_word')->default(false);
                $table->string('category', 60)->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('origin', 10)->default('manual');       // seed | manual | ai
                $table->text('notes')->nullable();
                $table->unsignedInteger('matches_count')->nullable();  // ofertas que coinciden (último cálculo)
                $table->timestamp('counted_at')->nullable();
                $table->timestamps();
                $table->unique(['type', 'match_mode', 'whole_word', 'pattern'], 'uq_jtek_pattern');
                $table->index(['is_active', 'type'], 'idx_jtek_active');
            });
        }

        if (DB::table('job_title_exclusion_keywords')->where('origin', 'seed')->doesntExist()) {
            $now = now();
            $rows = array_map(fn ($r) => [
                'pattern' => $r[0],
                'type' => 'exclude',
                'match_mode' => $r[1],
                'whole_word' => $r[2],
                'category' => $r[3],
                'is_active' => 1,
                'origin' => 'seed',
                'notes' => 'PATRONES_EXCLUIR de exportar_periodos_ti.py',
                'created_at' => $now,
                'updated_at' => $now,
            ], require database_path('data/job_title_exclusions/seed.php'));
            DB::table('job_title_exclusion_keywords')->insertOrIgnore($rows);
        }

        if (! Schema::hasTable('job_offer_processes')) {
            Schema::create('job_offer_processes', function (Blueprint $table) {
                $table->id();
                $table->string('type', 20);                 // apply | analyze | delete | restore | suggest | export
                $table->string('status', 12)->default('queued'); // queued | running | done | failed
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_name', 255)->nullable();
                $table->json('params')->nullable();
                $table->unsignedInteger('total')->default(0);
                $table->unsignedInteger('processed')->default(0);
                $table->unsignedBigInteger('cursor_id')->nullable();
                $table->json('state')->nullable();          // acumuladores entre Jobs encadenados
                $table->json('result')->nullable();
                $table->text('message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
                $table->index(['type', 'status'], 'idx_jop_type_status');
            });
        }

        if (! Schema::hasTable('job_title_exclusion_candidates')) {
            Schema::create('job_title_exclusion_candidates', function (Blueprint $table) {
                $table->unsignedBigInteger('process_id');
                $table->unsignedBigInteger('job_offer_id');
                $table->unsignedBigInteger('keyword_id');
                $table->primary(['process_id', 'job_offer_id']);
                $table->index('job_offer_id', 'idx_jtec_offer');
            });
        }

        if (! Schema::hasTable('job_offer_deletion_backups')) {
            Schema::create('job_offer_deletion_backups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('process_id');
                $table->string('table_name', 64);
                $table->unsignedBigInteger('job_offer_id');
                $table->longText('payload');                // la fila completa en JSON
                $table->timestamp('restored_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['process_id', 'table_name'], 'idx_jodb_process');
                $table->index('job_offer_id', 'idx_jodb_offer');
            });
        }

        if (! Schema::hasColumn('job_offers', 'title_exclusion_id')) {
            Schema::table('job_offers', function (Blueprint $table) {
                $table->unsignedBigInteger('title_exclusion_id')->nullable();
            });
        }

        $this->addIndex('job_offers', 'idx_jo_title_excl', ['title_exclusion_id']);
        // Cubre la consulta del ranking (rango de published_at + filtro de exclusión, sin leer la fila)
        $this->addIndex('job_offers', 'idx_jo_pub_excl', ['published_at', 'title_exclusion_id']);

        // Combos del listado /job-offers (SELECT DISTINCT col): sin índice cada uno recorre la tabla
        // (1,7-2,9 s cada uno con 400 000 ofertas); con índice es un recorrido del índice
        foreach (self::COMBO_COLUMNS as $column) {
            $this->addIndex('job_offers', "idx_jo_{$column}", [$column]);
        }
    }

    private const COMBO_COLUMNS = ['company', 'city', 'modality', 'job_type', 'remote_type', 'workload'];

    public function down(): void
    {
        $combo = array_map(fn ($c) => "idx_jo_{$c}", self::COMBO_COLUMNS);
        foreach (array_merge(['idx_jo_title_excl', 'idx_jo_pub_excl'], $combo) as $idx) {
            if ($this->hasIndex('job_offers', $idx)) {
                Schema::table('job_offers', fn (Blueprint $t) => $t->dropIndex($idx));
            }
        }
        if (Schema::hasColumn('job_offers', 'title_exclusion_id')) {
            Schema::table('job_offers', fn (Blueprint $t) => $t->dropColumn('title_exclusion_id'));
        }
        // job_offer_deletion_backups se conserva: puede tener el único respaldo de ofertas borradas
        Schema::dropIfExists('job_title_exclusion_candidates');
        Schema::dropIfExists('job_offer_processes');
        Schema::dropIfExists('job_title_exclusion_keywords');
    }

    private function addIndex(string $table, string $name, array $columns): void
    {
        if (! $this->hasIndex($table, $name)) {
            Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn ($i) => $i['name'] === $name);
    }
};
