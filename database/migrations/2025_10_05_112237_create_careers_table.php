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
        Schema::create('careers', function (Blueprint $table) {
            $table->id();

            // 🔹 Basic info
            $table->string('name');
            $table->string('slug')->unique();

            // 🔹 Descriptive fields
            $table->text('description')->nullable();
            $table->longText('detail')->nullable();

            // 🔹 Academic info
            $table->string('faculty')->nullable();       // Example: "Engineering Faculty"
            $table->string('degree_title')->nullable();  // Example: "Bachelor of Software Engineering"
            $table->integer('duration_years')->nullable();

            // 🔹 Status and timestamps
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('careers');
    }
};
