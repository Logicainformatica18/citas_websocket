<?php

namespace App\Services\AiClassification;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenAI para la clasificación: llamada directa (chat completions, JSON) y Batch API (archivo JSONL → lote →
 * archivo de salida). Misma clave que el resto del proyecto (config('services.openai.key')).
 */
class OpenAiClient
{
    private const BASE = 'https://api.openai.com/v1';

    /** Cuerpo de una petición de clasificación (igual en directo y en la Batch API). */
    public static function body(string $model, string $system, string $user): array
    {
        return [
            'model' => $model,
            'temperature' => 0,
            'response_format' => ['type' => 'json_object'],
            'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
        ];
    }

    /**
     * @return array{rows: array<int, array>, in: int, cached: int, out: int}
     */
    public function chat(string $model, string $system, string $user): array
    {
        $resp = $this->http()->timeout((int) config('ai_classification.http_timeout', 90))
            ->retry(2, 2000, throw: false)
            ->post(self::BASE . '/chat/completions', self::body($model, $system, $user));
        if (! $resp->successful()) {
            throw new RuntimeException('Error de OpenAI (' . $resp->status() . '): ' . mb_substr($resp->body(), 0, 400));
        }

        return self::parseCompletion($resp->json());
    }

    /** Filas de la respuesta ({"r":[…]}) y tokens de un chat completion (directo o línea de la Batch API). */
    public static function parseCompletion(array $json): array
    {
        $content = (string) data_get($json, 'choices.0.message.content', '');
        $data = json_decode($content, true);
        if (! is_array($data) && preg_match('/\{.*\}/s', $content, $m)) {
            $data = json_decode($m[0], true);
        }

        return [
            'rows' => is_array($data['r'] ?? null) ? $data['r'] : [],
            'in' => (int) data_get($json, 'usage.prompt_tokens', 0),
            'cached' => (int) data_get($json, 'usage.prompt_tokens_details.cached_tokens', 0),
            'out' => (int) data_get($json, 'usage.completion_tokens', 0),
        ];
    }

    public function uploadBatchFile(string $path): string
    {
        $resp = $this->http()->timeout(300)
            ->attach('file', fopen($path, 'r'), basename($path))
            ->post(self::BASE . '/files', ['purpose' => 'batch']);
        if (! $resp->successful()) {
            throw new RuntimeException('Error al subir el archivo del lote: ' . mb_substr($resp->body(), 0, 400));
        }

        return (string) $resp->json('id');
    }

    public function createBatch(string $fileId, array $metadata = []): array
    {
        $resp = $this->http()->timeout(60)->post(self::BASE . '/batches', [
            'input_file_id' => $fileId,
            'endpoint' => '/v1/chat/completions',
            'completion_window' => '24h',
            'metadata' => (object) $metadata,
        ]);
        if (! $resp->successful()) {
            throw new RuntimeException('Error al crear el lote: ' . mb_substr($resp->body(), 0, 400));
        }

        return $resp->json();
    }

    public function batch(string $id): array
    {
        $resp = $this->http()->timeout(60)->get(self::BASE . '/batches/' . $id);
        if (! $resp->successful()) {
            throw new RuntimeException('Error al consultar el lote: ' . mb_substr($resp->body(), 0, 400));
        }

        return $resp->json();
    }

    /** Descarga un archivo de resultados a disco (sin cargarlo en memoria). */
    public function download(string $fileId, string $toPath): void
    {
        $resp = $this->http()->timeout(600)->withOptions(['sink' => $toPath])->get(self::BASE . "/files/{$fileId}/content");
        if (! $resp->successful()) {
            throw new RuntimeException("Error al descargar {$fileId}: HTTP " . $resp->status());
        }
    }

    private function http()
    {
        $key = config('services.openai.key');
        if (! $key) {
            throw new RuntimeException('OPENAI_API_KEY no configurada');
        }

        return Http::withToken($key);
    }
}
