<?php

namespace App\Http\Controllers;

use App\Models\Selection;
use App\Models\SelectionDetail;
use Illuminate\Http\Request;

class SelectionDetailController extends Controller
{
    public function fetchPaginated($selectionId)
    {
        $selection = Selection::findOrFail($selectionId);

        $details = SelectionDetail::where('selection_id', $selection->id)
            ->with('associateDetail')
            ->orderBy('id', 'desc')
            ->paginate(10);

        return response()->json([
            'selection' => $selection,
            'details' => $details,
        ]);
    }

    public function store(Request $request, $selectionId)
    {
        $selection = Selection::findOrFail($selectionId);

        $request->validate([
            'description' => 'required|string|max:255',
            'detail'      => 'nullable|string|max:255',
            'associate_detail_id' => 'nullable|integer|exists:selection_details,id',
        ]);

        $detail = new SelectionDetail();

        $detail->description = $request->description;
        $detail->detail = $request->detail;
        $detail->selection_id = $selection->id;
        $detail->associate_detail_id = $request->associate_detail_id;

        $detail->save();

        return response()->json([
            'message' => 'Detalle de selección creado',
            'detail' => $detail,
        ]);
    }

    public function update(Request $request, $id)
    {
        $detail = SelectionDetail::findOrFail($id);

        $request->validate([
            'description' => 'required|string|max:255',
            'detail'      => 'nullable|string|max:255',
            'associate_detail_id' => 'nullable|integer|exists:selection_details,id|different:id',
        ]);

        $detail->description = $request->description;
        $detail->detail = $request->detail;
        $detail->associate_detail_id = $request->associate_detail_id;

        $detail->save();

        return response()->json([
            'message' => 'Detalle de selección actualizado',
            'detail' => $detail,
        ]);
    }

    public function destroy($id)
    {
        $detail = SelectionDetail::findOrFail($id);

        try {
            $detail->delete();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se puede eliminar el detalle porque está en uso por otra fila.',
            ], 409);
        }

        return response()->json([
            'message' => 'Detalle de selección eliminado',
        ]);
    }
}
