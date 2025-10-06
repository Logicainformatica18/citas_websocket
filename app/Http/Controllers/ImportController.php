<?php
namespace App\Http\Controllers;

use App\Models\ImportJob;
use App\Models\ImportMapping;
use App\Models\JobOffer;
use Illuminate\Http\Request;
use League\Csv\Reader;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    public function index()
    {
        $imports = ImportJob::latest()->get();
        return inertia('Imports/Index', compact('imports'));
    }

    public function upload(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);

        $path = $request->file('file')->store('imports');
        $csv = Reader::createFromPath(Storage::path($path), 'r');
        $csv->setHeaderOffset(0);
        $headers = $csv->getHeader();

        $job = ImportJob::create([
            'filename' => basename($path),
            'columns_detected' => $headers,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Archivo cargado correctamente');
    }

    public function map(ImportJob $job)
    {
        $detected = $job->columns_detected;
        $fields = ['title', 'company', 'city', 'country', 'modality', 'date', 'url', 'latitude', 'longitude'];
        return inertia('Imports/Map', compact('job', 'detected', 'fields'));
    }

    public function saveMapping(Request $request, ImportJob $job)
    {
        $request->validate(['mapping' => 'required|array']);
        ImportMapping::updateOrCreate(
            ['import_job_id' => $job->id],
            ['mapping' => $request->mapping]
        );
        $job->update(['status' => 'mapped']);
        return back()->with('success', 'Mapeo guardado correctamente');
    }

    public function process(ImportJob $job)
    {
        $mapping = $job->mapping?->mapping;
        if (!$mapping) return back()->with('error', 'Debes definir un mapeo primero');

        $path = Storage::path("imports/{$job->filename}");
        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);
        $records = $csv->getRecords();

        $count = 0;
        foreach ($records as $row) {
            $data = [];
            foreach ($mapping as $target => $source) {
                $data[$target] = $row[$source] ?? null;
            }

            JobOffer::updateOrCreate(
                ['url' => $data['url'] ?? null],
                [
                    'title' => $data['title'] ?? null,
                    'company' => $data['company'] ?? null,
                    'city' => $data['city'] ?? null,
                    'country' => $data['country'] ?? null,
                    'modality' => $data['modality'] ?? null,
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                    'published_at' => !empty($data['date']) ? Carbon::parse($data['date']) : now(),
                    'source' => $job->source,
                ]
            );
            $count++;
        }

        $job->update(['status' => 'processed']);
        return back()->with('success', "{$count} ofertas importadas correctamente");
    }
}
