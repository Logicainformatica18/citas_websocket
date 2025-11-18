<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('source')->nullable();        // Ej: WEF, OECD, etc.
            $table->integer('year')->nullable();
            $table->boolean('processed')->default(false); // Pipeline completado
            $table->string('file_path', 500);            // Ruta del PDF
            $table->integer('total_pages')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_documents');
    }
};
