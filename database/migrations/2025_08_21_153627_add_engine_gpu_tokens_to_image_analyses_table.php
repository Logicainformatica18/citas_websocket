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
     Schema::table('image_analyses', function (Blueprint $table) {
    $table->string('engine')->nullable();   // ej: easyocr, tesseract
    $table->boolean('gpu')->default(false);
    $table->integer('tokens')->nullable();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('image_analyses', function (Blueprint $table) {
            //
        });
    }
};
