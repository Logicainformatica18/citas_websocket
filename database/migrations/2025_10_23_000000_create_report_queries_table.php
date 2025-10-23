<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_queries', function (Blueprint $table) {
            $table->id();
            $table->string('category', 150)->index()
                ->comment('Bloque temático: Métricas, Empleabilidad, Obsolescencia, etc.');
            $table->text('question')
                ->comment('Pregunta natural del usuario (ej: ¿Qué tecnologías han aumentado más su demanda?)');
            $table->text('interpreter')
                ->comment('Comando o método que se ejecuta en el servicio o controlador');
            $table->string('component', 150)->nullable()
                ->comment('Componente React a renderizar (ej: TopTechnologiesChart)');
            $table->text('description')->nullable()
                ->comment('Descripción breve para GPT o documentación');
            $table->json('tags')->nullable()
                ->comment('Palabras clave relacionadas para búsquedas semánticas');
            $table->boolean('is_active')->default(true)
                ->comment('Permite activar/desactivar reportes sin borrarlos');
            
            // 👇 Nuevo campo para personalizar el prompt de explicación de resultados
            $table->text('explanation_prompt')->nullable()
                ->comment('Prompt personalizado para que GPT explique los resultados o tendencias');
$table->boolean('has_ai_response')
    ->default(true)
    ->comment('Indica si esta consulta requiere respuesta de IA');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_queries');
    }
};
