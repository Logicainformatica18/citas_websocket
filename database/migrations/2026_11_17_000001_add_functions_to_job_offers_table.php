<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Funciones del puesto (Get on Board: data.attributes.functions y functions_headline).
 * functions se guarda como texto plano (App\Support\HtmlText::toPlain).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('job_offers', 'functions')) {
            Schema::table('job_offers', function (Blueprint $table) {
                $table->text('functions')->nullable()->after('benefits');
            });
        }

        if (! Schema::hasColumn('job_offers', 'functions_headline')) {
            Schema::table('job_offers', function (Blueprint $table) {
                $table->string('functions_headline', 255)->nullable()->after('functions');
            });
        }
    }

    public function down(): void
    {
        foreach (['functions_headline', 'functions'] as $column) {
            if (Schema::hasColumn('job_offers', $column)) {
                Schema::table('job_offers', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
