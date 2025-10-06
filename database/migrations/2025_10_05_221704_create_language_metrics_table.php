<?php

// database/migrations/2025_10_05_190000_create_language_metrics_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('language_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->integer('jobs_found_count')->default(0);
            $table->integer('jobs_new_count')->default(0);
            $table->json('countries_breakdown')->nullable(); // {"Peru":10,"Chile":5,"Remote":12}
            $table->json('modality_breakdown')->nullable();  // {"remote":50,"onsite":30,"hybrid":20}
            $table->timestamp('run_date');
            $table->string('source')->default('GetOnBoard');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('language_metrics');
    }
};
