<?php

namespace App\Jobs;

use App\Models\TransactionBlock;
use App\Models\TransactionLine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use OpenAI\Factory; // ✅ Cliente puro

class ProcessTransactionBlockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $block;

    public function __construct(TransactionBlock $block)
    {
        $this->block = $block;
    }

    /**
     * Limpia comas de separadores de miles y convierte a float
     */
    private function toNumber($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float) str_replace(',', '', $value);
    }

    public function handle(): void
    {
        Log::info("📂 Procesando bloque ID={$this->block->id}, file={$this->block->file_path}");

        $imagePath = storage_path("app/public/{$this->block->file_path}");
        if (!file_exists($imagePath)) {
            Log::error("❌ Imagen no encontrada: {$imagePath}");
            return;
        }

        $base64 = base64_encode(file_get_contents($imagePath));
        Log::info("📤 Enviando bloque {$this->block->id} a OpenAI...");

        try {
            $apiKey = env('OPENAI_API_KEY');
            $client = (new Factory())
                ->withApiKey($apiKey)
                ->make();

            $response = $client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Eres un extractor de datos bancarios que siempre responde en JSON válido.'
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => "Convierte este estado de cuenta bancario a JSON estructurado.
Debes devolver absolutamente todas las filas de la tabla, sin cortar ni resumir.
Si la tabla es muy larga, continúa hasta el final en un solo JSON.
No inventes ni omitas filas.
Cada objeto debe contener:
- fecha
- fecha_valor
- descripcion
- lugar
- suc_age
- num_op
- hora
- origen
- tipo
- cargo
- abono
- saldo

Responde únicamente con JSON válido, sin comentarios ni texto adicional."
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => ['url' => "data:image/jpeg;base64,{$base64}"]
                            ],
                        ],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error("❌ OpenAI exception en bloque {$this->block->id}: " . $e->getMessage());
            return;
        }

        if (empty($response->choices[0]->message->content)) {
            Log::warning("⚠️ OpenAI no devolvió contenido usable para el bloque {$this->block->id}");
            return;
        }

        $content = $response->choices[0]->message->content;
        Log::info("✅ Respuesta OpenAI recibida: " . $content);

        // Intentar limpiar para quedarnos solo con JSON puro
        if (preg_match('/```json(.*?)```/s', $content, $matches)) {
            $json = $matches[1];
        } elseif (preg_match('/\[(.*)\]/s', $content, $matches)) {
            $json = '[' . $matches[1] . ']';
        } else {
            $json = $content;
        }

        $parsed = json_decode($json, true);
        if (!is_array($parsed)) {
            Log::warning("⚠️ OpenAI devolvió algo no parseable en bloque {$this->block->id}: " . $content);
            return;
        }

        foreach ($parsed as $line) {
            $lineModel = TransactionLine::create([
                'transaction_id'   => $this->block->transaction_id,
                'block_id'         => $this->block->id,
                // Híbrido: español primero, inglés como fallback
                'process_date'     => $line['fecha'] ?? $line['process_date'] ?? null,
                'value_date'       => $line['fecha_valor'] ?? $line['value_date'] ?? null,
                'description'      => $line['descripcion'] ?? $line['description'] ?? null,
                'branch_code'      => $line['suc_age'] ?? $line['branch_code'] ?? null,
                'operation_number' => $line['num_op'] ?? $line['operation_number'] ?? null,
                'time'             => $line['hora'] ?? $line['time'] ?? null,
                'origin'           => $line['origen'] ?? $line['origin'] ?? null,
                'transaction_type' => $line['tipo'] ?? $line['transaction_type'] ?? null,
                'debit'            => $this->toNumber($line['cargo'] ?? $line['debit'] ?? null),
                'credit'           => $this->toNumber($line['abono'] ?? $line['credit'] ?? null),
                'balance'          => $this->toNumber($line['saldo'] ?? $line['balance'] ?? null),
            ]);

            Log::info("💾 Línea guardada (bloque {$this->block->id}): ", $lineModel->toArray());
        }
    }
}
