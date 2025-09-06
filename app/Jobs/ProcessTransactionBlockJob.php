<?php

namespace App\Jobs;

use App\Models\TransactionBlock;
use App\Models\TransactionLine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use OpenAI; // 👈 Cliente oficial

class ProcessTransactionBlockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $block;

    public function __construct(TransactionBlock $block)
    {
        $this->block = $block;
    }

    public function handle(): void
    {
        \Log::info("📂 Procesando bloque ID={$this->block->id}, file={$this->block->file_path}");

        $imagePath = storage_path("app/public/{$this->block->file_path}");

        if (!file_exists($imagePath)) {
            \Log::error("❌ Imagen no encontrada: {$imagePath}");
            return;
        }

        $base64 = base64_encode(file_get_contents($imagePath));

        \Log::info("📤 Enviando bloque {$this->block->id} a OpenAI...");

        try {
            $client = OpenAI::client(env('OPENAI_API_KEY'));

            $response = $client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Devuelve las transacciones en formato JSON con campos: process_date, value_date, description, location, branch_code, operation_number, time, origin, transaction_type, debit, credit, balance.'
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => 'Analiza este bloque de estado de cuenta y extrae las transacciones.'],
                            ['type' => 'image_url', 'image_url' => ['url' => "data:image/jpeg;base64,{$base64}"]],
                        ],
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error("❌ OpenAI exception: " . $e->getMessage());
            return;
        }

        if (empty($response->choices[0]->message->content)) {
            \Log::warning("⚠️ OpenAI no devolvió contenido usable para el bloque {$this->block->id}");
            return;
        }

        $content = $response->choices[0]->message->content;
        \Log::info("✅ Respuesta OpenAI recibida: " . $content);

        $parsed = json_decode($content, true);

        if (!is_array($parsed)) {
            \Log::warning("⚠️ OpenAI devolvió algo no parseable: " . $content);
            return;
        }

        foreach ($parsed as $line) {
            $line = TransactionLine::create([
                'transaction_id'   => $this->block->transaction_id,
                'block_id'         => $this->block->id,
                'process_date'     => $line['process_date'] ?? null,
                'value_date'       => $line['value_date'] ?? null,
                'description'      => $line['description'] ?? null,
                'location'         => $line['location'] ?? null,
                'branch_code'      => $line['branch_code'] ?? null,
                'operation_number' => $line['operation_number'] ?? null,
                'time'             => $line['time'] ?? null,
                'origin'           => $line['origin'] ?? null,
                'transaction_type' => $line['transaction_type'] ?? null,
                'debit'            => $line['debit'] ?? null,
                'credit'           => $line['credit'] ?? null,
                'balance'          => $line['balance'] ?? null,
            ]);

            \Log::info("💾 Línea guardada: ", $line->toArray());
        }
    }
}
