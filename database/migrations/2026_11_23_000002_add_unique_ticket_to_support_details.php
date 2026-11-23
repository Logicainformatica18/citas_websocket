<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índice único de tickets.
 *
 * 'TK-' es el valor de "sin ticket" que usa el backend/frontend y se repite, por
 * eso el índice va sobre una columna generada que lo convierte en NULL:
 *     ticket_unique = NULLIF(NULLIF(ticket, 'TK-'), '')
 *
 * Si ya existen tickets repetidos (p. ej. solicitudes múltiples creadas antes,
 * cuando todos los detalles compartían el mismo TK), la migración se detiene SIN
 * crear nada y muestra cuántos hay. Queda pendiente hasta decidir cómo migrarlos.
 * Consulta para revisarlos:
 *
 *   SELECT ticket, COUNT(*) AS veces, COUNT(DISTINCT support_id) AS solicitudes
 *   FROM support_details WHERE ticket <> 'TK-' AND ticket <> ''
 *   GROUP BY ticket HAVING COUNT(*) > 1;
 */
return new class extends Migration
{
    public function up(): void
    {
        $dups = DB::table('support_details')
            ->select('ticket', DB::raw('COUNT(*) as veces'), DB::raw('COUNT(DISTINCT support_id) as solicitudes'))
            ->whereNotNull('ticket')->whereNotIn('ticket', ['TK-', ''])
            ->groupBy('ticket')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($dups->isNotEmpty()) {
            $mismaSolicitud = $dups->where('solicitudes', 1)->count();
            throw new RuntimeException(sprintf(
                'No se creó el índice único: hay %d tickets repetidos (%d filas afectadas; %d repetidos solo dentro '
                . 'de una misma solicitud múltiple y %d entre solicitudes distintas). Ejemplos: %s. '
                . 'Decide cómo migrarlos antes de volver a ejecutar esta migración.',
                $dups->count(),
                $dups->sum('veces'),
                $mismaSolicitud,
                $dups->count() - $mismaSolicitud,
                $dups->take(5)->pluck('ticket')->implode(', ')
            ));
        }

        Schema::table('support_details', function (Blueprint $table) {
            $table->string('ticket_unique')->nullable()
                ->virtualAs("NULLIF(NULLIF(ticket, 'TK-'), '')");
            $table->unique('ticket_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('support_details', 'ticket_unique')) {
            return;
        }
        Schema::table('support_details', function (Blueprint $table) {
            $table->dropUnique(['ticket_unique']);
            $table->dropColumn('ticket_unique');
        });
    }
};
