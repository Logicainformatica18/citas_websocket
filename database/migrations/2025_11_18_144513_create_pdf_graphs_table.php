<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_graphs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pdf_page_id')->constrained('pdf_pages')->onDelete('cascade');

            $table->integer('graph_index')->default(1); // Por si hay varios gráficos por página

            $table->string('image_path', 500)->nullable(); // Recorte del gráfico
            $table->string('title')->nullable();

            $table->json('data_json')->nullable();         // Dataset reconstruido
            $table->json('legend_json')->nullable();       // Leyendas detectadas
            $table->json('insights_json')->nullable();     // Insights generados por IA

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_graphs');
    }
};
