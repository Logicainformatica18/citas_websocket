<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
   Schema::create('image_analyses', function (Blueprint $table) {
    $table->id();
    $table->string('filename');                    // Original filename (e.g., voucher.png)
    $table->string('company_name')->nullable();    // Razon Social
    $table->string('operation_number')->nullable(); // Numero de operación
    $table->decimal('amount', 10, 2)->nullable();   // Monto
    $table->date('date')->nullable();              // Fecha
    $table->time('time')->nullable();              // Hora
    $table->string('phone')->nullable();           // Celular
    $table->string('status')->nullable();          // Status: Admitted / Not Admitted
    $table->string('concept')->nullable();         // Concept
    $table->string('path')->nullable();            // Image storage path
    $table->longText('response')->nullable();      // Full OCR response from Google
    $table->timestamps();                          // created_at & updated_at
});


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_analyses');
    }
};
