<?php

namespace App\Http\Controllers;

use App\Events\RecordChanged;
use App\Models\Support;
use App\Models\Motive;
use App\Models\Client;
use App\Models\Project;
use App\Models\AppointmentType;
use App\Models\WaitingDay;
use App\Models\InternalState;
use App\Models\ExternalState;
use App\Models\Type;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SupportController extends Controller
{
   public function index()
{
    $supports = Support::with([
        'area:id_area,descripcion',
        'creator:id,firstname,lastname,names',
        'client:id_cliente,Razon_Social',
        'motivoCita:id_motivos_cita,nombre_motivo',
        'tipoCita:id_tipo_cita,tipo',
        'diaEspera:id_dias_espera,dias',
        'internalState:id,description',
        'externalState:id,description',
        'supportType:id,description'
    ])->latest()->paginate(10);

    // Opciones para selects
    $motives = Motive::select('id_motivos_cita as id', 'nombre_motivo')->get();
    $appointmentTypes = AppointmentType::select('id_tipo_cita as id', 'tipo')->get();
    $waitingDays = WaitingDay::select('id_dias_espera as id', 'dias')->get();
    $internalStates = InternalState::select('id', 'description')->get();
    $externalStates = ExternalState::select('id', 'description')->get();
    $types = Type::select('id', 'description')->get();
        $projects = Project::select('id_proyecto', 'descripcion')->get();
    return Inertia::render('supports/index', [
        'supports' => $supports,
        'motives' => $motives,
        'appointmentTypes' => $appointmentTypes,
        'waitingDays' => $waitingDays,
        'internalStates' => $internalStates,
        'externalStates' => $externalStates,
        'types' => $types,
        'projects' => $projects
    ]);
}

public function fetchPaginated()
{
    $supports = Support::with([
        'area:id_area,descripcion',
        'creator:id,firstname,lastname,names',
        'client:id_cliente,Razon_Social',
        'motivoCita:id_motivos_cita,nombre_motivo',
        'tipoCita:id_tipo_cita,tipo',
        'diaEspera:id_dias_espera,dias',
        'internalState:id,description',
        'externalState:id,description',
        'supportType:id,description'
    ])->latest()->paginate(10);

    return response()->json([
        'supports' => $supports
    ]);
}



public function store(Request $request)
{
    $support = new Support();

    $support->subject = $request->input('subject', 'Nuevo Ticket de Soporte');
    $support->description = $request->input('description', 'Descripción del ticket de soporte');
    $support->client_id = $request->input('client_id', 1);
    $support->cellphone = $request->input('cellphone', '0000000000');
    $support->priority = $request->input('priority');
    $support->status = 'Pendiente';
    $support->reservation_time = now();
    $support->attended_at = now()->addHour();
    $support->created_by = Auth::id();
    $support->external_state_id = 1;
    $support->internal_state_id = 2;
    $support->id_tipo_cita = 1;
    $support->type_id = 3;
    $support->area_id = 1;
    $support->derived = $request->input('derived', '');
    $support->id_motivos_cita = 28;
    $support->project_id = $request->project_id;
    $support->Manzana = $request->Manzana;
    $support->Lote = $request->Lote;


    if ($request->hasFile('attachment')) {
        $support->attachment = fileStore($request->file('attachment'), 'uploads');
    }

    $support->save();

    // 🔄 Cargar relaciones igual que en el index
    $support->load([
        'area:id_area,descripcion',
        'creator:id,firstname,lastname,names',
        'client:id_cliente,Razon_Social',
        'motivoCita:id_motivos_cita,nombre_motivo',
        'tipoCita:id_tipo_cita,tipo',
        'diaEspera:id_dias_espera,dias',
        'internalState:id,description',
        'externalState:id,description',
        'supportType:id,description'
    ]);

     broadcast(new RecordChanged('Support', 'created', $support->toArray()))->toOthers();


$clientId = $request->input('client_id');
$data = $request->only(['dni', 'cellphone', 'email', 'address']);

dispatch(function () use ($clientId, $data) {
    $client = \App\Models\Client::find($clientId);
    if ($client) {
        $client->updateFromSupport($data);
    }
});



    return response()->json([
        'message' => '✅ Ticket de soporte creado correctamente',
        'support' => $support,
    ]);
}


    public function update(Request $request, $id)
    {
        $support = Support::findOrFail($id);
        $this->validateSupport($request);

        $support->fill($request->except('attachment'));

        if ($request->hasFile('attachment')) {
            $support->attachment = fileUpdate($request->file('attachment'), 'attachments', $support->attachment);
        }

        $support->save();

       broadcast(new RecordChanged('Support', 'updated', $support->toArray()))->toOthers();

        return response()->json([
            'message' => '✅ Ticket de soporte actualizado correctamente',
            'support' => $support,
        ]);
    }

    public function show($id)
    {
        $support = Support::with([
            'area',
            'creator',
            'client',
            'motivoCita',
            'tipoCita',
            'diaEspera',
            'internalState',
            'externalState',
            'supportType'
        ])->findOrFail($id);

        return response()->json(['support' => $support]);
    }

    public function destroy($id)
    {
        $support = Support::findOrFail($id);
        $support->delete();

        broadcast(new RecordChanged('Support', 'deleted', ['id' => $support->id]));

        return response()->json(['success' => true]);
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        Support::whereIn('id', $ids)->delete();

        foreach ($ids as $id) {
             broadcast(new RecordChanged('Support', 'deleted', ['id' => $id]));
        }

        return response()->json(['message' => 'Tickets eliminados correctamente']);
    }

    private function validateSupport(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:Normal,Urgente,Preferencial',
            'type' => 'required|string|max:50',
            'attachment' => 'nullable|file|max:2048',
            'area_id' => 'nullable|exists:areas,id_area',
            'client_id' => 'required|exists:clientes,id_cliente',
            'status' => 'required|in:Pendiente,Atendido,Cerrado',
            'reservation_time' => 'nullable|date',
            'attended_at' => 'nullable|date',
            'derived' => 'nullable|string|max:255',
            'cellphone' => 'nullable|string|max:20',
            'id_motivos_cita' => 'nullable|exists:motivos_cita,id_motivos_cita',
            'id_tipo_cita' => 'nullable|exists:tipos_cita,id_tipo_cita',
            'id_dia_espera' => 'nullable|exists:dias_espera,id_dias_espera',
            'internal_state_id' => 'nullable|exists:internal_states,id',
            'external_state_id' => 'nullable|exists:external_states,id',
            'type_id' => 'nullable|exists:types,id',
        ]);
    }
}
