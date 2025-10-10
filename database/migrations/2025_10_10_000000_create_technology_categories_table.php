<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('technology_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Ej: framework, cloud, library, database, etc.
            $table->string('description')->nullable(); // Descripción opcional
            $table->timestamps();
        });

        // 👇 Añadir relación a technologies si ya existe
        if (Schema::hasTable('technologies')) {
            Schema::table('technologies', function (Blueprint $table) {
                if (!Schema::hasColumn('technologies', 'category_id')) {
                    $table->foreignId('category_id')
                          ->nullable()
                          ->constrained('technology_categories')
                          ->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('technologies')) {
            Schema::table('technologies', function (Blueprint $table) {
                if (Schema::hasColumn('technologies', 'category_id')) {
                    $table->dropForeign(['category_id']);
                    $table->dropColumn('category_id');
                }
            });
        }

        Schema::dropIfExists('technology_categories');
    }
};
