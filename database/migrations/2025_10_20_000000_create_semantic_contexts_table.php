<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * 🧠 Tabla maestra de contextos semánticos
         * Define áreas técnicas, roles y patrones de búsqueda comunes
         */
        Schema::create('semantic_contexts', function (Blueprint $table) {
            $table->id();
            $table->string('search_context', 100)->unique();   // Ej: cloud, backend, data science
            $table->string('role_name', 150)->nullable();       // Ej: Cloud Engineer, Backend Developer
            $table->string('keyword_pattern', 255)->nullable(); // Ej: "programador OR desarrollador backend"
            $table->text('description')->nullable();            // Explicación opcional del contexto
            $table->timestamps();
        });

        /**
         * 🔗 Enlazamos las tablas existentes (sin borrar nada)
         */
        Schema::table('technologies', function (Blueprint $table) {
            if (!Schema::hasColumn('technologies', 'context_id')) {
                $table->foreignId('context_id')
                      ->nullable()
                      ->constrained('semantic_contexts')
                      ->onDelete('set null');
            }
        });

        Schema::table('languages', function (Blueprint $table) {
            if (!Schema::hasColumn('languages', 'context_id')) {
                $table->foreignId('context_id')
                      ->nullable()
                      ->constrained('semantic_contexts')
                      ->onDelete('set null');
            }
        });

        Schema::table('methodologies', function (Blueprint $table) {
            if (!Schema::hasColumn('methodologies', 'context_id')) {
                $table->foreignId('context_id')
                      ->nullable()
                      ->constrained('semantic_contexts')
                      ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('technologies', function (Blueprint $table) {
            if (Schema::hasColumn('technologies', 'context_id')) {
                $table->dropConstrainedForeignId('context_id');
            }
        });

        Schema::table('languages', function (Blueprint $table) {
            if (Schema::hasColumn('languages', 'context_id')) {
                $table->dropConstrainedForeignId('context_id');
            }
        });

        Schema::table('methodologies', function (Blueprint $table) {
            if (Schema::hasColumn('methodologies', 'context_id')) {
                $table->dropConstrainedForeignId('context_id');
            }
        });

        Schema::dropIfExists('semantic_contexts');
    }
};
