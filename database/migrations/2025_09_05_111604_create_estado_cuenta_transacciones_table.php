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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // Archivo original subido
            $table->string('file_1')->nullable(); // Ruta de la imagen o PDF original

            // Metadatos generales (pueden estar vacíos al inicio y llenarse tras el análisis)
            $table->string('process_date', 10)->nullable();
            $table->string('value_date', 10)->nullable();
            $table->string('description')->nullable();
            $table->string('location')->nullable();
            $table->string('branch_code', 20)->nullable();
            $table->string('operation_number', 50)->nullable();
            $table->string('time', 10)->nullable();
            $table->string('origin')->nullable();
            $table->string('transaction_type', 50)->nullable();

            // Montos
            $table->decimal('debit', 15, 2)->nullable();
            $table->decimal('credit', 15, 2)->nullable();
            $table->decimal('balance', 15, 2)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
