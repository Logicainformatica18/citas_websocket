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
        Schema::create('import_mappings', function (Blueprint $table) {
            $table->id();

            // 🔗 Relación con el archivo cargado (import_jobs)
            $table->foreignId('import_job_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // 🧩 JSON con el mapeo de columnas
            // Ejemplo:
            // {
            //   "title": "puesto",
            //   "company": "empresa",
            //   "city": "ubicacion",
            //   "country": "pais",
            //   "modality": "tipo_trabajo",
            //   "date": "publicado",
            //   "url": "url"
            // }
            $table->json('mapping');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_mappings');
    }
};
