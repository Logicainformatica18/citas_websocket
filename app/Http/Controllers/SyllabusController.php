<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Syllabus;
use App\Jobs\ProcessSyllabusJob;
use Illuminate\Support\Facades\Log;
use App\Models\Technology;

class SyllabusController extends Controller
{
   public function index(Request $request)
{
    $search = $request->get('search');

    $query = Syllabus::query();

    if ($search) {
        $query->whereRaw(
            "LOWER(JSON_UNQUOTE(JSON_EXTRACT(structured_data, '$.curso'))) LIKE ?",
            ['%' . strtolower($search) . '%']
        );
    }

    $uploads = $query->latest()->paginate(10)->withQueryString();

        $uploads->getCollection()->transform(function ($syllabus) {
            $data = json_decode($syllabus->getRawOriginal('structured_data'), true) ?? [];

            // 🧩 Enriquecer tecnologías con su categoría real desde DB
            $tecnologias = collect($data['tecnologias'] ?? [])->map(function ($item) {
                $nombre = is_array($item)
                    ? ($item['nombre'] ?? $item['name'] ?? null)
                    : $item;

                if (!$nombre) return null;

                // Busca la tecnología con su categoría
                $tech = Technology::with('category')->where('name', $nombre)->first();

                return [
                    'nombre' => $nombre,
                    'tipo'   => $tech?->category?->name ?? 'Sin categoría',
                ];
            })
            ->filter(fn($t) => !empty($t['nombre']))
            ->values()
            ->toArray();

            $syllabus->structured_data = [
                'curso'        => $data['curso'] ?? null,
                'lenguajes'    => $data['lenguajes'] ?? [],
                'tecnologias'  => $tecnologias,
                'metodologias' => $data['metodologias'] ?? [],
            ];

            $syllabus->detected_course = $syllabus->structured_data['curso'];

            return $syllabus;
        });

        return inertia('syllabus/index', [
            'uploads' => $uploads,
        ]);
    }

   public function fetchPaginated(Request $request)
{
    $search = $request->get('search');

    $query = Syllabus::query();

    if ($search) {
        $query->whereRaw(
            "LOWER(JSON_UNQUOTE(JSON_EXTRACT(structured_data, '$.curso'))) LIKE ?",
            ['%' . strtolower($search) . '%']
        );
    }

    $uploads = $query->latest()->paginate(10)->withQueryString();

        $uploads->getCollection()->transform(function ($syllabus) {
            $data = json_decode($syllabus->getRawOriginal('structured_data'), true) ?? [];

            $tecnologias = collect($data['tecnologias'] ?? [])->map(function ($item) {
                $nombre = is_array($item)
                    ? ($item['nombre'] ?? $item['name'] ?? null)
                    : $item;

                if (!$nombre) return null;

                $tech = Technology::with('category')->where('name', $nombre)->first();

                return [
                    'nombre' => $nombre,
                    'tipo'   => $tech?->category?->name ?? 'Sin categoría',
                ];
            })
            ->filter(fn($t) => !empty($t['nombre']))
            ->values()
            ->toArray();

            $syllabus->structured_data = [
                'curso'        => $data['curso'] ?? null,
                'lenguajes'    => $data['lenguajes'] ?? [],
                'tecnologias'  => $tecnologias,
                'metodologias' => $data['metodologias'] ?? [],
            ];

            $syllabus->detected_course = $syllabus->structured_data['curso'];

            return $syllabus;
        });

        return response()->json($uploads);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:10240', // max 10MB
        ]);

        $path = $request->file('file')->store('syllabus', 'public');
        $filename = $request->file('file')->getClientOriginalName();

        $record = Syllabus::create([
            'filename' => $filename,
            'path'     => $path,
            'status'   => 'pending',
        ]);

        Log::info('📄 Sílabus recibido y encolado', [
            'filename' => $filename,
            'path'     => $path,
        ]);

        ProcessSyllabusJob::dispatch($record->id);

        return response()->json($record);
    }

    public function destroy($id)
    {
        $record = Syllabus::findOrFail($id);

        if ($record->path && \Storage::disk('public')->exists($record->path)) {
            \Storage::disk('public')->delete($record->path);
        }

        $record->delete();

        return response()->json(['message' => 'Eliminado']);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate(['ids' => 'required|array']);

        $records = Syllabus::whereIn('id', $request->ids)->get();

        foreach ($records as $record) {
            if ($record->path && \Storage::disk('public')->exists($record->path)) {
                \Storage::disk('public')->delete($record->path);
            }
            $record->delete();
        }

        return response()->json(['message' => 'Eliminados correctamente']);
    }

    public function show($id)
    {
        $record = Syllabus::findOrFail($id);
        return response()->json($record);
    }
}
