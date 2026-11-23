<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas de catálogo heredadas de la BD original (nombres y PK en español).
 *
 * Los modelos Project, Area, AppointmentType, WaitingDay, Motive, InternalState,
 * ExternalState y Type las usan, pero no existía migración para ellas.
 * Las PK de las tablas heredadas son INT con signo porque support_details y
 * sales ya las referencian con $table->integer(...).
 *
 * Se protegen con hasTable() para no fallar en una BD donde ya existen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('proyecto')) {
            Schema::create('proyecto', function (Blueprint $table) {
                $table->integer('id_proyecto', true);
                $table->string('descripcion', 200);
                $table->boolean('habilitado')->default(true);
            });
        }

        if (! Schema::hasTable('areas')) {
            Schema::create('areas', function (Blueprint $table) {
                $table->integer('id_area', true);
                $table->string('descripcion', 50);
                $table->boolean('habilitado')->default(true);
            });
        }

        if (! Schema::hasTable('tipos_cita')) {
            Schema::create('tipos_cita', function (Blueprint $table) {
                $table->integer('id_tipo_cita', true);
                $table->string('tipo', 255);
                $table->boolean('habilitado')->default(true);
            });
        }

        if (! Schema::hasTable('dias_espera')) {
            Schema::create('dias_espera', function (Blueprint $table) {
                $table->integer('id_dias_espera', true);
                $table->string('dias', 255);
                $table->boolean('habilitado')->default(true);
            });
        }

        if (! Schema::hasTable('motivos_cita')) {
            Schema::create('motivos_cita', function (Blueprint $table) {
                $table->integer('id_motivos_cita', true);
                $table->string('nombre_motivo', 255);
                $table->integer('id_tipo_cita')->nullable();
                $table->integer('id_dia_espera')->nullable();
                $table->integer('id_area')->nullable();
                $table->integer('id_areap')->nullable();
                $table->boolean('habilitado')->default(true);

                $table->foreign('id_tipo_cita')->references('id_tipo_cita')->on('tipos_cita')->nullOnDelete();
                $table->foreign('id_dia_espera')->references('id_dias_espera')->on('dias_espera')->nullOnDelete();
                $table->foreign('id_area')->references('id_area')->on('areas')->nullOnDelete();
            });
        }

        // InternalState / ExternalState usan latest() → necesitan created_at.
        if (! Schema::hasTable('internal_states')) {
            Schema::create('internal_states', function (Blueprint $table) {
                $table->id();
                $table->string('description', 255);
                $table->text('detail')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('external_states')) {
            Schema::create('external_states', function (Blueprint $table) {
                $table->id();
                $table->string('description', 255);
                $table->text('detail')->nullable();
                $table->timestamps();
            });
        }

        // Type tiene $timestamps = false.
        if (! Schema::hasTable('types')) {
            Schema::create('types', function (Blueprint $table) {
                $table->id();
                $table->string('description', 255);
                $table->text('detail')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('types');
        Schema::dropIfExists('external_states');
        Schema::dropIfExists('internal_states');
        Schema::dropIfExists('motivos_cita');
        Schema::dropIfExists('dias_espera');
        Schema::dropIfExists('tipos_cita');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('proyecto');
    }
};
