<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla heredada `clientes` (modelo Client). Columnas según $fillable y las
 * reglas de ClientController. Debe existir antes de supports y sales.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clientes')) {
            return;
        }

        Schema::create('clientes', function (Blueprint $table) {
            $table->integer('id_cliente', true);
            $table->string('id_slin', 50)->nullable();
            $table->string('Codigo', 50)->nullable();
            $table->string('Razon_Social', 255);
            $table->string('DNI', 20)->nullable()->index();
            $table->string('NIT', 20)->nullable();
            $table->string('Direccion', 255)->nullable();
            $table->string('Ubigeo', 10)->nullable();
            $table->string('Departamento', 100)->nullable();
            $table->string('Provincia', 100)->nullable();
            $table->string('Distrito', 100)->nullable();
            $table->string('Telefono', 20)->nullable();
            $table->string('Email', 150)->nullable();
            $table->string('clave', 255)->nullable();
            $table->boolean('c_clave')->default(false);
            $table->string('ref_telefono1', 20)->nullable();
            $table->string('ref_telefono2', 20)->nullable();
            $table->text('comentario')->nullable();
            $table->string('canal', 50)->nullable();
            $table->boolean('habilitado')->default(true);
            $table->integer('id_rol')->default(2);

            $table->index('Razon_Social');
        });

        // La columna heredada "T.Doc." contiene puntos: el Schema Builder la
        // interpretaría como tabla.columna, así que se crea con SQL crudo.
        $quoted = DB::getDriverName() === 'mysql' ? '`T.Doc.`' : '"T.Doc."';
        DB::statement("ALTER TABLE clientes ADD COLUMN {$quoted} VARCHAR(20) NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
