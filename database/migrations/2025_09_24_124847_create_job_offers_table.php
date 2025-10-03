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
        Schema::create('job_offers', function (Blueprint $table) {
            $table->id();

            // 📌 Datos básicos del empleo
            $table->string('title');                   // Título del empleo
            $table->string('company')->nullable();     // Empresa
            $table->string('country')->nullable();     // País detectado/normalizado
            $table->string('state_code', 10)->nullable(); // Código de estado/provincia (ej: NJ, TX, ON)
            $table->string('city')->nullable();        // Ciudad original (texto crudo)
            $table->string('city_ascii')->nullable();  // Ciudad normalizada (para joins)
            $table->string('location')->nullable();    // Cadena original de ubicación (legacy)
            $table->string('zip_code', 20)->nullable(); // Código postal (cuando esté disponible)

            // 📌 Modalidad y tipo de empleo
            $table->string('modality')->nullable();    // remoto, híbrido, presencial
            $table->string('workload')->nullable();    // full-time, part-time, freelance
            $table->string('experience_level')->nullable(); // junior, mid, senior, etc.

            // 📌 Coordenadas
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();

            // 📌 Salario
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('compensation_type')->nullable(); // hourly, yearly, etc.

            // 📌 Fuente y referencias
            $table->string('source');                  // LinkedIn, GetOnBoard, Adzuna, ISIL...
            $table->string('external_id')->nullable(); // ID en la fuente
            $table->longText('url')->nullable();         // Link público a la oferta
            $table->string('application_url')->nullable(); // Link para postular
            $table->string('application_type')->nullable(); // método de postulación

            // 📌 Fechas
            $table->timestamp('published_at')->nullable();  // Fecha de publicación
            $table->timestamp('expiry')->nullable();        // Fecha de expiración si aplica
            $table->timestamps(); // created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_offers');
    }
};
