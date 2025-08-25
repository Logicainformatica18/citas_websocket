<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // channel (canal actual)
        if (!Schema::hasColumn('payments', 'channel')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('channel', 60)->nullable()->after('mz_lote')->index();
            });
        }

        // === Identificadores de operación/transacción/venta ===
        if (!Schema::hasColumn('payments', 'operation_number')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('operation_number', 100)->nullable()->after('channel')->index();
            });
        }
        if (!Schema::hasColumn('payments', 'transaction_code')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('transaction_code', 100)->nullable()->after('operation_number');
            });
        }
        if (!Schema::hasColumn('payments', 'sale_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('sale_id', 100)->nullable()->after('transaction_code');
            });
        }

        // === Entidades comerciales (empresa/comercio) ===
        if (!Schema::hasColumn('payments', 'company_name')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('company_name', 150)->nullable()->after('sale_id');
            });
        }
        if (!Schema::hasColumn('payments', 'commerce_name')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('commerce_name', 150)->nullable()->after('company_name');
            });
        }

        // === Cuentas ===
        if (!Schema::hasColumn('payments', 'account_holder')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('account_holder', 150)->nullable()->after('commerce_name'); // R.S / titular
            });
        }
        if (!Schema::hasColumn('payments', 'account_number')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('account_number', 150)->nullable()->after('account_holder')->index();
            });
        }
        if (!Schema::hasColumn('payments', 'destination_account')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('destination_account', 150)->nullable()->after('account_number');
            });
        }
        if (!Schema::hasColumn('payments', 'salary_account')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('salary_account', 150)->nullable()->after('destination_account');
            });
        }
        if (!Schema::hasColumn('payments', 'account_last_digits')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('account_last_digits', 20)->nullable()->after('salary_account');
            });
        }

        // === Moneda ===
        if (!Schema::hasColumn('payments', 'currency')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('currency', 10)->nullable()->after('account_last_digits'); // "PEN","USD"
            });
        }

        // === Evidencia y Meta ===
        if (!Schema::hasColumn('payments', 'evidence')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->text('evidence')->nullable()->after('currency'); // ruta/observación/JSON corto
            });
        }
        if (!Schema::hasColumn('payments', 'meta')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->json('meta')->nullable()->after('evidence'); // campo flexible para casos raros
            });
        }

        // === (Opcional) Historial en JSON, sin otra tabla ===
        if (!Schema::hasColumn('payments', 'channel_log')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->json('channel_log')->nullable()->after('meta');
            });
        }

        // === Estado de verificación ===
        if (!Schema::hasColumn('payments', 'state')) {
            Schema::table('payments', function (Blueprint $table) {
                // registrado  -> apenas se crea el pago
                // validado    -> OCR y nro. operación (o transacción) hacen match
                // observado   -> se registró, pero el match falló
                $table->enum('state', ['registrado','validado','observado'])
                      ->default('registrado')
                      ->after('channel_log')
                      ->index();
            });
        }

        /**
         * NOTAS:
         * - Ya tienes `amount` y `date`, así que NO los duplico.
         * - Usa `date` como "fecha de pago". Si necesitas precisión hora/min, ponlo en meta o channel_log.
         */
    }

    public function down(): void
    {
        $cols = [
            'channel',
            'operation_number', 'transaction_code', 'sale_id',
            'company_name', 'commerce_name',
            'account_holder', 'account_number', 'destination_account', 'salary_account', 'account_last_digits',
            'currency',
            'evidence', 'meta',
            'channel_log',
            'state',
        ];

        foreach ($cols as $col) {
            if (Schema::hasColumn('payments', $col)) {
                Schema::table('payments', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
