<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos para recibir sílabos aprobados desde silabos_v2 (POST /api/silabos/ingest).
 *
 * Es aditiva: las filas existentes (subidas por PDF) quedan con source = 'upload'
 * y el resto de columnas en NULL, así que la carga antigua sigue funcionando igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('syllabus', function (Blueprint $table) {
            // Las filas que llegan por API no tienen PDF.
            $table->string('filename')->nullable()->change();
            $table->string('path')->nullable()->change();

            // 'superseded': llegó por API pero ya hay una versión vigente mayor; no se procesa.
            $table->enum('status', ['pending', 'processing', 'processed', 'failed', 'superseded'])
                ->default('pending')
                ->change();

            $table->string('source', 20)->default('upload')->after('id');
            $table->string('external_model', 10)->nullable()->after('source');
            $table->unsignedBigInteger('external_id')->nullable()->after('external_model');
            $table->string('codigo_familia', 50)->nullable()->after('external_id');
            $table->unsignedInteger('external_version')->nullable()->after('codigo_familia');
            $table->string('periodo', 20)->nullable()->after('external_version');
            $table->unsignedInteger('periodo_orden')->nullable()->after('periodo');
            $table->unsignedTinyInteger('tipo_plantilla')->nullable()->after('periodo_orden');
            $table->string('course_code', 50)->nullable()->after('tipo_plantilla');
            $table->string('course_name')->nullable()->after('course_code');
            $table->foreignId('course_id')->nullable()->after('course_name')
                ->constrained('courses')->nullOnDelete();
            $table->json('careers')->nullable()->after('course_id');
            $table->timestamp('approved_at')->nullable()->after('careers');
            $table->string('approved_at_source', 30)->nullable()->after('approved_at');
            $table->json('content')->nullable()->after('approved_at_source');
            $table->char('content_hash', 64)->nullable()->after('content');
            $table->timestamp('superseded_at')->nullable()->after('content_hash');
            $table->unsignedBigInteger('superseded_by')->nullable()->after('superseded_at');

            $table->unique(['source', 'external_model', 'external_id'], 'syllabus_external_unique');
            $table->index(['source', 'external_model', 'codigo_familia'], 'syllabus_family_index');
            $table->index('superseded_at');
        });
    }

    public function down(): void
    {
        Schema::table('syllabus', function (Blueprint $table) {
            $table->dropUnique('syllabus_external_unique');
            $table->dropIndex('syllabus_family_index');
            $table->dropIndex(['superseded_at']);
            $table->dropConstrainedForeignId('course_id');
            $table->dropColumn([
                'source', 'external_model', 'external_id', 'codigo_familia', 'external_version',
                'periodo', 'periodo_orden', 'tipo_plantilla', 'course_code', 'course_name',
                'careers', 'approved_at', 'approved_at_source', 'content', 'content_hash',
                'superseded_at', 'superseded_by',
            ]);
        });
        // filename/path/status no se revierten: volver a NOT NULL fallaría si ya hay filas por API.
    }
};
