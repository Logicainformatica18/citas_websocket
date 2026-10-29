<?php

namespace App\Services\Syllabus;

use App\Events\SyllabusProcessed;
use App\Jobs\AnalyzeCourseJob;
use App\Models\Career;
use App\Models\Course;
use App\Models\Syllabus;
use App\Services\Catalog\CatalogPrompt;
use App\Services\Catalog\CatalogResolver;
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
        // Los de silabos_v2 se enlazan solo por código y antes de llamar a OpenAI:
        // si el código no tiene curso, el sílabo queda pendiente de vinculación sin gastar la llamada.
        $course = null;
        if ($syllabus->isFromSilabosV2()) {
            $course = $this->resolveCourseByCode($syllabus);
            if (!$course) {
                $syllabus->update(['status' => Syllabus::STATUS_PENDING_LINK]);
                Log::info("🔗 Sílabo {$syllabus->id} pendiente de vinculación: ningún curso tiene el código {$syllabus->course_code}.");
                return;
            }
        }

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

        $course ??= $this->resolveCourseByName((string) ($decoded['curso'] ?? ''));

        $syllabus->update(['course_id' => $course->id]);

        if (!$this->mayUpdateCourse($syllabus->fresh(), $course)) {
            return;
        }

        $catalogo = $this->syncCourseRelations($course, $decoded);
        $syllabus->update(['structured_data' => $decoded + ['catalogo' => $catalogo]]);

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

    /**
     * Llama a OpenAI con el prompt que usaba ProcessSyllabusJob, más el catálogo existente
     * (CatalogPrompt) y la convención de nombres de market_entities.
     */
    public function extract(string $text): array
    {
     $catalogo = app(CatalogPrompt::class)->text();

     $prompt = "
Extrae del siguiente sílabo la información en formato JSON estricto.

Reglas:
- 'curso' debe contener el nombre principal del sílabo.
- 'lenguajes' incluyen todos los lenguajes utilizados en el desarrollo de software,
  no solo los de programación. Esto abarca HTML, CSS, SQL, XML, JSON, etc.
- 'tecnologias' incluyen frameworks, librerías, herramientas de software, modelos, motores,
  estructuras de datos, algoritmos, conceptos o tecnologías relevantes como 'Inteligencia Artificial'.
  Para cada elemento incluye su tipo, que debe ser una de las categorías de tecnología del catálogo.
- Si el texto menciona algoritmos heurísticos, árboles de decisión, grafos,
  búsqueda inteligente o toma de decisiones automatizada, incluye 'Inteligencia Artificial'
  como tecnología con tipo 'model' o 'concept'.
- 'metodologias' deben ser metodologías de desarrollo de software (Scrum, Kanban, XP, Cascada, RUP, Agile, DevOps).
- Ignora metodologías de enseñanza o aprendizaje (aprendizaje cooperativo, adaptativo, basado en problemas, método de casos, etc.).
- Nombres: si un lenguaje, tecnología o metodología del sílabo es la misma cosa que un elemento
  del catálogo de abajo, usa EXACTAMENTE el nombre del catálogo, aunque esté en español
  (por ejemplo 'Inteligencia Artificial', 'Cascada', 'Bases de datos relacionales').
  No uses un elemento más específico ni más general: 'Machine Learning' no es 'Azure Machine Learning'.
- Si no está en el catálogo, usa el nombre oficial del producto o estándar, normalmente en inglés
  y con su escritura habitual (por ejemplo 'Power BI', 'Node.js', 'Big Data', 'IoT', 'Machine Learning').
  No traduzcas nombres de productos.
- Devuelve SOLO el JSON solicitado, sin texto adicional ni comentarios.

Catálogo existente:
$catalogo

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
     * Sílabos de silabos_v2: solo por código exacto (courses.code = código en silabos_v2).
     * Nunca por nombre ni creando cursos; si no hay curso, null (pendiente de vinculación).
     */
    public function resolveCourseByCode(Syllabus $syllabus): ?Course
    {
        return Course::where('code', $syllabus->course_code)->first();
    }

    /**
     * Regenera completamente los pivotes del curso con lo que define el sílabo.
     * Los nombres pasan por CatalogResolver (normalización y alias de config/catalogo.php);
     * devuelve lo que no fue coincidencia exacta, para structured_data['catalogo'].
     */
    public function syncCourseRelations(Course $course, array $decoded): array
    {
        $catalog = new CatalogResolver();

        // 🧠 1. Lenguajes — se reemplazan completamente (el sílabo define los nuevos)
        $languageIds = [];
        foreach ($decoded['lenguajes'] ?? [] as $langName) {
            if ($language = $catalog->resolve('languages', is_string($langName) ? $langName : null)) {
                $languageIds[] = $language->id;
            }
        }
        $course->languages()->sync(array_unique($languageIds));

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

            $tech = $catalog->resolve('technologies', $name);
            if (!$tech) continue;

            // 🧩 Si la IA devolvió un tipo y la tecnología no tiene categoría, se asigna
            // (solo categorías existentes; una desconocida va a 'other').
            if ($type && !$tech->category_id && ($category = $catalog->resolveCategory($type))) {
                $tech->category_id = $category->id;
                $tech->save();
            }

            $techIds[] = $tech->id;
        }
        $course->technologies()->sync(array_unique($techIds));

        // 🧠 3. Metodologías — igual, se reemplazan para reflejar solo las vigentes
        $methIds = [];
        foreach ($decoded['metodologias'] ?? [] as $methName) {
            if ($methodology = $catalog->resolve('methodologies', is_string($methName) ? $methName : null)) {
                $methIds[] = $methodology->id;
            }
        }
        $course->methodologies()->sync(array_unique($methIds));

        Log::info("✅ Curso '{$course->name}' actualizado (relaciones regeneradas completamente).");

        return $catalog->report();
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

    /** Vincula el curso solo a las carreras mapeadas en config('silabos_v2.career_map'), sin quitar las existentes. */
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
            } elseif ($slug) {
                // Las carreras sin mapeo son normales (silabos_v2 manda todas); un slug que no existe, no.
                Log::channel('silabos_revision')->warning('Carrera mapeada a un slug inexistente', [
                    'carrera' => $key,
                    'slug' => $slug,
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
