<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_histories', function (Blueprint $table) {
            $table->id();

            // 🔹 Identificador persistente de sesión (no depende del login)
            $table->uuid('session_id')->index()
                ->comment('Identificador único de la conversación, persistente por navegador');

            // 🔹 Usuario autenticado (cuando esté logueado, p. ej. vía SAML2)
            $table->foreignId('user_id')->nullable()
                ->constrained()
                ->onDelete('cascade')
                ->comment('Usuario asociado si existe sesión autenticada');

            // 🔹 Vinculación opcional a un reporte o pregunta del catálogo
            $table->foreignId('report_query_id')->nullable()
                ->comment('Pregunta o reporte ejecutado (relación con report_queries)');

            // 🔹 Conversación
            $table->text('user_message')->comment('Mensaje enviado por el usuario');
            $table->longText('ai_response')->nullable()->comment('Respuesta generada por la IA');

            // 🔹 Contexto adicional o variables de ejecución
            $table->json('context')->nullable()
                ->comment('Contexto adicional: métricas, filtros, etc.');

            // 🔹 Información adicional opcional
            $table->string('source')->default('dashboard')
                ->comment('Origen del chat: dashboard, VERA, API, etc.');
            $table->string('language', 10)->default('es')
                ->comment('Idioma del mensaje');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_histories');
    }
};
