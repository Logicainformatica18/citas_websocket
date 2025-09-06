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
        Schema::create('transaction_blocks', function (Blueprint $table) {
            $table->id();

            // Relación con transacción original
            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->onDelete('cascade');

            // Archivo del bloque recortado
            $table->string('file_path')->nullable(); // uploads/transactions/blocks/xxx.png

            // Texto extraído sin procesar
            $table->text('raw_text')->nullable();

            // Posiciones opcionales (debug o reprocesamiento)
            $table->integer('x')->nullable();
            $table->integer('y')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_blocks');
    }
};
