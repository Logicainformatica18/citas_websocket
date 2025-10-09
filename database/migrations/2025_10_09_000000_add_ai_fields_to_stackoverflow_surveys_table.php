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
        Schema::table('stackoverflow_surveys', function (Blueprint $table) {
            // 🧠 Satisfacción laboral
            if (!Schema::hasColumn('stackoverflow_surveys', 'job_satisfaction')) {
                $table->string('job_satisfaction')->nullable()->after('years_code_pro');
            }

            // 🤖 Campos relacionados con IA y percepción (TEXT para evitar overflow)
            if (!Schema::hasColumn('stackoverflow_surveys', 'ai_select')) {
                $table->text('ai_select')->nullable()->after('job_satisfaction'); // Usa IA o no
            }
            if (!Schema::hasColumn('stackoverflow_surveys', 'ai_sentiment')) {
                $table->text('ai_sentiment')->nullable()->after('ai_select'); // Sentimiento hacia IA
            }
            if (!Schema::hasColumn('stackoverflow_surveys', 'ai_acceptance')) {
                $table->text('ai_acceptance')->nullable()->after('ai_sentiment'); // Nivel de aceptación
            }
            if (!Schema::hasColumn('stackoverflow_surveys', 'ai_complexity')) {
                $table->text('ai_complexity')->nullable()->after('ai_acceptance'); // Complejidad percibida
            }
            if (!Schema::hasColumn('stackoverflow_surveys', 'ai_frustration')) {
                $table->text('ai_frustration')->nullable()->after('ai_complexity'); // Frustración con IA
            }
            if (!Schema::hasColumn('stackoverflow_surveys', 'ai_explain')) {
                $table->text('ai_explain')->nullable()->after('ai_frustration'); // Confianza en explicaciones
            }
            if (!Schema::hasColumn('stackoverflow_surveys', 'ai_agents')) {
                $table->text('ai_agents')->nullable()->after('ai_explain'); // Usa agentes IA
            }
            if (!Schema::hasColumn('stackoverflow_surveys', 'ai_agent_impact')) {
                $table->text('ai_agent_impact')->nullable()->after('ai_agents'); // Impacto percibido
            }
            if (!Schema::hasColumn('stackoverflow_surveys', 'ai_agent_challenges')) {
                $table->text('ai_agent_challenges')->nullable()->after('ai_agent_impact'); // Desafíos con agentes IA
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stackoverflow_surveys', function (Blueprint $table) {
            $columns = [
                'job_satisfaction',
                'ai_select',
                'ai_sentiment',
                'ai_acceptance',
                'ai_complexity',
                'ai_frustration',
                'ai_explain',
                'ai_agents',
                'ai_agent_impact',
                'ai_agent_challenges',
            ];

            foreach ($columns as $col) {
                if (Schema::hasColumn('stackoverflow_surveys', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
