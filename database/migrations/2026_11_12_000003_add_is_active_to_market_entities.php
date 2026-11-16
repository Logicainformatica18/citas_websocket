<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de entidad activa en market_entities: única fuente de verdad para
 * rankings, evolución, export y scrapers. Reclasifica IDC y OECD como inactivas.
 *
 * Idempotente: en producción lo aplica el DBA con
 * docs/produccion/ranking_lenguajes/05_ranking_languages_activos.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_entities', function (Blueprint $table) {
            if (! Schema::hasColumn('market_entities', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (! Schema::hasColumn('market_entities', 'inactive_reason')) {
                $table->string('inactive_reason')->nullable();
            }
            if (! Schema::hasColumn('market_entities', 'inactive_at')) {
                $table->timestamp('inactive_at')->nullable();
            }
        });

        if (! Schema::hasIndex('market_entities', 'idx_market_entities_type_active')) {
            Schema::table('market_entities', function (Blueprint $table) {
                $table->index(['entity_type', 'is_active'], 'idx_market_entities_type_active');
            });
        }

        DB::table('market_entities')
            ->where('entity_type', 'language')
            ->whereIn('slug', ['idc', 'oecd'])
            ->where('is_active', 1)
            ->update([
                'is_active' => 0,
                'inactive_reason' => 'Organización/fuente de reportes, no es un lenguaje de programación',
                'inactive_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasIndex('market_entities', 'idx_market_entities_type_active')) {
            Schema::table('market_entities', fn (Blueprint $table) => $table->dropIndex('idx_market_entities_type_active'));
        }

        foreach (['inactive_at', 'inactive_reason', 'is_active'] as $column) {
            if (Schema::hasColumn('market_entities', $column)) {
                Schema::table('market_entities', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
