<?php

namespace App\Services\Syllabus;

use App\Jobs\ProcessIngestedSyllabusJob;
use App\Models\Course;
use App\Models\Syllabus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Vinculación manual de los sílabos de silabos_v2 cuyo código no tiene curso en el observatorio
 * (estado 'pending_link'). Vincular asigna el código al curso elegido y procesa el sílabo;
 * "no vincular" lo deja como 'ignored'.
 */
class CourseLinkService
{
    /**
     * Asigna el código del sílabo al curso y encola el sílabo (y cualquier otro pendiente
     * con el mismo código). Devuelve los ids de sílabos encolados.
     *
     * @return int[]
     * @throws ValidationException si el sílabo ya no está pendiente, el curso ya tiene otro
     *         código o el código ya está asignado a otro curso
     */
    public function link(Syllabus $syllabus, Course $course): array
    {
        $ids = DB::transaction(function () use ($syllabus, $course) {
            $syllabus = Syllabus::lockForUpdate()->findOrFail($syllabus->id);
            $course = Course::lockForUpdate()->findOrFail($course->id);
            $code = $syllabus->course_code;

            if ($syllabus->status !== Syllabus::STATUS_PENDING_LINK) {
                throw ValidationException::withMessages([
                    'syllabus' => "El sílabo {$code} ya no está pendiente de vinculación.",
                ]);
            }

            if ($course->code !== null && $course->code !== $code) {
                throw ValidationException::withMessages([
                    'course_id' => "El curso «{$course->name}» ya tiene el código {$course->code}.",
                ]);
            }

            $owner = Course::where('code', $code)->where('id', '!=', $course->id)->first();
            if ($owner) {
                throw ValidationException::withMessages([
                    'course_id' => "El código {$code} ya está asignado al curso «{$owner->name}».",
                ]);
            }

            $course->update(['code' => $code]);
            Log::info("🔗 Código {$code} asignado al curso '{$course->name}' desde Vinculación de cursos.");

            return $this->markPendingAsQueued($code);
        });

        $this->dispatch($ids);

        return $ids;
    }

    /** "No vincular": el sílabo queda como ignorado y no se procesa. */
    public function ignore(Syllabus $syllabus): void
    {
        DB::transaction(function () use ($syllabus) {
            $syllabus = Syllabus::lockForUpdate()->findOrFail($syllabus->id);

            if ($syllabus->status !== Syllabus::STATUS_PENDING_LINK) {
                throw ValidationException::withMessages([
                    'syllabus' => "El sílabo {$syllabus->course_code} ya no está pendiente de vinculación.",
                ]);
            }

            $syllabus->update(['status' => 'ignored']);
        });

        Log::channel('silabos_revision')->info('Sílabo no vinculado: marcado como ignorado', [
            'syllabus_id' => $syllabus->id,
            'silabo_id' => $syllabus->external_id,
            'codigo' => $syllabus->course_code,
            'curso' => $syllabus->course_name,
        ]);
    }

    /**
     * Encola los sílabos pendientes cuyo código ya tiene curso (por ejemplo, tras asignar
     * códigos en bloque). Devuelve los ids encolados.
     *
     * @return int[]
     */
    public function processPendingWithCode(string $code): array
    {
        $ids = DB::transaction(fn () => $this->markPendingAsQueued($code));
        $this->dispatch($ids);

        return $ids;
    }

    /** @return int[] */
    private function markPendingAsQueued(string $code): array
    {
        $ids = Syllabus::pendingLink()->current()->where('course_code', $code)->pluck('id')->all();
        Syllabus::whereIn('id', $ids)->update(['status' => 'pending']);

        return $ids;
    }

    private function dispatch(array $ids): void
    {
        foreach ($ids as $id) {
            ProcessIngestedSyllabusJob::dispatch($id);
        }
    }
}
