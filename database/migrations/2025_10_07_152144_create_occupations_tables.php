<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('occupations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // ej. 15-1252.00
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->timestamps();
        });

        Schema::create('occupation_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occupation_id')->constrained()->onDelete('cascade');
            $table->string('skill_name');
            $table->string('category')->nullable(); // e.g. Basic Skills, Technical Skills
            $table->float('importance')->nullable();
            $table->float('level')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occupation_skills');
        Schema::dropIfExists('occupations');
    }
};
