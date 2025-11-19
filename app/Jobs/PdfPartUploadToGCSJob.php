<?php

namespace App\Jobs;

use App\Models\PdfDocumentPart;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class PdfPartUploadToGCSJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $partId;

    public function __construct(int $partId)
    {
        $this->partId = $partId;
    }

    public function handle()
    {
        $part = PdfDocumentPart::findOrFail($this->partId);

        Log::info("🚀 [UploadToGCS] Iniciando upload", [
            'part_id' => $part->id,
            'db_path' => $part->file_path,
        ]);

        // 🔥 Construcción correcta de archivo en Herd + Windows
        $localPath = public_path('storage/' . ltrim($part->file_path, '/'));

        // 🔎 Validación
        if (!file_exists($localPath)) {
            Log::error("❌ [UploadToGCS] Archivo NO existe", [
                'expected_path' => $localPath,
                'db_path' => $part->file_path,
            ]);

            // Evita que la cadena completa falle
            throw new \Exception("Archivo local no encontrado: " . $localPath);
        }

        Log::info("📄 [UploadToGCS] Archivo encontrado", [
            'localPath' => $localPath,
            'size' => filesize($localPath),
        ]);

        // ☁️ Cliente GCS
        $storage = new StorageClient([
            'projectId'   => env('GCS_PROJECT_ID'),
            'keyFilePath' => env('GCS_KEY_FILE_PATH'),
        ]);

        $bucket = $storage->bucket(env('GCS_BUCKET'));

        // Nombre final en GCS
        $gcsName = "pdf_parts/{$part->id}.pdf";

        // 🚀 Subida real
        try {
            $bucket->upload(
                fopen($localPath, 'r'),
                ['name' => $gcsName]
            );
        } catch (\Exception $e) {
            Log::error("❌ [UploadToGCS] Error subiendo a GCS", [
                'error' => $e->getMessage(),
                'localPath' => $localPath,
                'gcsName' => $gcsName
            ]);

            throw $e; // permite que Laravel reintente
        }

        // URI final
        $gcsUri = "gs://" . env('GCS_BUCKET') . "/" . $gcsName;

        $part->update([
            'gcs_path' => $gcsUri,
        ]);

        Log::info("✅ [UploadToGCS] PDF subido correctamente", [
            'part_id' => $part->id,
            'gcs_uri' => $gcsUri,
        ]);
    }
}
