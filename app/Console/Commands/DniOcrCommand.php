<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class DniOcrCommand extends Command
{
    protected $signature = 'dni:ocr
        {--front= : Ruta de la imagen frontal del DNI o CE}
        {--back= : Ruta de la imagen posterior del DNI o CE}';

    protected $description = '🔍 Ejecuta OCR (Google Vision) sobre anverso y reverso de un DNI o Carnet de Extranjería, combinándolos verticalmente, generando JSON con GPT y eliminando los archivos temporales.';

    public function handle()
    {
        $frontPath = $this->option('front');
        $backPath  = $this->option('back');

        // ✅ Validar imágenes
        if (!$frontPath || !file_exists($frontPath)) {
            $this->error('❌ Debes especificar la ruta --front válida.');
            return Command::FAILURE;
        }

        if ($backPath && !file_exists($backPath)) {
            $this->warn('⚠️  La ruta --back no existe, se procesará solo el anverso.');
            $backPath = null;
        }

        // ✅ Cargar credenciales
        $credentialsPath = env('GCS_KEY_FILE_PATH', storage_path('app/google/credentials.json'));
        if (!file_exists($credentialsPath)) {
            $this->error("❌ No se encontró el archivo de credenciales de Google Vision.");
            $this->line("Ruta esperada: {$credentialsPath}");
            return Command::FAILURE;
        }

        $this->info('✅ Credenciales de Google Vision detectadas.');
        Log::info('🧩 Credenciales OCR cargadas desde: ' . $credentialsPath);

        // 🧩 Combinar imágenes si hay reverso
        $timestamp = now()->format('Ymd_His');
        $mergedPath = storage_path("app/public/dni_combined_{$timestamp}.jpeg");

        if ($backPath) {
            $this->info('🧩 Combinando imágenes (frontal arriba, reverso abajo)...');

            $manager = new ImageManager(new Driver());
            $front = $manager->read($frontPath);
            $back  = $manager->read($backPath);

            $width  = max($front->width(), $back->width());
            $height = $front->height() + $back->height() + 10;

            $canvas = $manager->create($width, $height)->fill('ffffff');

            // Frontal arriba
            $canvas->place($front, 'top-left', 0, 0);

            // Línea divisoria
            $canvas->drawLine(function ($line) use ($front, $width) {
                $line->from(0, $front->height() + 5);
                $line->to($width, $front->height() + 5);
                $line->color('000000');
                $line->width(2);
            });

            // Reverso abajo
            $canvas->place($back, 'top-left', 0, $front->height() + 10);

            $canvas->save($mergedPath);
            $this->info('🖼️  Imágenes combinadas correctamente en: ' . $mergedPath);
        } else {
            // Solo frontal
            copy($frontPath, $mergedPath);
        }

        // 🧠 OCR con Google Vision
        $vision = new ImageAnnotatorClient(['credentials' => $credentialsPath]);
        $this->info('🧠 Ejecutando OCR con Google Vision...');
        $imageData = file_get_contents($mergedPath);
        $response = $vision->textDetection($imageData);
        $texts = $response->getTextAnnotations();
        $vision->close();

        $combinedText = $texts ? $texts[0]->getDescription() : '';

        // 🧹 Eliminar imagen combinada después del OCR
        if (file_exists($mergedPath)) {
            unlink($mergedPath);
            $this->info('🧹 Imagen temporal eliminada: ' . basename($mergedPath));
        }

        if (empty($combinedText)) {
            $this->error('⚠️  No se detectó texto en la imagen combinada.');
            return Command::FAILURE;
        }

        // 📤 Enviar texto a ChatGPT
        $this->info('📤 Enviando texto combinado a ChatGPT...');

        $prompt = <<<PROMPT
Analiza el texto OCR extraído del anverso (arriba) y reverso (abajo) de un DNI o Carnet de Extranjería peruano y devuelve la información en JSON ESTRICTO.

🎯 Reglas:
- Devuelve SOLO JSON válido, sin texto adicional.
- El número de documento debe contener SOLO dígitos (0-9), sin letras ni símbolos.
- Si tiene 8 dígitos → tipo_documento = "DNI"
- Si tiene entre 9 y 12 dígitos → tipo_documento = "CARNET DE EXTRANJERIA"
- Los campos de fecha deben tener formato DD-MM-YYYY.
- Si no se encuentra, deja el valor vacío: "".
- No inventes información.

📦 Formato JSON esperado:
{
  "tipo_documento": "",
  "numero_documento": "",
  "nombres": "",
  "apellido_paterno": "",
  "apellido_materno": "",
  "sexo": "",
  "fecha_nacimiento": "DD-MM-YYYY",
  "fecha_emision": "DD-MM-YYYY",
  "fecha_caducidad": "DD-MM-YYYY",
  "codigo_verificacion": "",
  "nacionalidad": "",
  "direccion": "",
  "departamento": "",
  "provincia": "",
  "distrito": "",
  "firma": "Presente" o "No visible"
}

Texto OCR:
\"\"\"$combinedText\"\"\"
PROMPT;

        try {
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

            $jsonData = json_decode($response['choices'][0]['message']['content'] ?? '{}', true);

            if (empty($jsonData)) {
                throw new \Exception('OpenAI no devolvió datos JSON válidos.');
            }

            // 🧹 Limpiar y validar número de documento
            $num = preg_replace('/\D/', '', $jsonData['numero_documento'] ?? '');

            if (strlen($num) === 8) {
                $jsonData['tipo_documento'] = 'DNI';
            } elseif (strlen($num) >= 9 && strlen($num) <= 12) {
                $jsonData['tipo_documento'] = 'CARNET DE EXTRANJERIA';
            } else {
                $jsonData['tipo_documento'] = 'DESCONOCIDO';
            }

            $jsonData['numero_documento'] = $num;

            // 💾 Guardar JSON
            $resultsDir = storage_path('app/ocr_results');
            if (!is_dir($resultsDir)) {
                mkdir($resultsDir, 0755, true);
            }

            $filename = "{$resultsDir}/dni_{$timestamp}.json";
            file_put_contents($filename, json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->newLine();
            $this->info('✅ Datos estructurados del documento (limpios y validados):');
            $this->line(json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("💾 JSON guardado en: {$filename}");

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('❌ Error en dni:ocr: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
