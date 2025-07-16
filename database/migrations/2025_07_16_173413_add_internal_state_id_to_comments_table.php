<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->unsignedBigInteger('internal_state_id')->nullable()->after('comment');

            $table->foreign('internal_state_id')
                  ->references('id')->on('internal_states')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['internal_state_id']);
            $table->dropColumn('internal_state_id');
        });
    }
};
