<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla job_offers tal como existe en producción (SHOW CREATE TABLE, 2026-11-17).
 *
 * Esta migración ya está registrada como ejecutada (batch 1) en las bases
 * existentes, así que no vuelve a correr allí. El guard de hasTable es una
 * segunda protección: si la tabla ya existe, no se toca nada.
 *
 * Las columnas functions y functions_headline se agregan en una migración
 * aparte (2026_11_17_000001_add_functions_to_job_offers_table).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('job_offers')) {
            return;
        }

        Schema::create('job_offers', function (Blueprint $table) {
            $table->id();

            // 📌 Datos básicos del empleo
            $table->string('title');
            $table->string('company')->nullable();
            $table->string('country')->nullable();
            $table->string('region', 100)->nullable();
            $table->string('region_normalized', 50)->nullable();
            $table->string('state_code', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('city_ascii')->nullable();
            $table->string('location')->nullable();
            $table->string('zip_code', 20)->nullable();

            // 📌 Modalidad y tipo de empleo
            $table->string('modality')->nullable();
            $table->string('job_type')->nullable();
            $table->string('remote_type', 100)->nullable();
            $table->string('workload')->nullable();
            $table->string('experience_level')->nullable();
            $table->enum('seniority', ['junior', 'mid', 'senior', 'unspecified'])->nullable()->default('unspecified');
            $table->string('education_level')->nullable();
            $table->string('education_field')->nullable();

            // 📌 Textos
            $table->text('certifications')->nullable();
            $table->text('requirements')->nullable();
            $table->text('skills')->nullable();
            $table->longText('description')->nullable();
            $table->text('benefits')->nullable();

            // 📌 Coordenadas
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();

            // 📌 Salario
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('compensation_type')->nullable();

            // 📌 Fuente y referencias
            $table->string('source');
            $table->string('search_query', 100)->nullable();
            $table->string('external_id')->nullable();
            $table->longText('url')->nullable();
            $table->string('application_url')->nullable();
            $table->string('application_type')->nullable();

            // 📌 Fechas
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expiry')->nullable();
            $table->timestamps();

            // 📌 Índices
            $table->index('country', 'idx_country');
            $table->index(['published_at', 'id'], 'idx_jo_pub');
            $table->index('published_at', 'idx_jo_published_at');
            $table->index('created_at', 'idx_jo_created_at');
            $table->index(['source', 'created_at'], 'idx_jo_source_created');
        });

        // Índices propios de MySQL: unique por prefijo sobre un LONGTEXT y FULLTEXT.
        // Las pruebas usan SQLite, donde no existen.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `job_offers` ADD UNIQUE KEY `unique_url` (`url`(255))');
            DB::statement('ALTER TABLE `job_offers` ADD FULLTEXT KEY `ft_job_content` (`title`, `skills`, `description`)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('job_offers');
    }
};
