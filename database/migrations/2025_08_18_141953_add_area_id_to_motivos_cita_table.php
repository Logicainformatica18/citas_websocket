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
        Schema::create('motivos_cita_area', function (Blueprint $table) {
           $table->id();
                 $table->integer('area_id')->nullable();
            $table->integer('id_motivos_cita')->nullable();
             $table->foreign('area_id')->references('id_area')->on('areas')->onDelete('set null');
             
            $table->foreign('id_motivos_cita')->references('id_motivos_cita')->on('motivos_cita')->onDelete('set null');

               $table->timestamps();
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('motivos_cita', function (Blueprint $table) {
            //
        });
    }
};
