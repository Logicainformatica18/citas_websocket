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
        Schema::create('career_course', function (Blueprint $table) {
            $table->id();

            // 🔹 Foreign keys
            $table->foreignId('career_id')
                ->constrained('careers')
                ->onDelete('cascade');

            $table->foreignId('course_id')
                ->constrained('courses')
                ->onDelete('cascade');

            // 🔹 Additional fields
            $table->string('semester')->nullable();      // Example: "1", "II", "2024-I"
            $table->boolean('is_mandatory')->default(true);

            $table->timestamps();

            // 🔹 Unique constraint (avoid duplicates)
            $table->unique(['career_id', 'course_id'], 'career_course_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('career_course');
    }
};
