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
           // Si tu columna 'detail' existe, la nueva se ubicará después de ella.
            // Si no existe, puedes quitar ->after('detail')
            $table->text('detail_2')->nullable()->after('detail');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('motivos_cita', function (Blueprint $table) {
           $table->dropColumn('detail_2');
        });
    }
};
