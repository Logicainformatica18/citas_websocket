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
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();

            // Información básica
            $table->string('description')->nullable();
            $table->longText('details')->nullable();

            // Datos del emisor
            $table->string('sender_firstname')->nullable();
            $table->string('sender_lastname')->nullable();
            $table->string('sender_email')->nullable();

            // Datos del receptor
            $table->string('receiver_firstname')->nullable();
            $table->string('receiver_lastname')->nullable();
            $table->string('receiver_email')->nullable();

            // Archivo adjunto
            $table->string('file_1')->nullable();

            // Conformidad
            $table->string('confirmation_token')->nullable()->unique(); // Token para confirmar
            $table->timestamp('confirmed_at')->nullable();              // Cuándo se confirmó
            $table->timestamp('received_at')->nullable();               // Cuándo se recibió físicamente

            $table->timestamps();
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
