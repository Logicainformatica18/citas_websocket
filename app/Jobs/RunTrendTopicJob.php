<?php

namespace App\Jobs;

use App\Models\TrendTopic;
use App\Services\Trends\RunTrendTopicService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunTrendTopicJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutos
    public int $tries = 2;

    protected int $topicId;

    public function __construct(int $topicId)
    {
        $this->topicId = $topicId;
    }

 public function handle()
{
    $topic = TrendTopic::findOrFail($this->topicId);

    // 🔥 ESTE BLOQUE NO EXISTÍA (CRÍTICO)
    $topic->update([
        'last_run_status'  => 'running',
        'last_run_message' => 'Procesando…',
    ]);

    try {

        Log::info("🔥 Job iniciado", [
            'topic_id' => $topic->id,
            'intent'   => $topic->intent
        ]);

       $service = app(\App\Services\Trends\RunTrendTopicService::class);
$service->run($topic);


        // ✅ CIERRE GLOBAL (NO dentro del switch)
        $topic->update([
            'last_run_status' => 'success',
            'last_run_message' => 'Procesado correctamente',
            'success_count' => $topic->success_count + 1,
            'last_success_at' => now(),
        ]);

    } catch (\Throwable $e) {

        Log::error("💥 Job falló", [
            'topic_id' => $topic->id,
            'intent' => $topic->intent,
            'error' => $e->getMessage(),
        ]);

        $topic->update([
            'last_run_status' => 'failed',
            'last_run_message' => $e->getMessage(),
            'fail_count' => $topic->fail_count + 1,
            'last_fail_at' => now(),
        ]);

        throw $e;
    }
}


    
}
