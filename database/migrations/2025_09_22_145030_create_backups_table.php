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
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scraping_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_id');          // correlativo de la fila
            $table->json('data');                       // valores dinámicos (menu, carrera, url, etc.)
            $table->boolean('reviewed')->default(false); // si ya fue validado o no
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scraping_backups');
    }
};
