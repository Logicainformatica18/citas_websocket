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
        Schema::table('motivos_cita', function (Blueprint $table) {
            //
             // Si prefieres limitar a 255 caracteres usa ->string('detail', 255)
            $table->text('detail')->nullable()->after('nombre_motivo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('motivos_cita', function (Blueprint $table) {
             $table->dropColumn('detail');
        });
    }
};
