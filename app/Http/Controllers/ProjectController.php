<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectController extends Controller
{
public function index(Request $request)
{
    if ($request->wantsJson()) {
        // 👉 API: devolver todos los proyectos (sin paginar)
        $projects = Project::orderByDesc('id_proyecto')
            ->get()
            ->map(function ($project) {
                return [
                    'id_proyecto' => $project->id_proyecto,
                    'descripcion' => $project->descripcion,
                    'habilitado' => (bool) $project->habilitado,
                ];
            });

        return response()->json($projects);
    }

    // 👉 Vista Inertia: paginado con through
    $projects = Project::orderByDesc('id_proyecto')
        ->paginate(10)
        ->through(function ($project) {
            return [
                'id_proyecto' => $project->id_proyecto,
                'descripcion' => $project->descripcion,
                'habilitado' => (bool) $project->habilitado,
            ];
        });

    return Inertia::render('projects/index', [
        'projects' => $projects,
    ]);
}


    public function fetchPaginated()
    {
        return response()->json(
            Project::orderByDesc('id_proyecto')->paginate(10)->through(function ($project) {
                return [
                    'id_proyecto' => $project->id_proyecto,
                    'descripcion' => $project->descripcion,
                    'habilitado' => (bool) $project->habilitado,
                ];
            })
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'descripcion' => 'required|string|max:200',
            'habilitado' => 'required|boolean',
        ]);

        $project = Project::create($validated);

        return response()->json(['message' => '✅ Project created', 'project' => $project]);
    }

    public function update(Request $request, $id)
    {
        $project = Project::where('id_proyecto', $id)->firstOrFail();

        $validated = $request->validate([
            'descripcion' => 'required|string|max:200',
            'habilitado' => 'required|boolean',
        ]);

        $project->update($validated);

        return response()->json(['message' => '✅ Updated', 'project' => $project]);
    }

    public function show($id)
    {
        $project = Project::where('id_proyecto', $id)->firstOrFail();

        return response()->json([
            'project' => [
                'id_proyecto' => $project->id_proyecto,
                'descripcion' => $project->descripcion,
                'habilitado' => (bool) $project->habilitado,
            ],
        ]);
    }

    public function destroy($id)
    {
        $project = Project::where('id_proyecto', $id)->firstOrFail();
        $project->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        Project::whereIn('id_proyecto', $ids)->delete();

        return response()->json(['message' => '✅ Deleted']);
    }
}
