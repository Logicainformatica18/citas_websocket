<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ScrapingWebResult;
use App\Models\ScrapingSource;

class ScrapingWebResultController extends Controller
{
    /**
     * 📌 Listar resultados web por fuente (Inertia)
     */
    public function index($sourceId)
    {
        $source = ScrapingSource::findOrFail($sourceId);

        $results = ScrapingWebResult::where('source_id', $sourceId)
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return inertia('Scraping/WebResults/Index', [
            'source'  => $source,
            'results' => $results,
        ]);
    }

    /**
     * 📌 Mostrar un resultado individual (Inertia)
     */
    public function show($resultId)
    {
        $result = ScrapingWebResult::findOrFail($resultId);

        return inertia('Scraping/WebResults/Show', [
            'result' => $result,
        ]);
    }
}
