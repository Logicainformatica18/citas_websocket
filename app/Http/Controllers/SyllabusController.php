<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Syllabus;
use App\Jobs\ProcessSyllabusJob;
use Illuminate\Support\Facades\Log;

class SyllabusController extends Controller
{
public function index()
{
    $uploads = Syllabus::latest()->paginate(10);

    $uploads->getCollection()->transform(function ($syllabus) {
        $data = json_decode($syllabus->getRawOriginal('structured_data'), true) ?? [];

        $syllabus->structured_data = [
            'curso'        => $data['curso'] ?? null,
            'lenguajes'    => $data['lenguajes'] ?? [],
            'tecnologias'  => $data['tecnologias'] ?? [],
            'metodologias' => $data['metodologias'] ?? [],
        ];

        $syllabus->detected_course = $syllabus->structured_data['curso'];

        return $syllabus;
    });

    return inertia('syllabus/index', [
        'uploads' => $uploads,
    ]);
}

public function fetchPaginated()
{
    $uploads = Syllabus::latest()->paginate(10);

    $uploads->getCollection()->transform(function ($syllabus) {
        $data = json_decode($syllabus->getRawOriginal('structured_data'), true) ?? [];

        $syllabus->structured_data = [
            'curso'        => $data['curso'] ?? null,
            'lenguajes'    => $data['lenguajes'] ?? [],
            'tecnologias'  => $data['tecnologias'] ?? [],
            'metodologias' => $data['metodologias'] ?? [],
        ];

        $syllabus->detected_course = $syllabus->structured_data['curso'];

        return $syllabus;
    });

    return $uploads;
}







    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:10240', // max 10MB
        ]);

        // 📂 Guardar en "public/syllabus"
        $path = $request->file('file')->store('syllabus', 'public');
        $filename = $request->file('file')->getClientOriginalName();

        $record = Syllabus::create([
            'filename' => $filename,
            'path'     => $path, // ejemplo: syllabus/abc123.pdf
            'status'   => 'pending',
        ]);

        Log::info('📄 Sílabus recibido y encolado', [
            'filename' => $filename,
            'path'     => $path,
        ]);

        // Mandar a cola
        ProcessSyllabusJob::dispatch($record->id);

        // ⚡ Devolvemos el registro directamente
        return response()->json($record);
    }

    public function destroy($id)
    {
        $record = Syllabus::findOrFail($id);

        // Opcional: eliminar también el archivo físico
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
