<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_summaries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pdf_id')->constrained('pdf_documents')->onDelete('cascade');

            $table->text('summary_short')->nullable();
            $table->text('summary_medium')->nullable();
            $table->longText('summary_long')->nullable();

            $table->json('insights_json')->nullable();      // Insights globales
            $table->json('topics_json')->nullable();        // Temas detectados

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_summaries');
    }
};
