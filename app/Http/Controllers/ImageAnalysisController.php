<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ImageAnalysis;
use App\Jobs\AnalyzeImageJob;
use Illuminate\Support\Facades\Log;
    use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;

use Illuminate\Support\Facades\Storage;


class ImageAnalysisController extends Controller
{


public function analyze(Request $request)
{
    $request->validate([
        'image' => 'required|image',
    ]);

    // Guardar la imagen temporalmente
    $path = $request->file('image')->store('temp');

    // Cargar la imagen
    $image = file_get_contents(storage_path('app/' . $path));

    // Inicializar cliente de Vision
    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . storage_path('app/google-credentials.json'));
    $client = new ImageAnnotatorClient();

    // Llamar a la API
    $response = $client->documentTextDetection($image);
    $text = $response->getFullTextAnnotation()->getText();

    $client->close();

    // Eliminar imagen temporal
    Storage::delete($path);

    // Extraer info específica (ej. DNI, monto, etc.)
    $dni = null;
    $monto = null;
    if (preg_match('/DNI[:\s]+(\d{8})/', $text, $matches)) {
        $dni = $matches[1];
    }
    if (preg_match('/S\/\s*([0-9,.]+)/', $text, $matches)) {
        $monto = $matches[1];
    }

    return response()->json([
        'texto_completo' => $text,
        'dni' => $dni,
        'monto' => $monto,
    ]);
}

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
