<?php

namespace App\Jobs;

use App\Models\Syllabus;
use App\Models\Course;
use App\Models\Language;
use App\Models\Technology;
use App\Models\Methodology;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\InputConfig;
use Google\Cloud\Vision\V1\OutputConfig;
use Google\Cloud\Vision\V1\GcsSource;
use Google\Cloud\Vision\V1\GcsDestination;
use Google\Cloud\Vision\V1\AsyncAnnotateFileRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessSyllabusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $syllabusId;

    public function __construct($syllabusId)
    {
        $this->syllabusId = $syllabusId;
    }

    public function handle(): void
    {
        $syllabus = Syllabus::find($this->syllabusId);

        if (!$syllabus) {
            Log::error("❌ Syllabus con ID {$this->syllabusId} no encontrado.");
            return;
        }

        $syllabus->update(['status' => 'processing']);

        try {
            // 📂 Subir PDF a GCS
            $localPath = Storage::disk('public')->path($syllabus->path);
            $gcsPath = "syllabus/{$syllabus->id}.pdf";

            $storage = new \Google\Cloud\Storage\StorageClient([
                'projectId' => env('GCS_PROJECT_ID'),
                'keyFilePath' => env('GCS_KEY_FILE_PATH'),
            ]);

            $bucket = $storage->bucket(env('GCS_BUCKET'));
            $bucket->upload(
                fopen($localPath, 'r'),
                ['name' => $gcsPath]
            );

            $gcsInputUri = "gs://" . env('GCS_BUCKET') . "/" . $gcsPath;
            Log::info("☁️ PDF subido a GCS", ['uri' => $gcsInputUri]);

            // 📤 Configuración de entrada
            $gcsSource = (new GcsSource())->setUri($gcsInputUri);
            $inputConfig = (new InputConfig())
                ->setMimeType('application/pdf')
                ->setGcsSource($gcsSource);

            // 📥 Configuración de salida
            $gcsDestinationUri = "gs://" . env('GCS_BUCKET') . "/syllabus_results/{$syllabus->id}/";
            $gcsDestination = (new GcsDestination())->setUri($gcsDestinationUri);
            $outputConfig = (new OutputConfig())->setGcsDestination($gcsDestination);

            $feature = (new Feature())->setType(Feature\Type::DOCUMENT_TEXT_DETECTION);

            // ✅ Crear AsyncAnnotateFileRequest
            $request = new AsyncAnnotateFileRequest();
            $request->setInputConfig($inputConfig);
            $request->setFeatures([$feature]);
            $request->setOutputConfig($outputConfig);

            $client = new ImageAnnotatorClient([
                'credentials' => env('GCS_KEY_FILE_PATH'),
            ]);

       // 📡 Llamar a Vision API con polling extendido
$operation = $client->asyncBatchAnnotateFiles([$request]);

$maxAttempts = 20;      // hasta 20 intentos (~2 minutos)
$interval = 6;          // segundos entre intentos

for ($i = 1; $i <= $maxAttempts; $i++) {
    if ($operation->isDone()) break;

    Log::info("⌛ Esperando OCR (intento {$i}/{$maxAttempts}) para syllabus {$this->syllabusId}...");
    sleep($interval);
    $operation->reload(); // vuelve a consultar el estado
}

if (!$operation->operationSucceeded()) {
    $errorMessage = $operation->getError() ? $operation->getError()->getMessage() : 'desconocido';
    throw new \Exception("Error en OCR PDF: {$errorMessage}");
}


            if (!$operation->operationSucceeded()) {
                throw new \Exception("Error en OCR PDF: " . $operation->getError()->getMessage());
            }

            Log::info("✅ OCR terminado, resultados en GCS", ['output' => $gcsDestinationUri]);

            // 📥 Leer JSON de resultados
            $files = $bucket->objects([
                'prefix' => "syllabus_results/{$syllabus->id}/",
            ]);

            $jsonData = null;
            foreach ($files as $file) {
                if (str_ends_with($file->name(), '.json')) {
                    $jsonContent = $file->downloadAsString();
                    $jsonData = json_decode($jsonContent, true);
                    break;
                }
            }

            if (!$jsonData) {
                throw new \Exception("No se encontraron resultados OCR en GCS.");
            }

            $text = '';
            foreach ($jsonData['responses'] as $page) {
                if (!empty($page['fullTextAnnotation']['text'])) {
                    $text .= $page['fullTextAnnotation']['text'] . "\n";
                }
            }

            if (!$text) {
                throw new \Exception("El OCR no devolvió texto.");
            }

            $syllabus->update(['raw_text' => $text]);
            Log::info("📝 OCR completado correctamente", [
                'syllabus_id' => $this->syllabusId,
                'chars' => strlen($text),
            ]);

            // 🤖 Procesar con OpenAI
        $prompt = "
Extrae del siguiente sílabo la información en JSON.

IMPORTANTE:
- 'lenguajes' deben ser lenguajes de programación (Java, Python, C#, JavaScript, etc.)
- 'tecnologias' deben ser frameworks, librerías o herramientas de software, e incluir su tipo (framework, library, tool, cloud, database, etc.)
- 'metodologias' deben ser metodologías de desarrollo de software (Scrum, Kanban, XP, Cascada, RUP, Agile, DevOps)
- Ignora metodologías de enseñanza o aprendizaje.

Formato JSON:
{
  \"curso\": \"\",
  \"lenguajes\": [],
  \"tecnologias\": [
    { \"nombre\": \"Laravel\", \"tipo\": \"framework\" },
    { \"nombre\": \"AWS S3\", \"tipo\": \"cloud\" }
  ],
  \"metodologias\": []
}

Texto:
$text
";


            $openaiResponse = Http::withToken(env('OPENAI_API_KEY'))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Eres un asistente que convierte sílabos en JSON estricto. Devuelve SOLO JSON.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ],
                    ],
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'], // 👈 fuerza JSON válido
                ]);

            $json = $openaiResponse->json('choices.0.message.content');

            // 🔥 Limpieza: quitar posibles ```json ... ```
            $json = trim($json);
            $json = preg_replace('/^```(json)?/i', '', $json);
            $json = preg_replace('/```$/', '', $json);
            $json = trim($json);

            $decoded = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE || !$decoded) {
                throw new \Exception("OpenAI no devolvió JSON válido: " . json_last_error_msg() . " → " . $json);
            }

            // Guardar en DB
            $syllabus->update([
                'structured_data' => $decoded,
                'status' => 'processed',
            ]);

            Log::info("📊 Datos estructurados guardados", [
                'curso' => $decoded['curso'] ?? null,
                'lenguajes' => $decoded['lenguajes'] ?? [],
                'tecnologias' => $decoded['tecnologias'] ?? [],
                'metodologias' => $decoded['metodologias'] ?? []
            ]);

            // 📊 Crear o buscar curso por nombre único
          $normalizedName = Str::title(Str::ascii(trim($decoded['curso'])));
