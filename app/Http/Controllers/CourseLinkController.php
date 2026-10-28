<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Syllabus;
use App\Services\Syllabus\CourseLinkService;
use App\Support\CourseNameSimilarity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 🔗 Vinculación de cursos: sílabos de silabos_v2 cuyo código no tiene curso en el observatorio.
 */
class CourseLinkController extends Controller
{
    public function index(): Response
    {
        $courses = Course::whereNull('code')
            ->with('careers:id,name')
            ->orderBy('name')
            ->get(['id', 'name']);

        $careerMap = config('silabos_v2.career_map', []);

        $pending = Syllabus::pendingLink()
            ->current()
            ->orderBy('course_name')
            ->get()
            ->map(fn (Syllabus $s) => [
                'id' => $s->id,
                'modelo' => $s->external_model,
                'silabo_id' => $s->external_id,
                'code' => $s->course_code,
                'name' => $s->course_name,
                'periodo' => $s->periodo,
                'version' => $s->external_version,
                'careers' => collect($s->careers ?? [])->map(fn ($c) => [
                    'codigo' => $c['codigo'] ?? '',
                    'nombre' => $c['nombre'] ?? '',
                    'mapped' => isset($careerMap[$s->external_model . ':' . ($c['codigo'] ?? '')]),
                ])->values(),
                'suggestion' => CourseNameSimilarity::suggest($s->course_name, $courses),
                'received_at' => optional($s->updated_at)->format('Y-m-d H:i'),
            ]);

        return Inertia::render('course-linking/index', [
            'pending' => $pending,
            'courses' => $courses->map(fn (Course $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'careers' => $c->careers->pluck('name')->values(),
            ]),
        ]);
    }

    /** Vincular a un curso (action=link, course_id) o no vincular (action=ignore). */
    public function store(Request $request, Syllabus $syllabus, CourseLinkService $links): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|in:link,ignore',
            'course_id' => 'required_if:action,link|nullable|integer|exists:courses,id',
        ], [
            'course_id.required_if' => 'Elige un curso o la opción "No vincular".',
            'course_id.exists' => 'El curso elegido no existe.',
        ]);

        if ($data['action'] === 'ignore') {
            $links->ignore($syllabus);

            return response()->json(['message' => "El sílabo {$syllabus->course_code} quedó como ignorado."]);
        }

        $course = Course::findOrFail($data['course_id']);
        $queued = $links->link($syllabus, $course);

        return response()->json([
            'message' => "Código {$syllabus->course_code} asignado a «{$course->name}». Sílabo enviado a procesar.",
            'queued' => $queued,
        ]);
    }
}
