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
// public function show($id)
// {
//     $support = Support::with([
//         'client',
//         'creator:id,names',
//         'details.area:id_area,descripcion',
//         'details.project:id_proyecto,descripcion',
//         'details.motivoCita:id_motivos_cita,nombre_motivo',
//         'details.tipoCita:id_tipo_cita,tipo',
//         'details.diaEspera:id_dias_espera,dias',
//         'details.internalState:id,description',
//         'details.externalState:id,description',
//         'details.supportType:id,description',
//     ])->findOrFail($id);

//     // Convertir el modelo a array para modificar el cliente
//     $supportArray = $support->toArray();

//     // Reemplazar el cliente con el formato frontend
//     $supportArray['client'] = $support->client ? $support->client->toFrontend() : null;

//     return Inertia::render('reports/Show', [
//         'support' => $supportArray
//     ]);
// }
public function show($id)
{
    // Arma el ticket en formato TK-00001
    $ticket = 'TR-' . str_pad($id, 5, '0', STR_PAD_LEFT);

    // Redirige al módulo de supports con la búsqueda aplicada
    return redirect()->route('supports.index', ['q' => $ticket]);
}

public function report()
    {
        $supports = Support::with([
            'client',
            'creator',
            'details.area',
            'details.project',
            'details.motivoCita',
            'details.tipoCita',
            'details.diaEspera',
            'details.internalState',
            'details.externalState',
            'details.supportType',
            'details.lastComment.internalState',
        ])->get();

        $exportData = [];

        foreach ($supports as $support) {
            $client = $support->client ? $support->client->toFrontend() : null;

            foreach ($support->details as $detail) {
                $exportData[] = [
                    'Ticket-TR' => 'TR-' . str_pad($detail->id, 5, '0', STR_PAD_LEFT),
                    'Ticket-TK' => $detail->ticket,
                    'ID de Soporte' => $support->id,
                    'Cliente' => $client['names'] ?? '',
                    'DNI' => $client['dni'] ?? '',
                    'Celular' => $client['cellphone'] ?? '',
                    'Email' => $client['email'] ?? '',
                    'Dirección' => $client['address'] ?? '',
                    'Asunto' => $detail->subject,
                    'Descripción' => $detail->description,
                    'Prioridad' => $detail->priority,
                    'Proyecto' => $detail->project->descripcion ?? '',
                    'Manzana' => $detail->Manzana,
                    'Área' => $detail->area->descripcion ?? '',
                    'Estado Interno' => $detail->internalState->description ?? '',
                    'Estado de Solicitud' => $detail->externalState->description ?? '',
                    'Registrado Por' => $support->creator->email ?? '',
                    'Canal' => $detail->channel ?? '',
                    'registro_inicio' => $support->created_at,
                    'registro_fin' => $detail->attended_start ?? '',
                    'Atencion_inicio' => $detail->attended_start ?? '',
                    'Atencion_fin' => $detail->attended_end ?? '',
                    'Ticket_inicio' => $detail->ticket_start ?? '',
                    'Ticket_fin' => $detail->ticket_end ?? '',
                    'estado_area' => $detail->lastComment->internalState->description ?? '',
                    'Fecha_ultima_modificacion' => $detail->updated_at ?? '',
                ];
            }
        }

        return view('supports.report', [
            'supports' => $exportData
        ]);
    }
}
