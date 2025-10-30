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
public function listAll()
{
     // ⚡ Solo necesitamos id y name, sin paginación pesada
    $courses = \App\Models\Course::select('id', 'name')
        ->orderBy('name', 'asc')
        ->get();

    return response()->json([
        'courses' => $courses,
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
public function search(Request $request)
{
    $query = Course::with(['languages', 'technologies', 'methodologies'])
        ->when($request->filled('name'), function ($q) use ($request) {
            $q->where('name', 'like', '%' . $request->name . '%');
        })
        ->when($request->filled('language_id'), function ($q) use ($request) {
            $q->whereHas('languages', function ($sub) use ($request) {
                $sub->where('languages.id', $request->language_id);
            });
        })
        ->when($request->filled('technology_id'), function ($q) use ($request) {
            $q->whereHas('technologies', function ($sub) use ($request) {
                $sub->where('technologies.id', $request->technology_id);
            });
        })
        ->when($request->filled('methodology_id'), function ($q) use ($request) {
            $q->whereHas('methodologies', function ($sub) use ($request) {
                $sub->where('methodologies.id', $request->methodology_id);
            });
        })
        ->orderBy('name', 'asc')
        ->paginate(10);

    // Si se pide JSON (por API/AJAX)
    if ($request->wantsJson()) {
        return response()->json([
            'courses' => $query,
        ]);
    }

    // Si se usa con Inertia
    return Inertia::render('courses/index', [
        'courses' => $query,
        'filters' => $request->only(['name', 'language_id', 'technology_id', 'methodology_id']),
    ]);
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
