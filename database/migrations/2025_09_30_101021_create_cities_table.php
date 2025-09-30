<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id(); // este es el ID único del Excel
            $table->string('city');
            $table->string('city_ascii')->nullable();
            $table->decimal('lat', 10, 6);
            $table->decimal('lng', 10, 6);
            $table->string('country');
            $table->string('iso2', 2)->nullable();
            $table->string('iso3', 3)->nullable();
            $table->string('admin_name')->nullable();
            $table->string('capital')->nullable();
            $table->bigInteger('population')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