$course = Course::firstOrCreate(['name' => $normalizedName]);
            // =====================================================
// 🔁 ACTUALIZAR RELACIONES SIN ELIMINAR LAS EXISTENTES
// =====================================================

            // Lenguajes
            if (!empty($decoded['lenguajes'])) {
                $languageIds = [];
                foreach ($decoded['lenguajes'] as $langName) {
                    $lang = Language::firstOrCreate(['name' => trim($langName)]);
                    $languageIds[] = $lang->id;
                }

                // 👉 En lugar de sync() (que reemplaza), usamos syncWithoutDetaching()
                $course->languages()->syncWithoutDetaching($languageIds);
            }

            // Tecnologías
          // 🧠 Procesar tecnologías con categoría
// Tecnologías
if (!empty($decoded['tecnologias'])) {
    $techIds = [];

    foreach ($decoded['tecnologias'] as $techItem) {
        if (is_array($techItem)) {
            $name = trim($techItem['nombre'] ?? '');
            $type = trim($techItem['tipo'] ?? '');
        } else {
            $name = trim($techItem);
            $type = null;
        }

        if ($name === '') continue;

        $tech = \App\Models\Technology::firstOrCreate(['name' => $name]);

        // 🧠 Si la IA devolvió tipo y el registro no tiene categoría, la asignamos
        if ($type && !$tech->category_id) {
            $category = \App\Models\TechnologyCategory::firstOrCreate(['name' => $type]);
            $tech->category_id = $category->id;
            $tech->save();
        }

        $techIds[] = $tech->id;
    }

    $course->technologies()->syncWithoutDetaching($techIds);
}



            // Metodologías
            if (!empty($decoded['metodologias'])) {
                $methIds = [];
                foreach ($decoded['metodologias'] as $methName) {
                    $meth = Methodology::firstOrCreate(['name' => trim($methName)]);
                    $methIds[] = $meth->id;
                }
                $course->methodologies()->syncWithoutDetaching($methIds);
            }

            Log::info("✅ Curso '{$decoded['curso']}' actualizado con nuevas relaciones sin eliminar las previas.");


            Log::info("✅ Procesado syllabus ID {$this->syllabusId} → curso '{$decoded['curso']}'");

        } catch (\Exception $e) {
            Log::error("❌ Error en ProcessSyllabusJob: " . $e->getMessage(), [
                'syllabus_id' => $this->syllabusId,
            ]);

            $syllabus->update(['status' => 'failed']);
        }
    }
}
