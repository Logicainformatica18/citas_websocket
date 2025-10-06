<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class JobOfferImportController extends Controller
{
    /**
     * 🔹 Subir CSV y obtener columnas
     */
 public function upload(Request $request)
{
    try {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);

        // 📁 Ruta donde se guardarán los CSV
        $dir = storage_path('app/private/imports');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $filename = 'computrabajo_' . time() . '.csv';
        $path = "{$dir}/{$filename}";

        // 📥 Mover manualmente el archivo
        $request->file('file')->move($dir, $filename);

        if (!file_exists($path)) {
            Log::error("❌ El archivo no se guardó correctamente en: {$path}");
            return response()->json(['error' => 'Archivo no encontrado tras guardar.'], 500);
        }

        Log::info("📥 Archivo subido en: {$path}");

        // Leer encabezados del CSV
        $csv = \League\Csv\Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);
        $headers = $csv->getHeader();

        return response()->json([
            'columns' => $headers,
            'path' => $path, // 👈 esta ruta real se enviará al paso de importación
        ]);
    } catch (\Throwable $e) {
        Log::error("❌ Error al subir CSV: {$e->getMessage()}");
        return response()->json(['error' => 'Error al procesar el CSV'], 500);
    }
}


    /**
     * 🔹 Procesar el CSV mediante LOAD DATA INFILE (rápido y fiable)
     */
 public function process(Request $request)
{
    try {
        $request->validate([
            'path' => 'required|string',
            'source' => 'nullable|string',
        ]);

        $source = $request->input('source', 'Computrabajo');
        $path = $request->input('path');

        // ⚙️ Copiar CSV a carpeta de datos de MariaDB
        $dbDataDir = 'C:/Program Files/MariaDB 12.0/data/';
        if (!is_dir($dbDataDir)) {
            mkdir($dbDataDir, 0777, true);
        }

        $finalCsv = $dbDataDir . basename($path);
        copy($path, $finalCsv);

        Log::info("📦 CSV copiado a directorio de datos: {$finalCsv}");

        // 🚀 1️⃣ Crear tabla temporal
        DB::statement("
            CREATE TEMPORARY TABLE tmp_job_offers LIKE job_offers;
        ");

        // 🚀 2️⃣ Cargar datos del CSV a la tabla temporal
        $sqlLoad = "
            LOAD DATA INFILE '{$finalCsv}'
            INTO TABLE tmp_job_offers
            CHARACTER SET utf8mb4
            FIELDS TERMINATED BY ','
            ENCLOSED BY '\"'
            LINES TERMINATED BY '\\n'
            IGNORE 1 ROWS
            (
              title,
              company,
              city,
              country,
              modality,
              @date,
              url,
              @latitude,
              @longitude
            )
            SET
              latitude     = NULLIF(@latitude, ''),
              longitude    = NULLIF(@longitude, ''),
              published_at = STR_TO_DATE(@date, '%Y-%m-%d %H:%i:%s'),
              source       = '{$source}',
              created_at   = NOW(),
              updated_at   = NOW();
        ";
        DB::unprepared($sqlLoad);

        Log::info("📥 Datos cargados temporalmente desde CSV.");

        // 🚀 3️⃣ Insertar o actualizar según URL
        $sqlMerge = "
            INSERT INTO job_offers (
                title, company, city, country, modality,
                latitude, longitude, published_at, source, url,
                created_at, updated_at
            )
            SELECT
                title, company, city, country, modality,
                latitude, longitude, published_at, source, url,
                NOW(), NOW()
            FROM tmp_job_offers
            ON DUPLICATE KEY UPDATE
                title        = VALUES(title),
                company      = VALUES(company),
                city         = VALUES(city),
                country      = VALUES(country),
                modality     = VALUES(modality),
                latitude     = VALUES(latitude),
                longitude    = VALUES(longitude),
                published_at = VALUES(published_at),
                source       = VALUES(source),
                updated_at   = NOW();
        ";
        DB::unprepared($sqlMerge);

        Log::info("✅ Importación completada con merge por URL.");

        return response()->json([
            'success' => true,
            'message' => "Importación completada y actualizada correctamente por URL.",
        ]);

    } catch (\Throwable $e) {
        Log::error("💥 Error al importar CSV con LOAD DATA INFILE: {$e->getMessage()}", [
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json(['error' => 'Error durante la importación.'], 500);
    }
}
}
