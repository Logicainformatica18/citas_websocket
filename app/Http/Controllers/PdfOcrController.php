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
     * Sube un PDF desde un <form> y devuelve el texto OCR.
     * POST /ocr/pdf/upload
     */
    public function uploadAndOcr(Request $request)
    {
        $data = $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:51200'], // 50MB demo
        ]);

        // 1) Subir a GCS
        $localPath = $data['pdf']->getRealPath();
        $destPath = 'uploads/pdfs/' . time() . '_' . $data['pdf']->getClientOriginalName();
        $gcsPdfUri = $this->uploadToGcs($localPath, $destPath);

        // 2) Lanzar OCR + leer salida
        [$outPrefix, $text, $pages, $objects] = $this->runOcrAndCollect($gcsPdfUri);

        return response()->json([
            'ok' => true,
            'gcs_pdf_uri' => $gcsPdfUri,
            'out_prefix' => $outPrefix,
            'pages' => $pages,
            'text' => $text,
            'result_objects' => $objects, // archivos .json generados
        ]);
    }

    /**
     * Procesa un PDF que ya existe en GCS (en tu bucket).
     * POST /ocr/pdf/existing  body: { "object": "MODELO-DE-OFICIO.pdf" }
     *    o con ruta: { "object": "carpeta/archivo.pdf" }
     */
    public function ocrExisting(Request $request)
    {
        $data = $request->validate([
            'object' => ['required', 'string'], // nombre dentro del bucket
        ]);

        $gcsPdfUri = $this->toGcsUri($data['object']);

        [$outPrefix, $text, $pages, $objects] = $this->runOcrAndCollect($gcsPdfUri);

        return response()->json([
            'ok' => true,
            'gcs_pdf_uri' => $gcsPdfUri,
            'out_prefix' => $outPrefix,
            'pages' => $pages,
            'text' => $text,
            'result_objects' => $objects,
        ]);
    }

    /* ===================== Helpers ===================== */

    /** Sube un archivo local a GCS y devuelve la URI gs://bucket/obj */
    private function uploadToGcs(string $localPath, string $destPath): string
    {
        $storage = new StorageClient([
            'projectId' => $this->projectId,
            'keyFilePath' => storage_path('app/eco-splicer-468114-t0-54c2adb26581.json'),
        ]);

        $bucket = $storage->bucket($this->bucket);
        $bucket->upload(fopen($localPath, 'r'), [
            'name' => $destPath,
            // sin ACLs; usa solo IAM del bucket
        ]);


        return $this->toGcsUri($destPath);
    }

    /** Convierte "folder/file.pdf" a "gs://bucket/folder/file.pdf" */
    private function toGcsUri(string $objectPath): string
    {
        $objectPath = ltrim($objectPath, '/');
        return sprintf('gs://%s/%s', $this->bucket, $objectPath);
    }

    /**
     * Lanza Vision async (PDF) y recopila texto de los .json de salida.
     * Devuelve array [outPrefix, text, pages, objects]
     */
    private function runOcrAndCollect(string $gcsPdfUri): array
    {
        $outPrefix = $this->outputBase . '/' . Str::uuid() . '/';
        $gcsOutUri = $this->toGcsUri($outPrefix);

        // 1) Lanzar OCR asíncrono (bloqueamos hasta terminar para demo)
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
        $op->pollUntilComplete(); // PRODUCCIÓN: mover a Job/Queue
        $client->close();

        if (!$op->operationSucceeded()) {
            $err = $op->getError()?->getMessage() ?? 'Unknown error';
            Log::error('Vision OCR error: ' . $err);
            abort(500, 'OCR falló: ' . $err);
        }

        // 2) Leer archivos .json generados y unir texto
        [$text, $pages, $objects] = $this->collectOutputText($outPrefix);

        return [$outPrefix, $text, $pages, $objects];
    }

    /** Lee los .json del prefijo y concatena el texto; devuelve [text, pages, objects[]] */
    private function collectOutputText(string $outPrefix): array
    {
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
            if (!str_ends_with($name, '.json')) {
                continue;
            }
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
}
