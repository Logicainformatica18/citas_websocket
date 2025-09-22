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
    $table->string('field_name');   // antes 'nombre_campo'
    $table->string('selector');     // igual
    $table->string('path')->nullable(); // antes 'direccion'
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
