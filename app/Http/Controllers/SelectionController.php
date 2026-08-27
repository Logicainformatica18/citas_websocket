<?php

namespace App\Http\Controllers;

use App\Models\Selection;
use Inertia\Inertia;
use Illuminate\Http\Request;

class SelectionController extends Controller
{
    public function index(Request $request)
    {
        $selections = Selection::with('associate')->orderBy('id', 'desc')->paginate(10);
        $allSelections = Selection::orderBy('id', 'asc')->get();

        if ($request->wantsJson()) {
            return response()->json([
                'selections' => $selections,
                'allSelections' => $allSelections,
            ]);
        }

        return Inertia::render('selections/index', [
            'selections' => $selections,
            'allSelections' => $allSelections,
        ]);
    }

    public function fetchPaginated()
    {
        $selections = Selection::with('associate')->orderBy('id', 'desc')->paginate(10);
        $allSelections = Selection::orderBy('id', 'asc')->get();

        return response()->json([
            'selections' => $selections,
            'allSelections' => $allSelections,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'detail'      => 'nullable|string|max:255',
            'state'       => 'nullable|string|max:255',
            'associate_id' => 'nullable|integer|exists:selections,id',
        ]);

        $selection = new Selection();

        $selection->description = $request->description;
        $selection->detail      = $request->detail;
        $selection->state       = $request->state;
        $selection->associate_id = $request->associate_id;

        $selection->save();

        return response()->json([
            'message' => 'Selección creada',
            'selection' => $selection,
        ]);
    }

    public function show($id)
    {
        $selection = Selection::with('associate')->findOrFail($id);

        return response()->json([
            'selection' => $selection,
        ]);
    }

    public function update(Request $request, $id)
    {
        $selection = Selection::findOrFail($id);

        $request->validate([
            'description' => 'required|string|max:255',
            'detail'      => 'nullable|string|max:255',
            'state'       => 'nullable|string|max:255',
            'associate_id' => 'nullable|integer|exists:selections,id|different:id',
        ]);

        $selection->description = $request->description;
        $selection->detail      = $request->detail;
        $selection->state       = $request->state;
        $selection->associate_id = $request->associate_id;

        $selection->save();

        return response()->json([
            'message' => 'Selección actualizada',
            'selection' => $selection,
        ]);
    }

    public function destroy($id)
    {
        $selection = Selection::findOrFail($id);

        try {
            $selection->delete();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se puede eliminar la selección porque está en uso por otra fila.',
            ], 409);
        }

        return response()->json([
            'message' => 'Selección eliminada',
        ]);
    }
}
