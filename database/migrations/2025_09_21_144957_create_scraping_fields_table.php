<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
      Schema::create('scraping_fields', function (Blueprint $table) {
    $table->id();
    $table->foreignId('scraping_id')->constrained()->cascadeOnDelete();
    $table->foreignId('parent_id')->nullable()->constrained('scraping_fields')->cascadeOnDelete(); // relación recursiva
    $table->string('field_name');        // nombre lógico del campo
    $table->string('selector_type');     // id, class, tag, attribute, text, css
    $table->string('selector_value');    // valor asociado (ej. menu-cursos, nav-link, a[href*="carreras"])
    $table->string('attr')->nullable();  // 👈 atributo opcional (href, src, alt, etc.)
    $table->string('path')->nullable();  // URL relativa dentro del sitio
    $table->timestamps();
});


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scraping_fields');
    }
};
