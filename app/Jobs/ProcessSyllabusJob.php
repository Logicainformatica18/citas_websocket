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
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

            Storage::disk('gcs')->put($gcsPath, file_get_contents($localPath));
            $gcsInputUri = "gs://" . env('GOOGLE_CLOUD_BUCKET') . "/" . $gcsPath;

            Log::info("☁️ PDF subido a GCS", ['uri' => $gcsInputUri]);

            // 📤 Configuración de entrada (PDF en GCS)
            $gcsSource = (new GcsSource())->setUri($gcsInputUri);
            $inputConfig = (new InputConfig())
                ->setMimeType('application/pdf')
                ->setGcsSource($gcsSource);

            // 📥 Configuración de salida (JSON OCR en GCS)
            $gcsDestinationUri = "gs://" . env('GOOGLE_CLOUD_BUCKET') . "/syllabus_results/{$syllabus->id}/";
            $gcsDestination = (new GcsDestination())->setUri($gcsDestinationUri);
            $outputConfig = (new OutputConfig())->setGcsDestination($gcsDestination);

            $feature = (new Feature())->setType(Feature\Type::DOCUMENT_TEXT_DETECTION);

            $client = new ImageAnnotatorClient();
            $operation = $client->asyncBatchAnnotateFiles([
                'requests' => [[
                    'inputConfig' => $inputConfig,
                    'features' => [$feature],
                    'outputConfig' => $outputConfig,
                ]],
            ]);

            // Esperar resultado
            $operation->pollUntilComplete();

            if (!$operation->operationSucceeded()) {
                throw new \Exception("Error en OCR PDF: " . $operation->getError()->getMessage());
            }

            Log::info("✅ OCR terminado, resultados en GCS", ['output' => $gcsDestinationUri]);

            // 📥 Descargar JSON con OCR desde GCS
            $files = Storage::disk('gcs')->files("syllabus_results/{$syllabus->id}");
            if (empty($files)) {
                throw new \Exception("No se encontraron resultados OCR en GCS.");
            }

            $jsonData = json_decode(Storage::disk('gcs')->get($files[0]), true);

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
Extrae del siguiente sílabo de universidad la información en JSON:
{
  \"curso\": \"\",
  \"lenguajes\": [],
  \"tecnologias\": [],
  \"metodologias\": []
}

Texto:
$text
";

            $openaiResponse = Http::withToken(env('OPENAI_API_KEY'))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Eres un asistente que convierte sílabos en datos estructurados JSON.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.2,
                ]);

            $json = $openaiResponse->json('choices.0.message.content');
            $decoded = json_decode($json, true);

            if (!$decoded) {
                throw new \Exception("OpenAI no devolvió JSON válido: " . $json);
            }

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

            // 📊 Crear o actualizar curso
            $course = Course::updateOrCreate(
                ['name' => $decoded['curso']],
                []
            );

            // Lenguajes
            if (!empty($decoded['lenguajes'])) {
                $languageIds = [];
                foreach ($decoded['lenguajes'] as $langName) {
                    $lang = Language::firstOrCreate(['name' => $langName]);
                    $languageIds[] = $lang->id;
                }
                $course->languages()->sync($languageIds);
            }

            // Tecnologías
            if (!empty($decoded['tecnologias'])) {
                $techIds = [];
                foreach ($decoded['tecnologias'] as $techName) {
                    $tech = Technology::firstOrCreate(['name' => $techName]);
                    $techIds[] = $tech->id;
                }
                $course->technologies()->sync($techIds);
            }

            // Metodologías
            if (!empty($decoded['metodologias'])) {
                $methIds = [];
                foreach ($decoded['metodologias'] as $methName) {
                    $meth = Methodology::firstOrCreate(['name' => $methName]);
                    $methIds[] = $meth->id;
                }
                $course->methodologies()->sync($methIds);
            }

            Log::info("✅ Procesado syllabus ID {$this->syllabusId} → curso '{$decoded['curso']}'");

        } catch (\Exception $e) {
            Log::error("❌ Error en ProcessSyllabusJob: " . $e->getMessage(), [
                'syllabus_id' => $this->syllabusId,
            ]);

            $syllabus->update(['status' => 'failed']);
        }
    }
}
