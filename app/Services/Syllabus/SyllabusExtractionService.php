<?php

namespace App\Services\Syllabus;

use App\Events\SyllabusProcessed;
use App\Jobs\AnalyzeCourseJob;
use App\Models\Career;
use App\Models\Course;
use App\Models\Language;
use App\Models\Methodology;
use App\Models\Syllabus;
use App\Models\Technology;
use App\Models\TechnologyCategory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Extracción de lenguajes, tecnologías y metodologías de un sílabo ya convertido a texto.
 *
 * La comparten la subida por PDF (ProcessSyllabusJob, tras el OCR) y la ingesta desde
 * silabos_v2 (ProcessIngestedSyllabusJob, que ya trae el texto). El prompt y la forma de
 * regenerar los pivotes del curso son los mismos que tenía ProcessSyllabusJob.
 */
class SyllabusExtractionService
{
    /**
     * Procesa el raw_text del sílabo y actualiza el curso. Lanza excepción si falla;
     * el job que lo llama decide el estado 'failed'.
     */
    public function process(Syllabus $syllabus): void
    {
        $decoded = $this->extract((string) $syllabus->raw_text);

        // En los sílabos de silabos_v2 el nombre del curso es dato, no lo que infiere la IA.
        if ($syllabus->isFromSilabosV2()) {
            $decoded['curso_detectado'] = $decoded['curso'] ?? null;
            $decoded['curso'] = $syllabus->course_name;
        }

        // Se guarda sin marcar 'processed': eso ocurre recién tras sincronizar los pivotes.
        $syllabus->update(['structured_data' => $decoded]);

        Log::info("📊 Datos estructurados guardados", [
            'curso' => $decoded['curso'] ?? null,
            'lenguajes' => $decoded['lenguajes'] ?? [],
            'tecnologias' => $decoded['tecnologias'] ?? [],
            'metodologias' => $decoded['metodologias'] ?? []
        ]);

        $course = $syllabus->isFromSilabosV2()
            ? $this->resolveCourseByCode($syllabus)
            : $this->resolveCourseByName((string) ($decoded['curso'] ?? ''));

        $syllabus->update(['course_id' => $course->id]);

        if (!$this->mayUpdateCourse($syllabus->fresh(), $course)) {
            return;
        }

        $this->syncCourseRelations($course, $decoded);

        if ($syllabus->isFromSilabosV2()) {
            $this->supersedeUploadsOfCourse($syllabus, $course);
            $this->attachCareers($syllabus, $course);
        }

        $syllabus->update(['status' => 'processed']);

        event(new SyllabusProcessed($syllabus->fresh()));

        if ($syllabus->isFromSilabosV2() && config('silabos_v2.analyze_after_ingest')) {
            AnalyzeCourseJob::dispatch($course->id);
        }
    }

    /** Llama a OpenAI con el mismo prompt que usaba ProcessSyllabusJob. */
    public function extract(string $text): array
    {
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

        $openaiResponse = Http::withToken(config('services.openai.key'))
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

        $json = (string) $openaiResponse->json('choices.0.message.content');

        // 🔥 Limpieza: quitar posibles ```json ... ```
        $json = trim($json);
        $json = preg_replace('/^```(json)?/i', '', $json);
        $json = preg_replace('/```$/', '', $json);
        $json = trim($json);

        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !$decoded) {
            throw new \Exception("OpenAI no devolvió JSON válido: " . json_last_error_msg() . " → " . $json);
        }

