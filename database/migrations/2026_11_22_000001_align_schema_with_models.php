<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes para que el esquema coincida con lo que usa el backend/frontend.
 * Idempotente: cada cambio se protege con hasColumn()/hasIndex().
 */
return new class extends Migration
{
    public function up(): void
    {
        // users: columnas en User::$fillable que no existían; lastname es
        // nullable en UserController@store.
        Schema::table('users', function (Blueprint $table) {
            foreach (['google_id', 'fb', 'github', 'portfolio', 'youtube', 'instagram'] as $col) {
                if (! Schema::hasColumn('users', $col)) {
                    $table->string($col)->nullable();
                }
            }
            $table->string('lastname')->nullable()->change();
        });

        // supports.status_global era enum('Simple','Múltiple'), pero el
        // frontend envía 'Sí'/'No' y el controlador usa 'Incompleto' por
        // defecto → con strict mode MySQL rechazaba los INSERT.
        Schema::table('supports', function (Blueprint $table) {
            $table->string('status_global', 20)->default('No')->change();
            // SupportController@update asigna $support->attachment al subir archivo.
            if (! Schema::hasColumn('supports', 'attachment')) {
                $table->string('attachment')->nullable()->after('state');
            }
            if (! Schema::hasIndex('supports', ['created_at'])) {
                $table->index('created_at');
            }
        });

        // support_details: el backend usa prioridades Baja/Media/Alta (default
        // 'Baja'); la migración tenía default 'Normal'. Índices para filtros.
        Schema::table('support_details', function (Blueprint $table) {
            $table->string('priority')->default('Baja')->change();
            foreach (['status', 'priority', 'ticket', 'channel', 'created_at'] as $col) {
                if (! Schema::hasIndex('support_details', [$col])) {
                    $table->index($col);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_details', function (Blueprint $table) {
            foreach (['status', 'priority', 'ticket', 'channel', 'created_at'] as $col) {
                $table->dropIndex([$col]);
            }
            $table->string('priority')->default('Normal')->change();
        });

        Schema::table('supports', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropColumn('attachment');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'fb', 'github', 'portfolio', 'youtube', 'instagram']);
        });
    }
};
