<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Models\Scraping;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class BackupController extends Controller
{
    /**
     * Listar todos los backups de un scraping específico.
     */
public function index($scrapingId)
{
    $scraping = \App\Models\Scraping::findOrFail($scrapingId);

    $backups = $scraping->backups()
        ->orderBy('id', 'desc')
        ->paginate(10); // 👉 aquí ya devuelve paginado

    return inertia('Backups/Index', [
        'scraping' => $scraping,
        'backups'  => $backups,
    ]);
}




    /**
     * Guardar un nuevo backup manualmente desde el request.
     */
    public function store(Request $request, $scrapingId)
    {
        $request->validate([
            'row_id' => 'required|integer',
            'data'   => 'required|array',
        ]);

        $backup = Backup::create([
            'scraping_id' => $scrapingId,
            'row_id'      => $request->row_id,
            'data'        => $request->data,
            'reviewed'    => false,
        ]);

        return response()->json([
            'message' => '✅ Backup creado con éxito',
            'backup'  => $backup,
        ]);
    }

    /**
     * Marcar un backup como revisado o no.
     */
    public function toggleReviewed($id)
    {
        $backup = Backup::findOrFail($id);
        $backup->reviewed = !$backup->reviewed;
        $backup->save();

        return response()->json([
            'message' => '🔄 Estado de revisión actualizado',
            'backup'  => $backup,
        ]);
    }

    /**
     * Eliminar un backup.
     */
    public function destroy($id)
    {
        $backup = Backup::findOrFail($id);
        $backup->delete();

        return response()->json([
            'message' => '🗑️ Backup eliminado',
        ]);
    }

    /**
     * Exportar backups de un scraping a Excel.
     */
    public function export($scrapingId)
    {
        $scraping = Scraping::findOrFail($scrapingId);

        $backups = Backup::where('scraping_id', $scrapingId)->get();

        if ($backups->isEmpty()) {
            return back()->with('error', '⚠️ No hay datos para exportar');
        }

        $exportData = $backups->map(fn ($b) => array_merge(
            ['row_id' => $b->row_id, 'reviewed' => $b->reviewed],
            $b->data ?? []
        ));

        return Excel::download(new class($exportData) implements \Maatwebsite\Excel\Concerns\FromCollection {
            protected $data;
            public function __construct($data) { $this->data = $data; }
            public function collection() { return collect($this->data); }
        }, $scraping->name . '-backups.xlsx');
    }
    public function storeMany(Request $request, $scrapingId)
{
    $request->validate([
        'data' => 'required|array',
    ]);

    $inserted = [];

    foreach ($request->data as $row) {
        $backup = \App\Models\Backup::updateOrCreate(
            [
                'scraping_id' => $scrapingId,
                'row_id'      => $row['row_id'] ?? null,
            ],
            [
                'data'     => $row,
                'reviewed' => false,
            ]
        );

        $inserted[] = $backup;
    }

    return response()->json([
        'message' => '✅ Backups guardados correctamente',
        'count'   => count($inserted),
    ]);
}

}
