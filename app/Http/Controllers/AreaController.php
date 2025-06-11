<?php

namespace App\Http\Controllers;

use App\Models\Area;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class AreaController extends Controller
{
    public function index()
    {
        $areas = Area::orderBy('id_area', 'desc')->paginate(7);
        return Inertia::render('areas/index', [
            'areas' => $areas,
        ]);
    }

    public function fetchPaginated()
    {
        return response()->json(Area::orderBy('id_area', 'desc')->paginate(7));
    }

    public function store(Request $request)
    {
        $this->validateArea($request);

        $area = new Area();
        $area->fill($request->only('descripcion', 'habilitado'));
        $area->save();

        return response()->json([
            'message' => '✅ Área creada correctamente',
            'area' => $area,
        ]);
    }

    public function update(Request $request, $id)
    {
        $area = Area::findOrFail($id);
        $this->validateArea($request);

        $area->fill($request->only('descripcion', 'habilitado'));
        $area->save();

        return response()->json([
            'message' => '✅ Área actualizada correctamente',
            'area' => $area,
        ]);
    }

    public function show($id)
    {
        $area = Area::findOrFail($id);
        return response()->json(['area' => $area]);
    }

    public function destroy($id)
    {
        Area::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        Area::whereIn('id_area', $ids)->delete();

        return response()->json(['message' => 'Eliminadas correctamente']);
    }

    private function validateArea(Request $request)
    {
        $request->validate([
            'descripcion' => 'required|string|max:50',
            'habilitado' => 'required|boolean',
        ]);
    }
    public function getAllEnabled()
    {
        $areas = Area::whereNotIn('descripcion', [
            'Cobranza',
            'BackOffice',
            'SuperAdmin',
        ])->get(['id_area as id', 'descripcion']);

        return response()->json($areas);
    }

}



