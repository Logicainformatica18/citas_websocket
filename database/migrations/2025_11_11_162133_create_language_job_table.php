<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */

      public function up()
{
    Schema::create('language_job', function (Blueprint $table) {
        $table->id();
        $table->foreignId('language_id')->constrained()->cascadeOnDelete();
        $table->foreignId('job_offer_id')->constrained()->cascadeOnDelete();
        $table->timestamps();

        $table->unique(['language_id', 'job_offer_id']); // evita duplicados
    });
}



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('language_job');
    }
};
