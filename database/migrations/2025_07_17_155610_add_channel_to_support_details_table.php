<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('support_details', function (Blueprint $table) {
            $table->string('channel')->nullable()->after('ticket_end'); // o cámbialo por 'canal' si prefieres en español
        });
    }

    public function down(): void
    {
        Schema::table('support_details', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }
};