        return $decoded;
    }

    /** Búsqueda flexible por nombre (sin tildes ni mayúsculas); crea el curso si no existe. */
    public function resolveCourseByName(string $detectedName): Course
    {
        $normalizedName = Str::title(Str::ascii(trim($detectedName)));
        $normalizedKey = $this->normalizeName($normalizedName);

        $existingCourse = Course::all()->first(function ($c) use ($normalizedKey) {
            return $this->normalizeName($c->name) === $normalizedKey;
        });

        if ($existingCourse) {
            Log::info("🔁 Curso encontrado (coincidencia flexible): {$existingCourse->name}");
            return $existingCourse;
        }

        $course = Course::create(['name' => $normalizedName]);
        Log::info("🆕 Curso creado: {$normalizedName}");

        return $course;
    }

    /**
     * Sílabos de silabos_v2: primero por código; si no, un curso existente del mismo nombre
     * que todavía no tenga código (se le asigna); si no, uno nuevo con nombre y código.
     */
    public function resolveCourseByCode(Syllabus $syllabus): Course
    {
        if ($course = Course::where('code', $syllabus->course_code)->first()) {
            return $course;
        }

        $normalizedName = Str::title(Str::ascii(trim((string) $syllabus->course_name)));
        $normalizedKey = $this->normalizeName($normalizedName);

        $byName = Course::whereNull('code')->get()->first(
            fn ($c) => $this->normalizeName($c->name) === $normalizedKey
        );

        if ($byName) {
            $byName->update(['code' => $syllabus->course_code]);
            Log::info("🔗 Curso '{$byName->name}' enlazado al código {$syllabus->course_code}");
            return $byName;
        }

        $course = Course::create(['name' => $normalizedName, 'code' => $syllabus->course_code]);
        Log::info("🆕 Curso creado: {$normalizedName} ({$syllabus->course_code})");

        return $course;
    }

    /** Regenera completamente los pivotes del curso con lo que define el sílabo. */
    public function syncCourseRelations(Course $course, array $decoded): void
    {
        // 🧠 1. Lenguajes — se reemplazan completamente (el sílabo define los nuevos)
        $languageIds = [];
        foreach ($decoded['lenguajes'] ?? [] as $langName) {
            if (trim((string) $langName) === '') continue;
            $languageIds[] = Language::firstOrCreate(['name' => trim($langName)])->id;
        }
        $course->languages()->sync($languageIds);

        // 🧠 2. Tecnologías — también se regeneran, porque cambian con los sílabos
        $techIds = [];
        foreach ($decoded['tecnologias'] ?? [] as $techItem) {
            if (is_array($techItem)) {
                $name = trim($techItem['nombre'] ?? '');
                $type = trim($techItem['tipo'] ?? '');
            } else {
                $name = trim($techItem);
                $type = null;
            }

            if ($name === '') continue;

            $tech = Technology::firstOrCreate(['name' => $name]);

            // 🧩 Si la IA devolvió un tipo y la tecnología no tiene categoría, se asigna
            if ($type && !$tech->category_id) {
                $category = TechnologyCategory::firstOrCreate(['name' => $type]);
                $tech->category_id = $category->id;
                $tech->save();
            }

            $techIds[] = $tech->id;
        }
        $course->technologies()->sync($techIds);

        // 🧠 3. Metodologías — igual, se reemplazan para reflejar solo las vigentes
        $methIds = [];
        foreach ($decoded['metodologias'] ?? [] as $methName) {
            if (trim((string) $methName) === '') continue;
            $methIds[] = Methodology::firstOrCreate(['name' => trim($methName)])->id;
        }
        $course->methodologies()->sync($methIds);

        Log::info("✅ Curso '{$course->name}' actualizado (relaciones regeneradas completamente).");
    }

    /**
     * Solo el sílabo vigente del curso puede regenerar sus pivotes:
     *  - uno de silabos_v2 que fue reemplazado mientras esperaba en la cola, no;
     *  - un PDF subido para un curso que ya tiene un sílabo vigente de silabos_v2, tampoco
     *    (el PDF queda guardado y marcado como reemplazado).
     */
    private function mayUpdateCourse(Syllabus $syllabus, Course $course): bool
    {
        if ($syllabus->superseded_at) {
            $syllabus->update(['status' => 'superseded']);
            Log::info("⏭️ Sílabo {$syllabus->id} reemplazado antes de procesarse; no se tocan los pivotes.");
            return false;
        }

        if ($syllabus->isFromSilabosV2()) {
            return true;
        }

        $current = Syllabus::where('source', Syllabus::SOURCE_SILABOS_V2)
            ->where('course_id', $course->id)
            ->where('status', 'processed')
            ->current()
            ->first();

        if (!$current) {
            return true;
        }

        $syllabus->update([
            'status' => 'processed',
            'superseded_at' => now(),
            'superseded_by' => $current->id,
        ]);
        Log::info("⏭️ PDF {$syllabus->id} guardado como reemplazado: el curso '{$course->name}' ya tiene el sílabo {$current->id} de silabos_v2.");

        return false;
    }

    /** Los PDF subidos del mismo curso dejan de contar para la alineación. */
    private function supersedeUploadsOfCourse(Syllabus $syllabus, Course $course): void
    {
        $courseKey = $this->normalizeName((string) $course->name);

        $ids = Syllabus::where('source', Syllabus::SOURCE_UPLOAD)
            ->current()
            ->get(['id', 'course_id', 'structured_data'])
            ->filter(fn ($s) => $s->course_id === $course->id
                || $this->normalizeName((string) ($s->structured_data['curso'] ?? '')) === $courseKey)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            Syllabus::whereIn('id', $ids)->update(['superseded_at' => now(), 'superseded_by' => $syllabus->id]);
            Log::info("🗂️ PDF reemplazados por el sílabo {$syllabus->id}", ['ids' => $ids->all()]);
        }
    }

    /** Vincula el curso a las carreras mapeadas en config('silabos_v2.career_map'), sin quitar las existentes. */
    private function attachCareers(Syllabus $syllabus, Course $course): void
    {
        $map = config('silabos_v2.career_map', []);
        $careerIds = [];

        foreach ($syllabus->careers ?? [] as $career) {
            $key = $syllabus->external_model . ':' . ($career['codigo'] ?? '');
            $slug = $map[$key] ?? null;
            $id = $slug ? Career::where('slug', $slug)->value('id') : null;

            if ($id) {
                $careerIds[] = $id;
            } else {
                Log::channel('silabos_revision')->info('Carrera sin mapeo', [
                    'motivo' => $slug ? 'slug_inexistente' : 'sin_mapeo',
                    'carrera' => $key,
                    'nombre' => $career['nombre'] ?? null,
                    'syllabus_id' => $syllabus->id,
                    'curso' => $course->name,
                ]);
            }
        }

        if ($careerIds) {
            $course->careers()->syncWithoutDetaching($careerIds);
        }
    }

    private function normalizeName(string $name): string
    {
        return Str::lower(Str::ascii(trim($name)));
    }
}
