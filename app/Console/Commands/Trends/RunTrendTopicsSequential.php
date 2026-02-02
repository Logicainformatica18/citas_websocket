<?php

namespace App\Console\Commands\Trends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\TrendTopic;
use App\Services\Trends\RunTrendTopicService;

class RunTrendTopicsSequential extends Command
{
    protected $signature = 'trends:run-one {--sleep=15}';

    protected $description = 'Ejecuta UN TrendTopic pendiente por corrida (secuencial, seguro para GPT-Search)';

    public function handle()
    {
        $sleep = (int) $this->option('sleep');

        // 🔍 1️⃣ Buscar siguiente topic pendiente
        $topic = TrendTopic::where('active', 1)
            ->where(function ($q) {
                $q->whereNull('last_run_status')
                  ->orWhereIn('last_run_status', ['success', 'failed']);
            })
            ->orderBy('last_success_at')
            ->first();

        if (!$topic) {
            $this->info('🟢 No hay TrendTopics pendientes');
            return Command::SUCCESS;
        }

        // 🔒 2️⃣ Marcar como running
        $topic->update([
            'last_run_status'  => 'running',
            'last_run_message' => 'Procesando (secuencial)',
        ]);

        $this->info("🔥 Ejecutando topic {$topic->id} ({$topic->intent})");

        try {
            app(RunTrendTopicService::class)->run($topic);

            $topic->update([
                'last_run_status'   => 'success',
                'last_run_message'  => 'Procesado correctamente',
                'success_count'     => $topic->success_count + 1,
                'last_success_at'   => now(),
            ]);

            $this->info('✅ Topic procesado');

        } catch (\Throwable $e) {

            Log::error('💥 Error en TrendTopic', [
                'topic_id' => $topic->id,
                'error'    => $e->getMessage(),
            ]);

            $topic->update([
                'last_run_status' => 'failed',
                'last_run_message'=> $e->getMessage(),
                'fail_count'      => $topic->fail_count + 1,
                'last_fail_at'    => now(),
            ]);

            $this->error('❌ Topic falló');
        }

        // 💤 3️⃣ Pausa consciente (opcional)
        if ($sleep > 0) {
            $this->info("⏳ Sleep {$sleep}s");
            sleep($sleep);
        }

        return Command::SUCCESS;
    }
}
