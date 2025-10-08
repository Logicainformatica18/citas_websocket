<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('worldbank_indicators', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 5);
            $table->string('country_name');
            $table->string('indicator_code');
            $table->string('indicator_name');
            $table->year('year')->nullable();
            $table->float('value')->nullable();
            $table->string('source')->default('World Bank API');
            $table->timestamps();

            // Índices útiles para consultas rápidas
            $table->index(['indicator_code', 'country_code', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worldbank_indicators');
    }
};
