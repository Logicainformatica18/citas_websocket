

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('support_details', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->unsignedBigInteger('support_id'); // Relación obligatoria
            $table->integer('project_id')->nullable();
            $table->integer('area_id')->nullable();
            $table->integer('id_motivos_cita')->nullable();
            $table->integer('id_tipo_cita')->nullable();
            $table->integer('id_dia_espera')->nullable();
            $table->unsignedBigInteger('internal_state_id')->nullable();
            $table->unsignedBigInteger('external_state_id')->nullable();
            $table->unsignedBigInteger('type_id')->nullable();

            // Campos generales
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('priority')->default('Normal');
            $table->string('type') ;
            $table->string('status') ;
            $table->string('attachment')->nullable();
            $table->datetime('reservation_time')->nullable();
            $table->datetime('attended_at')->nullable();
            $table->string('derived')->nullable();

            // Ubicación
            $table->string('Manzana')->nullable();
            $table->string('Lote')->nullable();

            $table->timestamps();

            // Llaves foráneas
            $table->foreign('support_id')->references('id')->on('supports')->onDelete('cascade');
            $table->foreign('project_id')->references('id_proyecto')->on('proyecto')->onDelete('set null');
            $table->foreign('area_id')->references('id_area')->on('areas')->onDelete('set null');
            $table->foreign('id_motivos_cita')->references('id_motivos_cita')->on('motivos_cita')->onDelete('set null');
            $table->foreign('id_tipo_cita')->references('id_tipo_cita')->on('tipos_cita')->onDelete('set null');
            $table->foreign('id_dia_espera')->references('id_dias_espera')->on('dias_espera')->onDelete('set null');
            $table->foreign('internal_state_id')->references('id')->on('internal_states')->onDelete('set null');
            $table->foreign('external_state_id')->references('id')->on('external_states')->onDelete('set null');
            $table->foreign('type_id')->references('id')->on('types')->onDelete('set null');
        });
    }

    public function down(): void {
        Schema::dropIfExists('support_details');
    }
};


















