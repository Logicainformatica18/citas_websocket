<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('survey_details', 'role')) {
            Schema::table('survey_details', function (Blueprint $table) {
                $table->enum('role', ['segmento', 'medicion'])->default('medicion')->after('type');
            });
        }

        DB::table('survey_details')
            ->whereNull('role')
            ->update(['role' => 'medicion']);

        if (! Schema::hasColumn('survey_clients', 'option_key')) {
            Schema::table('survey_clients', function (Blueprint $table) {
                $table->string('option_key', 255)
                    ->nullable()
                    ->storedAs("JSON_UNQUOTE(COALESCE(JSON_EXTRACT(`option`, '$[0]'), JSON_EXTRACT(`option`, '$'))) ")
                    ->after('option');
            });
        }

        $indexes = DB::select('SHOW INDEX FROM survey_clients');
        $hasIndex = collect($indexes)->contains(fn ($index) => $index->Key_name === 'survey_clients_survey_detail_id_option_index');

        if (! $hasIndex) {
            Schema::table('survey_clients', function (Blueprint $table) {
                $table->index(['survey_detail_id', 'option_key'], 'survey_clients_survey_detail_id_option_index');
            });
        }
    }

    public function down(): void
    {
        $indexes = DB::select('SHOW INDEX FROM survey_clients');
        $hasIndex = collect($indexes)->contains(fn ($index) => $index->Key_name === 'survey_clients_survey_detail_id_option_index');

        if ($hasIndex) {
            Schema::table('survey_clients', function (Blueprint $table) {
                $table->dropIndex('survey_clients_survey_detail_id_option_index');
            });
        }

        if (Schema::hasColumn('survey_clients', 'option_key')) {
            Schema::table('survey_clients', function (Blueprint $table) {
                $table->dropColumn('option_key');
            });
        }

        if (Schema::hasColumn('survey_details', 'role')) {
            Schema::table('survey_details', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }
};
