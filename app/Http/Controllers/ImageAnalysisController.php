<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ImageAnalysis;
use App\Jobs\AnalyzeImageJob;
use Illuminate\Support\Facades\Log;
class ImageAnalysisController extends Controller
{
    public function filenames()
    {
        return ImageAnalysis::pluck('filename');
    }



public function analyzeImages(Request $request)
{
    Log::info('📥 Solicitud recibida en /analyze-images');
    Log::info('📦 Archivos recibidos:', [
        'cantidad' => count($request->file('images') ?? []),
        'nombres' => collect($request->file('images'))->pluck('name')->toArray(),
    ]);

    // Validación mínima
    $request->validate([
        'images' => 'required|array',
        'images.*' => 'required|file|max:4096',
    ]);

    foreach ($request->file('images') as $image) {
        $path = $image->store('image_analyses');
        $mime = $image->getMimeType();
        $filename = $image->getClientOriginalName();

        Log::info('📝 Imagen almacenada y encolada para análisis:', [
            'filename' => $filename,
            'mime' => $mime,
            'path' => $path,
        ]);

        AnalyzeImageJob::dispatch($path, $filename, $mime);
    }

    Log::info('✅ Todas las imágenes fueron encoladas correctamente.');
    return response()->json(['message' => 'Imágenes enviadas para análisis. Verifica más tarde.']);
}


    public function index()
    {
        return inertia('bot/index', [
            'analyses' => ImageAnalysis::latest()->paginate(10),
        ]);
    }

    public function fetchPaginated()
    {
        return ImageAnalysis::latest()->paginate(10);
    }

 

public function store(Request $request)
{
    Log::info('📥 Solicitud recibida en /analyses');
    Log::info('📄 Datos recibidos:', [
        'items' => $request->items ?? null,
    ]);

    // Validación básica
    $request->validate([
        'items' => 'required|array',
        'items.*.filename' => 'required|string',
        'items.*.response' => 'required|string',
    ]);

    $guardados = [];

    foreach ($request->items as $item) {
        $analysis = ImageAnalysis::create([
            'filename' => $item['filename'],
            'response' => $item['response'],
        ]);
        $guardados[] = $analysis->id;

        Log::info('✅ Registro guardado:', [
            'id' => $analysis->id,
            'filename' => $analysis->filename,
        ]);
    }

    Log::info('📦 Todos los registros fueron guardados correctamente:', [
        'ids' => $guardados,
    ]);

    return response()->json(['message' => 'Guardado exitosamente']);
}


    public function destroy($id)
    {
        $record = ImageAnalysis::findOrFail($id);
        $record->delete();

        return response()->json(['message' => 'Eliminado']);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate(['ids' => 'required|array']);
        ImageAnalysis::whereIn('id', $request->ids)->delete();

        return response()->json(['message' => 'Eliminados correctamente']);
    }
}
