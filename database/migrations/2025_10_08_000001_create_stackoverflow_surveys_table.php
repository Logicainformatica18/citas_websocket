<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stackoverflow_surveys', function (Blueprint $table) {
            $table->id();

            // 🧍 Identidad y demografía
            $table->string('main_branch')->nullable();              // Ej: "I am a developer by profession"
            $table->string('age')->nullable();                      // Ej: "25-34 years old"
            $table->string('country')->nullable();                  // Ej: "Peru"
            $table->string('iso2', 2)->nullable();                  // PE, MX, US (opcional)
            $table->string('employment')->nullable();               // Ej: "Employed, full-time"
            $table->string('remote_work')->nullable();              // Ej: "Remote", "Hybrid"

            // 🎓 Formación y experiencia
            $table->string('ed_level')->nullable();                 // Ej: "Bachelor’s degree"
            $table->string('learn_code')->nullable();               // Ej: "Books / Online Courses"
            $table->string('years_code')->nullable();               // Ej: "5-9 years"
            $table->string('years_code_pro')->nullable();           // Ej: "3-5 years"
            $table->string('dev_type')->nullable();                 // Ej: "Backend developer"

            // 💰 Compensación
            $table->string('currency')->nullable();                 // Ej: "USD"
            $table->decimal('comp_total', 15, 2)->nullable();       // Valor bruto (ConvertedCompYearly)

            // 💻 Lenguajes
            $table->text('language_have_worked_with')->nullable();  // Lista separada por ";"
            $table->text('language_want_work_with')->nullable();
            $table->text('language_admired')->nullable();

            // 🧠 Base de datos, frameworks, plataformas
            $table->text('database_have_worked_with')->nullable();
            $table->text('webframe_have_worked_with')->nullable();
            $table->text('platform_have_worked_with')->nullable();

            // 💬 Opiniones sobre IA o productividad
            $table->string('ai_select')->nullable();                // Ej: "Yes", "No"
            $table->string('ai_sentiment')->nullable();             // Ej: "Very favorable"
            $table->string('ai_benefit')->nullable();               // Ej: "Increase productivity"

            // 🏢 Organización y ambiente laboral
            $table->string('org_size')->nullable();                 // Ej: "20-99 employees"
            $table->string('industry')->nullable();                 // Ej: "IT Services"
            $table->string('job_satisfaction')->nullable();         // Ej: "Satisfied", "Neutral", etc.

            // 🕓 Año de encuesta
            $table->integer('year')->nullable();

            $table->timestamps();

            // Índices clave para filtros y gráficos
            $table->index(['year']);
            $table->index(['country']);
            $table->index(['remote_work']);
            $table->index(['employment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stackoverflow_surveys');
    }
};
