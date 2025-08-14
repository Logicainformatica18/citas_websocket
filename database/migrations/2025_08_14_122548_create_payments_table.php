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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            
            // Client info
            $table->string('email', 150)->index();
            $table->string('dni', 20)->index();
            $table->string('full_name', 200);
            
            // Payment info
            $table->string('receipt_number', 100)->nullable(); // comprobante
            $table->decimal('amount', 10, 2);
            $table->text('details')->nullable();
            
            // Project info
               $table->integer('project_id')->nullable();
            $table->string('mz_lote', 50)->nullable(); // MZ-Lote
            $table->string('file_1', 50)->nullable(); // MZ-Lote
            $table->string('file_2', 50)->nullable(); // MZ-Lote
            $table->string('file_3', 50)->nullable(); // MZ-Lote
            
            
            $table->timestamps();

            // Foreign keys (si project_id es de otra tabla, p. ej. projects)
                 $table->foreign('project_id')->references('id_proyecto')->on('proyecto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
