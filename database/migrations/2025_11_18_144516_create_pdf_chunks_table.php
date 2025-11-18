<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_chunks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pdf_id')->constrained('pdf_documents')->onDelete('cascade');

            $table->integer('chunk_index');
            $table->longText('content');     // Trozo de texto para resumen

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_chunks');
    }
};
