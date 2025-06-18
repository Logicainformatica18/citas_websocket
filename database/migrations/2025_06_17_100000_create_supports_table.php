<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supports', function (Blueprint $table) {
            $table->id();

            $table->integer('client_id')->nullable();
            $table->foreign('client_id')->references('id_cliente')->on('clientes')->onDelete('cascade');
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');


            $table->string('cellphone')->nullable();
            $table->string('state')->nullable();

            $table->enum('status_global', ['Simple', 'Múltiple']);
            $table->timestamps();


        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supports');
    }
};
