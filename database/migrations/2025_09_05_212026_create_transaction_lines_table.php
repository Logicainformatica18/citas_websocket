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
        Schema::create('transaction_lines', function (Blueprint $table) {
            $table->id();

            // Relación con transactions (archivo original)
            $table->foreignId('transaction_id')
                  ->constrained('transactions')
                  ->onDelete('cascade');

            // Relación con transaction_blocks (recorte de la imagen)
            $table->foreignId('transaction_block_id')
                  ->nullable()
                  ->constrained('transaction_blocks')
                  ->onDelete('cascade');

            // Campos extraídos por GPT
            $table->string('process_date', 20)->nullable();      // fecha proceso
            $table->string('value_date', 20)->nullable();        // fecha valor
            $table->string('description')->nullable();           // descripción
            $table->string('place')->nullable();                 // lugar
            $table->string('branch_code', 20)->nullable();       // suc/age
            $table->string('operation_number', 50)->nullable();  // nro operación
            $table->string('time', 20)->nullable();              // hora
            $table->string('origin')->nullable();                // origen
            $table->string('transaction_type', 50)->nullable();  // tipo

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
        Schema::dropIfExists('transaction_lines');
    }
};
