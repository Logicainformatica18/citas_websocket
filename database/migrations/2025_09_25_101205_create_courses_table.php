<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla principal de cursos
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Catálogo de lenguajes
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Catálogo de tecnologías
        Schema::create('technologies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Catálogo de metodologías
        Schema::create('methodologies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Pivote: cursos ↔ lenguajes
        Schema::create('course_language', function (Blueprint $table) {
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->primary(['course_id', 'language_id']);
        });

        // Pivote: cursos ↔ tecnologías
        Schema::create('course_technology', function (Blueprint $table) {
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technology_id')->constrained()->cascadeOnDelete();
            $table->primary(['course_id', 'technology_id']);
        });

        // Pivote: cursos ↔ metodologías
        Schema::create('course_methodology', function (Blueprint $table) {
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('methodology_id')->constrained()->cascadeOnDelete();
            $table->primary(['course_id', 'methodology_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_methodology');
        Schema::dropIfExists('course_technology');
        Schema::dropIfExists('course_language');
        Schema::dropIfExists('methodologies');
        Schema::dropIfExists('technologies');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('courses');
    }
};
