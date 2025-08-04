<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ImageAnalysis;
use App\Jobs\AnalyzeImageJob;
use Illuminate\Support\Facades\Storage;

class ImageAnalysisController extends Controller
{
    public function filenames()
{
    return ImageAnalysis::pluck('filename');
}

    public function analyzeImages(Request $request)
    {
        $request->validate([
            'images' => 'required|array',
            'images.*' => 'required|image|max:4096',
        ]);

        foreach ($request->file('images') as $image) {
            $path = $image->store('image_analyses'); // Se almacena en storage/app/image_analyses
            $mime = $image->getMimeType();
            $filename = $image->getClientOriginalName();

            // Despachamos un job en cola
            AnalyzeImageJob::dispatch($path, $filename, $mime);
        }

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
        $request->validate([
            'items' => 'required|array',
            'items.*.filename' => 'required|string',
            'items.*.response' => 'required|string',
        ]);

        foreach ($request->items as $item) {
            ImageAnalysis::create([
                'filename' => $item['filename'],
                'response' => $item['response'],
            ]);
        }

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
