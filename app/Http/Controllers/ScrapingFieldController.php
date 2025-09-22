<?php

namespace App\Http\Controllers;

use App\Models\ScrapingField;
use App\Models\Scraping;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ScrapingFieldController extends Controller
{
    public function index(Request $request, $scrapingId)
    {
        $scraping = Scraping::findOrFail($scrapingId);
        $fields = ScrapingField::where('scraping_id', $scrapingId)
            ->orderBy('id', 'desc')
            ->paginate(10);

        if ($request->wantsJson()) {
            return response()->json([
                'scraping' => $scraping,
                'fields' => $fields,
            ]);
        }

        return Inertia::render('ScrapingFields/Index', [
            'scraping' => $scraping,
            'fields' => $fields,
        ]);
    }

    public function fetchPaginated($scrapingId)
    {
        $fields = ScrapingField::where('scraping_id', $scrapingId)
            ->latest()
            ->paginate(10);

        return response()->json(['fields' => $fields]);
    }

    public function store(Request $request, $scrapingId)
    {
        $request->validate([
            'field_name' => 'required|string|max:255',
            'selector'   => 'required|string|max:255',
            'path'       => 'nullable|string|max:255',
        ]);

        $field = new ScrapingField();
        $field->scraping_id = $scrapingId;
        $field->fill($request->only(['field_name', 'selector', 'path']));
        $field->save();

       if ($request->wantsJson()) {
    return response()->json([
        'message' => '✅ Campo creado',
        'field' => $field,
    ]);
}

return redirect()->back()->with('success', '✅ Campo creado');

    }

    public function show($scrapingId, $id)
    {
        $field = ScrapingField::where('scraping_id', $scrapingId)
            ->findOrFail($id);

        return response()->json(['field' => $field]);
    }

    public function update(Request $request, $scrapingId, $id)
    {
        $field = ScrapingField::where('scraping_id', $scrapingId)
            ->findOrFail($id);

        $request->validate([
            'field_name' => 'required|string|max:255',
            'selector'   => 'required|string|max:255',
            'path'       => 'nullable|string|max:255',
        ]);

        $field->fill($request->only(['field_name', 'selector', 'path']));
        $field->save();

        return response()->json([
            'message' => '✅ Campo actualizado',
            'field' => $field,
        ]);
    }

    public function destroy($scrapingId, $id)
    {
        $field = ScrapingField::where('scraping_id', $scrapingId)
            ->findOrFail($id);

        $field->delete();

        return response()->json(['message' => '✅ Campo eliminado']);
    }
}
