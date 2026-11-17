<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Origen de job_offers.functions:
 *   'field'     → la fuente trae las funciones en un campo propio (Get on Board, Wantedly, USAJOBS)
 *   'heuristic' → se extrajeron de la descripción (App\Support\FunctionsSection)
 *
 * Requiere 2026_11_17_000001_add_functions_to_job_offers_table.
 * Es idempotente: se puede ejecutar más de una vez.
 */
return new class extends Migration
{
    /** Filas por UPDATE en el relleno (rangos de id, para no bloquear la tabla). */
    private const BATCH = 5000;

    public function up(): void
    {
        if (! Schema::hasColumn('job_offers', 'functions_origin')) {
            Schema::table('job_offers', function (Blueprint $table) {
                $table->string('functions_origin', 20)->nullable()->after('functions_headline');
            });
        }

        $this->backfillGetOnBoard();
    }

    public function down(): void
    {
        if (Schema::hasColumn('job_offers', 'functions_origin')) {
            Schema::table('job_offers', function (Blueprint $table) {
                $table->dropColumn('functions_origin');
            });
        }
    }

    /** Las ofertas de Get on Board que ya tienen functions las obtuvieron de un campo propio. */
    private function backfillGetOnBoard(): void
    {
        $pending = fn () => DB::table('job_offers')
            ->where('source', 'GetOnBoard')
            ->whereNotNull('functions')
            ->whereNull('functions_origin');

        $min = $pending()->min('id');
        $max = $pending()->max('id');

        if ($min === null) {
            return;
        }

        $updated = 0;
        for ($from = (int) $min; $from <= (int) $max; $from += self::BATCH) {
            // DB::table (no Eloquent): no toca updated_at.
            $updated += $pending()
                ->whereBetween('id', [$from, $from + self::BATCH - 1])
                ->update(['functions_origin' => 'field']);
        }

        echo "  functions_origin = 'field' en {$updated} ofertas de GetOnBoard.\n";
    }
};
