<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contador para generar tickets TK-n con bloqueo de fila (ver TicketNumberGenerator).
 * Se inicializa con el mayor TK-n existente para continuar la numeración actual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_sequences', function (Blueprint $table) {
            $table->string('name', 50)->primary();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        $max = DB::table('support_details')
            ->where('ticket', 'like', 'TK-%')
            ->pluck('ticket')
            ->filter(fn ($t) => preg_match('/^TK-\d+$/', (string) $t))
            ->map(fn ($t) => (int) substr($t, 3))
            ->max() ?? 0;

        DB::table('ticket_sequences')->insert([
            'name' => 'support_ticket',
            'last_number' => $max,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_sequences');
    }
};
