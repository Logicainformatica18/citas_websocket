<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clasificación con IA (PLAN_RANKINGS.md, Bloque 10): prompts y versiones, decisión vigente por título,
 * historial de decisiones, lotes de la Batch API, corridas de los comandos, pruebas de versión y la fila
 * de configuración (interruptor de emergencia y filtro de tendencias en los rankings, apagado).
 * Siembra los prompts v2 (database/data/ai_prompts/*.txt) como versión 1 activa si no existen.
 * Idempotente.
 * php artisan migrate --path=database/migrations/2026_11_25_000006_create_ai_classification_tables.php --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_prompts')) {
            Schema::create('ai_prompts', function (Blueprint $t) {
                $t->id();
                $t->string('kind', 20)->unique();             // offers | trends
                $t->string('name', 120);
                $t->unsignedBigInteger('active_version_id')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('ai_prompt_versions')) {
            Schema::create('ai_prompt_versions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('prompt_id');
                $t->unsignedInteger('version');
                $t->mediumText('body');
                $t->string('model', 40);
                $t->string('note', 500)->nullable();
                $t->enum('status', ['borrador', 'activa', 'retirada'])->default('borrador');
                $t->string('created_by', 150)->nullable();
                $t->string('activated_by', 150)->nullable();
                $t->timestamp('activated_at')->nullable();
                $t->timestamps();
                $t->unique(['prompt_id', 'version']);
            });
        }

        // Decisión vigente por título (clave normalizada): la heredan las ofertas con ese título
        if (! Schema::hasTable('ai_title_classifications')) {
            Schema::create('ai_title_classifications', function (Blueprint $t) {
                $t->char('title_key', 32)->charset('ascii')->collation('ascii_bin')->primary();
                $t->string('title', 500);
                $t->enum('status', ['ti', 'no_ti', 'dudosa']);
                $t->boolean('depends_on_description')->default(false);
                $t->string('category', 60)->nullable();
                $t->string('reason', 300)->nullable();
                $t->unsignedTinyInteger('confidence')->nullable();
                $t->unsignedBigInteger('prompt_version_id')->nullable();
                $t->string('model', 40)->nullable();
                $t->enum('origin', ['ia', 'manual']);
                $t->unsignedBigInteger('result_id')->nullable();
                $t->timestamps();
                $t->index(['status', 'origin']);
                $t->index('prompt_version_id');
            });
        }

        // Historial: una fila por decisión (no se sobrescribe). kind: offer_title | offer | trend
        if (! Schema::hasTable('ai_classification_results')) {
            Schema::create('ai_classification_results', function (Blueprint $t) {
                $t->id();
                $t->string('kind', 12);
                $t->string('item_key', 64);
                $t->string('status', 12);
                $t->string('category', 60)->nullable();
                $t->string('reason', 300)->nullable();
                $t->unsignedTinyInteger('confidence')->nullable();
                $t->boolean('depends_on_description')->nullable();
                $t->unsignedBigInteger('prompt_version_id')->nullable();
                $t->string('model', 40)->nullable();
                $t->enum('origin', ['regla', 'ia', 'manual', 'keyword']);
                $t->string('pass', 12)->nullable();                 // title | offer | trend | rule | manual
                $t->unsignedBigInteger('batch_id')->nullable();
                $t->unsignedBigInteger('run_id')->nullable();
                $t->string('user_name', 150)->nullable();
                $t->unsignedInteger('items_affected')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->index(['kind', 'item_key']);
                $t->index(['prompt_version_id', 'kind']);
            });
        }

        if (! Schema::hasTable('ai_batches')) {
            Schema::create('ai_batches', function (Blueprint $t) {
                $t->id();
                $t->string('kind', 12);                             // offers | trends
                $t->string('pass', 12);                             // title | offer | trend
                $t->string('purpose', 20)->default('history');      // history | reclassify
                $t->string('status', 20)->default('preparando');    // preparando | enviado | completado | aplicado | fallido | expirado | cancelado
                $t->string('provider_status', 30)->nullable();
                $t->string('provider_batch_id', 80)->nullable();
                $t->string('input_file_id', 80)->nullable();
                $t->string('output_file_id', 80)->nullable();
                $t->string('error_file_id', 80)->nullable();
                $t->unsignedInteger('requests')->default(0);
                $t->unsignedInteger('items')->default(0);
                $t->unsignedBigInteger('prompt_version_id')->nullable();
                $t->string('model', 40)->nullable();
                $t->unsignedBigInteger('tokens_in')->default(0);
                $t->unsignedBigInteger('tokens_out')->default(0);
                $t->decimal('cost_usd', 10, 5)->default(0);
                $t->text('error')->nullable();
                $t->timestamp('submitted_at')->nullable();
                $t->timestamp('completed_at')->nullable();
                $t->timestamp('applied_at')->nullable();
                $t->timestamps();
                $t->index('status');
            });
        }

        // Qué elementos van en cada petición del lote (índice corto de la línea → clave del elemento)
        if (! Schema::hasTable('ai_batch_requests')) {
            Schema::create('ai_batch_requests', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('batch_id');
                $t->string('custom_id', 40);
                $t->json('items');
                $t->string('status', 12)->default('enviado');      // enviado | aplicado | fallido
                $t->unique(['batch_id', 'custom_id']);
            });
        }

        if (! Schema::hasTable('ai_runs')) {
            Schema::create('ai_runs', function (Blueprint $t) {
                $t->id();
                $t->string('command', 40);
                $t->string('kind', 12)->nullable();
                $t->json('options')->nullable();
                $t->boolean('dry_run')->default(false);
                $t->string('status', 12)->default('en_curso');     // en_curso | ok | error
                $t->unsignedInteger('processed')->default(0);
                $t->unsignedInteger('inherited')->default(0);
                $t->unsignedInteger('by_rule')->default(0);
                $t->unsignedInteger('calls')->default(0);
                $t->unsignedBigInteger('tokens_in')->default(0);
                $t->unsignedBigInteger('tokens_out')->default(0);
                $t->decimal('cost_usd', 10, 5)->default(0);
                $t->json('summary')->nullable();
                $t->text('errors')->nullable();
                $t->timestamp('started_at')->nullable();
                $t->timestamp('finished_at')->nullable();
                $t->index('started_at');
            });
        }

        if (! Schema::hasTable('ai_prompt_tests')) {
            Schema::create('ai_prompt_tests', function (Blueprint $t) {
                $t->id();
                $t->string('kind', 12);
                $t->unsignedBigInteger('draft_version_id');
                $t->unsignedBigInteger('base_version_id');
                $t->unsignedSmallInteger('sample_size');
                $t->string('status', 12)->default('pendiente');    // pendiente | en_curso | terminada | fallida
                $t->decimal('agreement', 5, 2)->nullable();
                $t->decimal('cost_usd', 10, 5)->default(0);
                $t->longText('result')->nullable();
                $t->text('error')->nullable();
                $t->string('created_by', 150)->nullable();
                $t->timestamp('ran_at')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('ai_classification_settings')) {
            Schema::create('ai_classification_settings', function (Blueprint $t) {
                $t->id();
                $t->boolean('enabled')->default(true);                 // interruptor de emergencia: sin llamadas a la IA
                $t->boolean('trends_filter_enabled')->default(false);  // rankings ignoran el ruido de tendencias
                // Corte histórico / nuevo: id <= corte lo procesa ai:classify-history (Batch API); id > corte, ai:classify
                $t->unsignedBigInteger('offers_history_max_id')->default(0);
                $t->unsignedBigInteger('trends_history_max_id')->default(0);
                $t->timestamp('history_submitted_at')->nullable();      // el operador ejecutó --submit
                $t->string('updated_by', 150)->nullable();
                $t->timestamps();
            });
        }
        if (! DB::table('ai_classification_settings')->exists()) {
            DB::table('ai_classification_settings')->insert(['id' => 1, 'enabled' => 1, 'trends_filter_enabled' => 0,
                'offers_history_max_id' => (int) DB::table('job_offers')->max('id'),
                'trends_history_max_id' => (int) DB::table('entity_trends')->max('id'),
                'created_at' => now(), 'updated_at' => now()]);
        }

        $this->seedPrompt('offers', 'Clasificación de ofertas (TI / no TI)', 'ofertas_v2.txt');
        $this->seedPrompt('trends', 'Clasificación de tendencias (relevante / ruido)', 'tendencias_v2.txt');
    }

    private function seedPrompt(string $kind, string $name, string $file): void
    {
        if (DB::table('ai_prompts')->where('kind', $kind)->exists()) {
            return;
        }
        $body = file_get_contents(database_path("data/ai_prompts/{$file}"));
        $now = now();
        DB::transaction(function () use ($kind, $name, $body, $now) {
            $pid = DB::table('ai_prompts')->insertGetId(['kind' => $kind, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
            $vid = DB::table('ai_prompt_versions')->insertGetId([
                'prompt_id' => $pid, 'version' => 1, 'body' => $body, 'model' => 'gpt-4.1-mini',
                'note' => 'Prompt v2 del análisis (piloto 2, docs/produccion/clasificacion_ia/)', 'status' => 'activa',
                'created_by' => 'migración', 'activated_by' => 'migración', 'activated_at' => $now,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('ai_prompts')->where('id', $pid)->update(['active_version_id' => $vid]);
        });
    }

    public function down(): void
    {
        foreach (['ai_classification_settings', 'ai_prompt_tests', 'ai_runs', 'ai_batch_requests', 'ai_batches',
            'ai_classification_results', 'ai_title_classifications', 'ai_prompt_versions', 'ai_prompts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
