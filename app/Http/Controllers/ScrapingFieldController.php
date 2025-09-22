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

        // Traer campos con padre e hijos
        $fields = ScrapingField::with(['children', 'parent'])
            ->where('scraping_id', $scrapingId)
            ->orderBy('id', 'asc')
            ->paginate(10);

        if ($request->wantsJson()) {
            return response()->json([
                'scraping' => $scraping,
                'fields'   => $fields,
            ]);
        }

        return Inertia::render('ScrapingFields/Index', [
            'scraping' => $scraping,
            'fields'   => $fields,
        ]);
    }

    public function fetchPaginated($scrapingId)
    {
        $fields = ScrapingField::with(['children', 'parent'])
            ->where('scraping_id', $scrapingId)
            ->whereNull('parent_id')
            ->latest()
            ->paginate(10);

        return response()->json(['fields' => $fields]);
    }

    public function store(Request $request, $scrapingId)
    {
        $request->validate([
            'field_name'     => 'required|string|max:255',
            'selector_type'  => 'required|string|in:id,class,tag,attribute,text,css',
            'selector_value' => 'required|string|max:255',
            'attr'           => 'nullable|string|max:50', // 👈 nuevo
            'path'           => 'nullable|string|max:255',
            'parent_id'      => 'nullable|exists:scraping_fields,id',
        ]);

        $field = new ScrapingField();
        $field->scraping_id    = $scrapingId;
        $field->field_name     = $request->field_name;
        $field->selector_type  = $request->selector_type;
        $field->selector_value = $request->selector_value;
        $field->attr           = $request->attr; // 👈 nuevo
        $field->path           = $request->path;
        $field->parent_id      = $request->parent_id;
        $field->save();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => '✅ Campo creado',
                'field'   => $field->load('children'),
            ]);
        }

        return redirect()->back()->with('success', '✅ Campo creado');
    }

    public function show($scrapingId, $id)
    {
        $field = ScrapingField::with('children')
            ->where('scraping_id', $scrapingId)
            ->findOrFail($id);

        return response()->json(['field' => $field]);
    }

    public function update(Request $request, $scrapingId, $id)
    {
        $field = ScrapingField::where('scraping_id', $scrapingId)
            ->findOrFail($id);

        $request->validate([
            'field_name'     => 'required|string|max:255',
            'selector_type'  => 'required|string|in:id,class,tag,attribute,text,css',
            'selector_value' => 'required|string|max:255',
            'attr'           => 'nullable|string|max:50', // 👈 nuevo
            'path'           => 'nullable|string|max:255',
            'parent_id'      => 'nullable|exists:scraping_fields,id',
        ]);

        $field->field_name     = $request->field_name;
        $field->selector_type  = $request->selector_type;
        $field->selector_value = $request->selector_value;
        $field->attr           = $request->attr; // 👈 nuevo
        $field->path           = $request->path;
        $field->parent_id      = $request->parent_id;
        $field->save();

        return response()->json([
            'message' => '✅ Campo actualizado',
            'field'   => $field->load('children'),
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
