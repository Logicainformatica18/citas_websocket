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

        // RUTA SEGÚN BD
        $dbPath = $part->file_path; // <- ESTA SÍ EXISTE

        Log::info("🚀 [UploadToGCS] Iniciando upload", [
            'part_id' => $part->id,
            'db_path' => $dbPath,
        ]);

        // Ruta real en storage
        $localPath = storage_path("app/public/" . $dbPath);

        if (!file_exists($localPath)) {
            Log::error("❌ Archivo local no encontrado", [
                "expected_path" => $localPath,
                "db_path" => $dbPath
            ]);
            throw new \Exception("Archivo local no encontrado: $localPath");
        }

        Log::info("📄 Archivo encontrado", [
            'localPath' => $localPath,
            'size_MB' => round(filesize($localPath) / 1024 / 1024, 2)
        ]);

        // Cliente GCS
        $storage = new StorageClient([
            'projectId'   => env('GCS_PROJECT_ID'),
            'keyFilePath' => env('GCS_KEY_FILE_PATH'),
        ]);

        $bucket = $storage->bucket(env('GCS_BUCKET'));

        // Nombre final
        $gcsName = "pdf_parts/{$part->id}.pdf";

        // Subida
        try {
            $bucket->upload(
                fopen($localPath, 'r'),
                ['name' => $gcsName]
            );
        } catch (\Exception $e) {
            Log::error("❌ Error subiendo a GCS", [
                'error' => $e->getMessage(),
                'localPath' => $localPath,
                'gcsName' => $gcsName
            ]);
            throw $e;
        }

        // Guardar URL final
        $gcsUri = "gs://" . env('GCS_BUCKET') . "/" . $gcsName;

        $part->update([
            'gcs_path' => $gcsUri,
        ]);

        Log::info("✅ PDF subido correctamente", [
            'part_id' => $part->id,
            'gcs_uri' => $gcsUri,
        ]);
    }
}
