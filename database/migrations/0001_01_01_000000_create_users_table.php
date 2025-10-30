<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
 * ⚙️ NOTA DE COMPATIBILIDAD (MySQL ↔ MariaDB)
 * ---------------------------------------------------------------
 * Evitamos usar la collation `utf8mb4_uca1400_ai_ci` porque solo
 * existe en MySQL 8.0.30+ y NO está soportada en MariaDB 10.x–12.x.
 *
 * En su lugar, usamos `utf8mb4_unicode_ci`, que mantiene
 * compatibilidad total entre ambos motores sin alterar
 * comparaciones ni ordenamiento de texto en español.
 *
 * Si exportas estructuras desde MySQL 8, recuerda reemplazar:
 *     utf8mb4_uca1400_ai_ci → utf8mb4_unicode_ci
 * antes de importar en entornos con MariaDB.
 */

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->string("dni",100)->nullable();
            $table->string("firstname");
            $table->string("lastname");
            $table->string("names");
            $table->string("password");
            $table->date("datebirth")->nullable();
            $table->string("cellphone",20)->nullable();
            $table->longText("photo")->nullable();
            $table->string("sex",1)->nullable();
            $table->string('email',100)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
