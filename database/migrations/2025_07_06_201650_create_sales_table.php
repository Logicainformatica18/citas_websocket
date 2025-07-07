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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->integer('id_cliente')->nullable();
            $table->foreign('id_cliente')->references('id_cliente')->on('clientes')->onDelete('cascade');
            $table->string('code')->nullable();
            $table->string('holder')->nullable();
            $table->string('stage')->nullable();
            $table->string('mz_lote')->nullable();
            $table->string('state')->nullable();
            $table->integer('project_id')->nullable();
            $table->foreign('project_id')->references('id_proyecto')->on('proyecto')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
