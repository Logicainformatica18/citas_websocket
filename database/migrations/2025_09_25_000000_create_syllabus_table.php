<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syllabus', function (Blueprint $table) {
            $table->id();
            $table->string('filename'); // nombre original del archivo
            $table->string('path'); // ruta en storage/app
            $table->enum('status', ['pending', 'processing', 'processed', 'failed'])->default('pending');
            
            $table->longText('raw_text')->nullable(); // texto extraído del pdf
            $table->json('structured_data')->nullable(); // JSON con curso/tecnologías/lenguajes/metodologías
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syllabus');
    }
};
