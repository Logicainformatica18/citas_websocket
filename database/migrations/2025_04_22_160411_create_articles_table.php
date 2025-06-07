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

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('details')->nullable();
            $table->integer('quanty')->default(0);
            $table->decimal('price', 10, 2)->nullable(); // Precio de compra o referencia

            // Nuevas columnas
            $table->string('code')->nullable(); // Código de inventario
            $table->string('condition')->nullable(); // Estado físico: nuevo, usado, dañado


            $table->string('state')->nullable();
            $table->bigInteger('transfer_id')->unsigned();
            $table->foreign('transfer_id')->references('id')->on('transfers')->onDelete('cascade');

            $table->bigInteger('product_id')->unsigned();
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');


            $table->string('file_1')->nullable();
            $table->string('file_2')->nullable();
            $table->string('file_3')->nullable();
            $table->string('file_4')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
