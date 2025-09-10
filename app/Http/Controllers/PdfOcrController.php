<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\InputConfig;
use Google\Cloud\Vision\V1\OutputConfig;
use Google\Cloud\Vision\V1\GcsSource;
use Google\Cloud\Vision\V1\GcsDestination;
use Google\Cloud\Vision\V1\AsyncAnnotateFileRequest;

use OpenAI; // requiere: composer require openai-php/laravel

class PdfOcrController extends Controller
{
    private string $projectId;
    private string $bucket;
    private string $outputBase; // ej: 'vision/output'

    public function __construct()
    {
        $this->projectId = (string) env('GCP_PROJECT_ID', '');
        $this->bucket = (string) env('GCS_BUCKET', '');
        $this->outputBase = rtrim((string) env('GCS_OUTPUT_PREFIX', 'vision/output'), '/');
    }

    /**
     * Sube un PDF y devuelve OCR estructurado.
     */
    public function uploadAndOcr(Request $request)
    {
        set_time_limit(300);

        $data = $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:51200'],
        ]);

        $localPath = $data['pdf']->getRealPath();
        $destPath = 'uploads/pdfs/' . time() . '_' . $data['pdf']->getClientOriginalName();
        $gcsPdfUri = $this->uploadToGcs($localPath, $destPath);

        [$outPrefix, $text, $pages, $objects] = $this->runOcrAndCollect($gcsPdfUri);

        $structured = $this->postProcessWithOpenAI($text);

        return response()->json([
            'ok' => true,
            'gcs_pdf_uri' => $gcsPdfUri,
            'out_prefix' => $outPrefix,
            'pages' => $pages,
            'text' => $text,
            'structured' => $structured,
            'result_objects' => $objects,
        ]);
    }

    /**
     * Procesa un PDF ya existente en GCS.
     */
    public function ocrExisting(Request $request)
    {
        set_time_limit(300);

        $data = $request->validate([
            'object' => ['required', 'string'],
        ]);

        $gcsPdfUri = $this->toGcsUri($data['object']);
        [$outPrefix, $text, $pages, $objects] = $this->runOcrAndCollect($gcsPdfUri);

        $structured = $this->postProcessWithOpenAI($text);

        return response()->json([
            'ok' => true,
            'gcs_pdf_uri' => $gcsPdfUri,
            'out_prefix' => $outPrefix,
            'pages' => $pages,
            'text' => $text,
            'structured' => $structured,
            'result_objects' => $objects,
        ]);
    }

    /* ===================== Helpers ===================== */

    private function uploadToGcs(string $localPath, string $destPath): string
    {
        set_time_limit(300);

        $storage = new StorageClient([
            'projectId' => $this->projectId,
            'keyFilePath' => storage_path('app/eco-splicer-468114-t0-54c2adb26581.json'),
        ]);

        $bucket = $storage->bucket($this->bucket);
        $bucket->upload(fopen($localPath, 'r'), ['name' => $destPath]);

        return $this->toGcsUri($destPath);
    }

    private function toGcsUri(string $objectPath): string
    {
        $objectPath = ltrim($objectPath, '/');
        return sprintf('gs://%s/%s', $this->bucket, $objectPath);
    }

    private function runOcrAndCollect(string $gcsPdfUri): array
    {
        set_time_limit(300);

        $outPrefix = $this->outputBase . '/' . Str::uuid() . '/';
        $gcsOutUri = $this->toGcsUri($outPrefix);

        $client = new ImageAnnotatorClient([
            'credentials' => storage_path('app/eco-splicer-468114-t0-54c2adb26581.json'),
        ]);

        $feature = (new Feature())->setType(Feature\Type::DOCUMENT_TEXT_DETECTION);

        $inputConfig = (new InputConfig())
            ->setMimeType('application/pdf')
            ->setGcsSource((new GcsSource())->setUri($gcsPdfUri));

        $outputConfig = (new OutputConfig())
            ->setGcsDestination((new GcsDestination())->setUri($gcsOutUri));

        $request = (new AsyncAnnotateFileRequest())
            ->setInputConfig($inputConfig)
            ->setFeatures([$feature])
            ->setOutputConfig($outputConfig);

        $op = $client->asyncBatchAnnotateFiles([$request]);
        $op->pollUntilComplete();
        $client->close();

        if (!$op->operationSucceeded()) {
            $err = $op->getError()?->getMessage() ?? 'Unknown error';
            Log::error('Vision OCR error: ' . $err);
            abort(500, 'OCR falló: ' . $err);
        }

        [$text, $pages, $objects] = $this->collectOutputText($outPrefix);

        return [$outPrefix, $text, $pages, $objects];
    }

    private function collectOutputText(string $outPrefix): array
    {
        set_time_limit(300);

        $storage = new StorageClient([
            'projectId' => $this->projectId,
            'keyFilePath' => storage_path('app/eco-splicer-468114-t0-54c2adb26581.json'),
        ]);

        $bucket = $storage->bucket($this->bucket);
        $texts = [];
        $pages = 0;
        $objects = [];

        foreach ($bucket->objects(['prefix' => $outPrefix]) as $object) {
            $name = $object->name();
            if (!str_ends_with($name, '.json')) continue;

            $objects[] = $name;
            $json = json_decode($object->downloadAsString(), true);

            foreach (($json['responses'] ?? []) as $resp) {
                $t = $resp['fullTextAnnotation']['text'] ?? '';
                if ($t !== '') {
                    $texts[] = $t;
                    $pages++;
                }
            }
        }

        return [implode("\n\n", $texts), $pages, $objects];
    }

    /**
     * 🔹 Post-procesar con OpenAI para estructurar las transacciones.
     */
    private function postProcessWithOpenAI(string $rawText): array
    {
        try {
            $client = OpenAI::client(env('OPENAI_API_KEY'));

            $response = $client->chat()->create([
                'model' => 'gpt-4o-mini',
                'response_format' => [
                    "type" => "json_schema",
                    "json_schema" => [
                        "name" => "estado_cuenta",
                        "schema" => [
                            "type" => "object",
                            "properties" => [
                                "transacciones" => [
                                    "type" => "array",
                                    "items" => [
                                        "type" => "object",
                                        "properties" => [
                                            "fecha_proc" => ["type" => "string"],
                                            "fecha_valor" => ["type" => "string"],
                                            "descripcion" => ["type" => "string"],
                                            "med_at" => ["type" => "string"],
                                            "lugar" => ["type" => "string"],
                                            "suc_age" => ["type" => "string"],
                                            "num_op" => ["type" => "string"],
                                            "hora" => ["type" => "string"],
                                            "origen" => ["type" => "string"],
                                            "tipo" => ["type" => "string"],
                                            "cargo" => ["type" => "string"],
                                            "abono" => ["type" => "string"],
                                            "saldo_contable" => ["type" => "string"]
                                        ],
                                        "required" => ["fecha_proc", "descripcion", "saldo_contable"]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres un parser de estados de cuenta bancarios.'],
                    ['role' => 'user', 'content' => "Convierte este estado de cuenta bancario a JSON estructurado.
Devuelve un array de transacciones, cada una con exactamente estas claves:
- fecha_proc
- fecha_valor
- descripcion
- med_at
- lugar
- suc_age
- num_op
- hora
- origen
- tipo
- cargo
- abono
- saldo_contable

Si algún valor no está presente en el OCR, déjalo como string vacío.

Texto OCR:
$rawText"],
                ],
            ]);

            return json_decode($response->choices[0]->message->content, true)['transacciones'] ?? [];
        } catch (\Exception $e) {
            Log::error("OpenAI error: " . $e->getMessage());
            return [];
        }
    }
}
