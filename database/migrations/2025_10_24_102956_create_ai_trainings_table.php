<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aitrainings', function (Blueprint $table) {
            $table->id();
            $table->string('topic', 150)->comment('Bloque temático del entrenamiento IA');
            $table->text('prompt')->comment('Instrucción o pregunta usada para entrenar la IA');
            $table->text('interpreter')->comment('Controlador o método backend asociado');
            $table->string('component', 150)->nullable()->comment('Componente React a renderizar');
            $table->text('description')->nullable()->comment('Descripción breve o propósito del entrenamiento');
            $table->json('tags')->nullable()->comment('Palabras clave relacionadas');
            $table->boolean('is_active')->default(true)->comment('Activo o inactivo');
            $table->boolean('has_ai_response')->default(true)->comment('Requiere respuesta IA');
            $table->text('explanation_prompt')->nullable()->comment('Prompt explicativo opcional');
            $table->timestamps();

            $table->index('topic', 'ai_trainings_topic_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aitrainings');
    }
};
