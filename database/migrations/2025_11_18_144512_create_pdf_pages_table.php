<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdf_id')->constrained('pdf_documents')->onDelete('cascade');

            $table->integer('page_number');

            $table->string('image_path', 500);              // Imagen PNG/JPG generada de la página
            $table->longText('text_content')->nullable();   // Texto OCR o GPT-mini

            $table->enum('content_type', ['text','graph','table','mixed','empty'])
                  ->default('text');

            $table->json('metadata_json')->nullable();         // Título detectado, sección, etc.
            $table->json('detected_elements')->nullable();     // {graph: true, table: false}

            $table->boolean('ai_processed')->default(false);   // OCR + clasificación lista

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_pages');
    }
};
