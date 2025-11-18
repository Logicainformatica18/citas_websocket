<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_tables', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pdf_page_id')->constrained('pdf_pages')->onDelete('cascade');

            $table->integer('table_index')->default(1);

            $table->json('data_json')->nullable();          // Datos de tabla en JSON
            $table->json('insights_json')->nullable();      // Análisis por IA

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_tables');
    }
};
