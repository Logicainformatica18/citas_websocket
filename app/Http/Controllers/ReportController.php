<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Support;
class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
public function show($id)
{
    $support = Support::with([
        'client',
        'creator:id,names',
        'details.area:id_area,descripcion',
        'details.project:id_proyecto,descripcion',
        'details.motivoCita:id_motivos_cita,nombre_motivo',
        'details.tipoCita:id_tipo_cita,tipo',
        'details.diaEspera:id_dias_espera,dias',
        'details.internalState:id,description',
        'details.externalState:id,description',
        'details.supportType:id,description',
    ])->findOrFail($id);

    // Convertir el modelo a array para modificar el cliente
    $supportArray = $support->toArray();

    // Reemplazar el cliente con el formato frontend
    $supportArray['client'] = $support->client ? $support->client->toFrontend() : null;

    return Inertia::render('reports/Show', [
        'support' => $supportArray
    ]);
}


}
