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
private function normalizeName(string $name): string
{
    return Str::lower(Str::ascii(trim($name)));
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
Extrae del siguiente sílabo la información en formato JSON estricto.

Reglas:
- 'curso' debe contener el nombre principal del sílabo.
- 'lenguajes' incluyen todos los lenguajes utilizados en el desarrollo de software,
  no solo los de programación. Esto abarca HTML, CSS, SQL, XML, JSON, etc.
- 'tecnologias' incluyen frameworks, librerías, herramientas de software, modelos, motores,
  estructuras de datos, algoritmos, conceptos o tecnologías relevantes como 'Inteligencia Artificial'.
  Para cada elemento incluye su tipo (framework, library, tool, cloud, database, engine, model, concept, etc.),
  siguiendo una de las categorías existentes en la tabla technology_categories.
- Si el texto menciona algoritmos heurísticos, árboles de decisión, grafos,
  búsqueda inteligente o toma de decisiones automatizada, incluye 'Inteligencia Artificial'
  como tecnología con tipo 'model' o 'concept'.
- 'metodologias' deben ser metodologías de desarrollo de software (Scrum, Kanban, XP, Cascada, RUP, Agile, DevOps).
- Ignora metodologías de enseñanza o aprendizaje (aprendizaje cooperativo, adaptativo, basado en problemas, método de casos, etc.).
- Devuelve SOLO el JSON solicitado, sin texto adicional ni comentarios.

Formato JSON:
{
  \"curso\": \"\",
  \"lenguajes\": [],
  \"tecnologias\": [
    { \"nombre\": \"\", \"tipo\": \"\" }
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

      // 🧠 Normaliza para comparar sin tildes ni mayúsculas


// 🧩 Normalizar nombre del curso detectado
$normalizedName = Str::title(Str::ascii(trim($decoded['curso'])));
$normalizedKey = $this->normalizeName($normalizedName);

// 🔍 Buscar coincidencia exacta ignorando tildes y ñ/ni
$existingCourse = \App\Models\Course::all()->first(function ($c) use ($normalizedKey) {
    return $this->normalizeName($c->name) === $normalizedKey;
});

// ⚙️ Crear o reutilizar
if ($existingCourse) {
    $course = $existingCourse;
    Log::info("🔁 Curso encontrado (coincidencia flexible): {$existingCourse->name}");
} else {
    $course = \App\Models\Course::create(['name' => $normalizedName]);
    Log::info("🆕 Curso creado: {$normalizedName}");
}

            // =====================================================
// 🔁 ACTUALIZAR RELACIONES SIN ELIMINAR LAS EXISTENTES
// =====================================================

       // =====================================================
// 🔁 ACTUALIZAR RELACIONES (REGENERAR COMPLETAMENTE)
// =====================================================

// 🧠 1. Lenguajes — se reemplazan completamente (el sílabo define los nuevos)
$languageIds = [];
if (!empty($decoded['lenguajes'])) {
    foreach ($decoded['lenguajes'] as $langName) {
        $lang = \App\Models\Language::firstOrCreate(['name' => trim($langName)]);
        $languageIds[] = $lang->id;
    }
}
// 🔄 Se eliminan las relaciones anteriores y se agregan las nuevas detectadas
$course->languages()->sync($languageIds);

// 🧠 2. Tecnologías — también se regeneran, porque cambian con los sílabos
$techIds = [];
if (!empty($decoded['tecnologias'])) {
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

        // 🧩 Si la IA devolvió un tipo y la tecnología no tiene categoría, se asigna
        if ($type && !$tech->category_id) {
            $category = \App\Models\TechnologyCategory::firstOrCreate(['name' => $type]);
            $tech->category_id = $category->id;
            $tech->save();
        }

        $techIds[] = $tech->id;
    }
}
// 🔄 Se regeneran las relaciones tecnológicas
$course->technologies()->sync($techIds);

// 🧠 3. Metodologías — igual, se reemplazan para reflejar solo las vigentes
$methIds = [];
if (!empty($decoded['metodologias'])) {
    foreach ($decoded['metodologias'] as $methName) {
        $meth = \App\Models\Methodology::firstOrCreate(['name' => trim($methName)]);
        $methIds[] = $meth->id;
    }
}
// 🔄 Se reemplazan las metodologías anteriores por las nuevas
$course->methodologies()->sync($methIds);

Log::info("✅ Curso '{$decoded['curso']}' actualizado (relaciones regeneradas completamente).");








        } catch (\Exception $e) {
            Log::error("❌ Error en ProcessSyllabusJob: " . $e->getMessage(), [
                'syllabus_id' => $this->syllabusId,
            ]);

            $syllabus->update(['status' => 'failed']);
        }
    }
}
