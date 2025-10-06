<?php

namespace App\Http\Controllers;

use App\Models\Career;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Str;

class CareerController extends Controller
{
    /**
     * 📄 Listado general (para Inertia)
     */
    public function index()
    {
        $careers = Career::withCount('courses')
            ->orderBy('id', 'desc')
            ->paginate(10);

        return Inertia::render('careers/index', [
            'careers' => $careers->through(fn ($c) => [
                'id'            => $c->id,
                'name'          => $c->name,
                'faculty'       => $c->faculty,
                'degree_title'  => $c->degree_title,
                'duration_years'=> $c->duration_years,
                'active'        => $c->active,
                'courses_count' => $c->courses_count,
                'created_at'    => optional($c->created_at)->format('Y-m-d'),
            ]),
        ]);
    }

    /**
     * 📄 API JSON paginada
     */
    public function fetchPaginated()
    {
        $careers = Career::withCount('courses')
            ->orderBy('id', 'desc')
            ->paginate(10);

        $formatted = $careers->through(fn ($c) => [
            'id'            => $c->id,
            'name'          => $c->name,
            'faculty'       => $c->faculty,
            'degree_title'  => $c->degree_title,
            'duration_years'=> $c->duration_years,
            'active'        => $c->active,
            'courses_count' => $c->courses_count,
            'created_at'    => optional($c->created_at)->format('Y-m-d'),
        ]);

        return response()->json($formatted);
    }

    /**
     * 📥 Crear nueva carrera
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'detail'         => 'nullable|string',
            'faculty'        => 'nullable|string|max:255',
            'degree_title'   => 'nullable|string|max:255',
            'duration_years' => 'nullable|integer|min:1|max:10',
            'active'         => 'boolean',
        ]);

        return DB::transaction(function () use ($validated) {
            $validated['slug'] = Str::slug($validated['name']);
            $career = Career::create($validated);

            return response()->json([
                'message' => '✅ Career created successfully',
                'career'  => $career,
            ], 201);
        });
    }

    /**
     * 📄 Mostrar detalle con cursos asociados
     */
    public function show($id)
    {
        $career = Career::with('courses')->findOrFail($id);

        return response()->json([
            'career'  => $career,
            'courses' => $career->courses->map(fn ($c) => [
                'id'           => $c->id,
                'name'         => $c->name,
                'semester'     => $c->pivot->semester,
                'is_mandatory' => $c->pivot->is_mandatory,
            ]),
        ]);
    }

    /**
     * ✏️ Actualizar una carrera
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'detail'         => 'nullable|string',
            'faculty'        => 'nullable|string|max:255',
            'degree_title'   => 'nullable|string|max:255',
            'duration_years' => 'nullable|integer|min:1|max:10',
            'active'         => 'boolean',
        ]);

        return DB::transaction(function () use ($validated, $id) {
            $career = Career::findOrFail($id);
            $career->update(array_merge($validated, [
                'slug' => Str::slug($validated['name']),
            ]));

            return response()->json([
                'message' => '✅ Career updated successfully',
                'career'  => $career,
            ]);
        });
    }

    /**
     * 🗑️ Eliminar una carrera
     */
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $career = Career::findOrFail($id);
            $career->delete();

            return response()->json(['message' => 'Career deleted successfully']);
        });
    }

    /**
     * 🔗 Asociar un solo curso a la carrera
     */
    public function attachCourse(Request $request, $careerId)
    {
        $validated = $request->validate([
            'course_id'    => 'required|exists:courses,id',
            'semester'     => 'nullable|string|max:50',
            'is_mandatory' => 'boolean',
        ]);

        $career = Career::findOrFail($careerId);
        $career->courses()->syncWithoutDetaching([
            $validated['course_id'] => [
                'semester'     => $validated['semester'] ?? null,
                'is_mandatory' => $validated['is_mandatory'] ?? true,
            ]
        ]);

        return response()->json(['message' => '✅ Course attached successfully']);
    }

    /**
     * 🔄 Sincronizar todos los cursos seleccionados (desde el modal)
     */
    public function syncCourses(Request $request, $careerId)
    {
        $validated = $request->validate([
            'courses'               => 'required|array|min:1',
            'courses.*.id'          => 'required|exists:courses,id',
            'courses.*.semester'    => 'nullable|string|max:50',
            'courses.*.is_mandatory'=> 'boolean',
        ]);

        $career = Career::findOrFail($careerId);

        $syncData = collect($validated['courses'])->mapWithKeys(function ($course) {
            return [
                $course['id'] => [
                    'semester'     => $course['semester'] ?? null,
                    'is_mandatory' => $course['is_mandatory'] ?? true,
                ]
            ];
        })->toArray();

        $career->courses()->sync($syncData);

        return response()->json([
            'message' => '✅ Courses synchronized successfully',
            'synced'  => count($syncData),
        ]);
    }

    /**
     * ❌ Desasociar un curso de la carrera
     */
    public function detachCourse($careerId, $courseId)
    {
        $career = Career::findOrFail($careerId);
        $career->courses()->detach($courseId);

        return response()->json(['message' => 'Course detached successfully']);
    }
}
