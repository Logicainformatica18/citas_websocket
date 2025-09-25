<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Language;
use App\Models\Technology;
use App\Models\Methodology;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $courses = Course::with(['languages', 'technologies', 'methodologies'])
            ->orderBy('id', 'desc')
            ->paginate(10);

        $languages = Language::all();
        $technologies = Technology::all();
        $methodologies = Methodology::all();

        if ($request->wantsJson()) {
            return response()->json([
                'courses' => $courses,
                'languages' => $languages,
                'technologies' => $technologies,
                'methodologies' => $methodologies,
            ]);
        }

        return Inertia::render('courses/index', [
            'courses' => $courses,
            'languages' => $languages,
            'technologies' => $technologies,
            'methodologies' => $methodologies,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'languages' => 'array',
            'languages.*' => 'integer|exists:languages,id',
            'technologies' => 'array',
            'technologies.*' => 'integer|exists:technologies,id',
            'methodologies' => 'array',
            'methodologies.*' => 'integer|exists:methodologies,id',
        ]);

        $course = Course::create($request->only(['name']));

        // Sincronizamos relaciones
        $course->languages()->sync($request->languages ?? []);
        $course->technologies()->sync($request->technologies ?? []);
        $course->methodologies()->sync($request->methodologies ?? []);

        return response()->json(['message' => '✅ Curso creado', 'course' => $course->load(['languages', 'technologies', 'methodologies'])]);
    }

    public function show($id)
    {
        $course = Course::with(['languages', 'technologies', 'methodologies'])->findOrFail($id);
        return response()->json(['course' => $course]);
    }

    public function update(Request $request, $id)
    {
        $course = Course::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'languages' => 'array',
            'languages.*' => 'integer|exists:languages,id',
            'technologies' => 'array',
            'technologies.*' => 'integer|exists:technologies,id',
            'methodologies' => 'array',
            'methodologies.*' => 'integer|exists:methodologies,id',
        ]);

        $course->update($request->only(['name']));

        // Actualizamos relaciones
        $course->languages()->sync($request->languages ?? []);
        $course->technologies()->sync($request->technologies ?? []);
        $course->methodologies()->sync($request->methodologies ?? []);

        return response()->json(['message' => '✅ Curso actualizado', 'course' => $course->load(['languages', 'technologies', 'methodologies'])]);
    }

    public function destroy($id)
    {
        $course = Course::findOrFail($id);
        $course->delete();

        return response()->json(['message' => '✅ Curso eliminado']);
    }
}
