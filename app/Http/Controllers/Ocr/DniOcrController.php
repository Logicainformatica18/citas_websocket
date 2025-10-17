<?php

namespace App\Http\Controllers\OCR;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DniOcrController extends Controller
{
    /**
     * 🔹 Método 1: OCR directo desde archivos locales (sin usar GCS)
     * Ideal para máxima privacidad.
     */
    public function extractLocal(Request $request)
    {
        $request->validate([
            'front' => 'required|file|mimes:jpg,jpeg,png|max:5120',
            'back'  => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
        ]);

        $credentials = storage_path('app/google/credentials.json');
        $vision = new ImageAnnotatorClient(['credentials' => $credentials]);
        $ocrTexts = [];

        try {
            foreach (['front', 'back'] as $side) {
                if (!$request->hasFile($side)) continue;

                $imageData = file_get_contents($request->file($side)->getRealPath());
                $response = $vision->textDetection($imageData);
                $texts = $response->getTextAnnotations();
                $ocrTexts[$side] = $texts ? $texts[0]->getDescription() : '';
            }

            $vision->close();

            $combinedText = trim(($ocrTexts['front'] ?? '') . "\n" . ($ocrTexts['back'] ?? ''));

            return $this->analyzeTextWithGPT($combinedText);

        } catch (\Throwable $e) {
            Log::error("❌ Error OCR local: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 🔹 Método 2: OCR usando GCS temporal (para imágenes grandes o PDF)
     * Ideal cuando necesitas procesar archivos más pesados sin saturar tu servidor.
     */
    public function extractFromGCS(Request $request)
    {
        $request->validate([
            'front' => 'required|file|mimes:jpg,jpeg,png|max:5120',
            'back'  => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
        ]);

        $bucketName = env('GOOGLE_CLOUD_STORAGE_BUCKET');
        $credentialsPath = storage_path('app/google/credentials.json');
        $storage = new \Google\Cloud\Storage\StorageClient(['keyFilePath' => $credentialsPath]);
        $bucket = $storage->bucket($bucketName);

        $ocrTexts = [];
        $uploaded = [];

        try {
            foreach (['front', 'back'] as $side) {
                if (!$request->hasFile($side)) continue;

                $file = $request->file($side);
                $fileName = 'dni_tmp/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
                $object = $bucket->upload(fopen($file->getRealPath(), 'r'), ['name' => $fileName]);
                $uploaded[] = $object;

                $vision = new ImageAnnotatorClient(['credentials' => $credentialsPath]);
                $gcsUri = "gs://{$bucketName}/{$fileName}";
                $response = $vision->textDetection($gcsUri);
                $texts = $response->getTextAnnotations();
                $ocrTexts[$side] = $texts ? $texts[0]->getDescription() : '';
                $vision->close();
            }

            // Combinar textos de ambos lados
            $combinedText = trim(($ocrTexts['front'] ?? '') . "\n" . ($ocrTexts['back'] ?? ''));

            // Borrar archivos del bucket
            foreach ($uploaded as $obj) {
                try { $obj->delete(); } catch (\Throwable $e) {}
            }

            return $this->analyzeTextWithGPT($combinedText);

        } catch (\Throwable $e) {
            foreach ($uploaded as $obj) {
                try { $obj->delete(); } catch (\Throwable $ex) {}
            }
            Log::error("❌ Error OCR GCS: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 🔹 Método privado reutilizable: Análisis con ChatGPT
     */
    private function analyzeTextWithGPT(string $combinedText)
    {
        $prompt = "
Analiza el texto extraído del anverso y reverso de un DNI o Carnet de Extranjería peruano.
Devuélvelo en formato JSON con los siguientes campos:

{
  'tipo_documento': '',
  'numero_documento': '',
  'nombres': '',
  'apellido_paterno': '',
  'apellido_materno': '',
  'sexo': '',
  'fecha_nacimiento': '',
  'fecha_emision': '',
  'fecha_caducidad': '',
  'codigo_verificacion': '',
  'nacionalidad': '',
  'direccion': '',
  'departamento': '',
  'provincia': '',
  'distrito': '',
  'firma': 'Presente / No visible'
}

Texto OCR:
\"\"\"$combinedText\"\"\"";

        $response = Http::withToken(env('OPENAI_API_KEY'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres un analista experto en documentos de identidad peruanos. Devuelve siempre JSON válido.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'response_format' => ['type' => 'json_object'],
            ])
            ->json();

        $data = json_decode($response['choices'][0]['message']['content'] ?? '{}', true);

        return response()->json([
            'status' => 'ok',
            'data' => $data,
        ]);
    }
}
