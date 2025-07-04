<?php

namespace App\Http\Controllers;

use App\Events\RecordChanged;
use App\Models\Area;
use App\Models\Support;
use App\Models\Motive;
use App\Models\Client;
use App\Models\Project;
use App\Models\AppointmentType;
use App\Models\WaitingDay;
use App\Models\InternalState;
use App\Models\ExternalState;
use App\Models\Type;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Exports\SupportExport;
use App\Notifications\NewSupportAtcNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\SupportDetail;
class SupportDetailController extends Controller
{
    // public function index()
    // {
    //     $supports = Support::with([
    //         'area:id_area,descripcion',
    //         'creator:id,firstname,lastname,names',
    //         'client:id_cliente,Razon_Social',
    //         'motivoCita:id_motivos_cita,nombre_motivo',
    //         'tipoCita:id_tipo_cita,tipo',
    //         'diaEspera:id_dias_espera,dias',
    //         'internalState:id,description',
    //         'externalState:id,description',
    //         'supportType:id,description',
    //         'project:id_proyecto,descripcion',


    //         'type:id,description'
    //     ])->latest()->paginate(7);

    //     // Opciones para selects
    //     $motives = Motive::select('id_motivos_cita as id', 'nombre_motivo')->get();
    //     $appointmentTypes = AppointmentType::select('id_tipo_cita as id', 'tipo')->get();
    //     $waitingDays = WaitingDay::select('id_dias_espera as id', 'dias')->get();
    //     $internalStates = InternalState::select('id', 'description')->get();
    //     $externalStates = ExternalState::select('id', 'description')->get();
    //     $types = Type::select('id', 'description')->get();
    //     $projects = Project::select('id_proyecto', 'descripcion')->get();
    //     $areas = Area::select('id_area', 'descripcion')->get();

    //     return Inertia::render('supports/index', [
    //         'supports' => $supports,
    //         'motives' => $motives,
    //         'appointmentTypes' => $appointmentTypes,
    //         'waitingDays' => $waitingDays,
    //         'internalStates' => $internalStates,
    //         'externalStates' => $externalStates,
    //         'types' => $types,
    //         'projects' => $projects,
    //         'areas' => $areas,
    //         'users' => User::select('id', 'names', 'email')->get(),
    //     ]);
    // }


    public function updateAreaMotivo(Request $request, $id)
    {
        // (Opcional) Validación, puedes reactivarla si quieres control:
        // $request->validate([
        //     'area_id' => 'nullable|exists:areas,id_area',
        //     'id_motivos_cita' => 'nullable|exists:motivos_cita,id_motivos_cita',
        //     'internal_state_id' => 'nullable|exists:internal_states,id',
        // ]);

        // 🔍 Buscar el detalle con relaciones
        $support_detail = SupportDetail::with([
            'area',
            'project',
            'externalState',
            'internalState'
        ])->findOrFail($id);

        // ✏️ Actualizar campos
        $support_detail->area_id = $request->area_id;
        if($request->area_id!=1){
            $support_detail->external_state_id=2;

        }
        else{
             $support_detail->external_state_id=1;

        }

        $support_detail->id_motivos_cita = $request->id_motivos_cita;
        $support_detail->internal_state_id = $request->internal_state_id;

        $support_detail->save();

        // 🔁 Obtener el Solicitud relacionado y cargar TODO lo necesario
        $support = Support::find($support_detail->support_id);

        $support->load([
            'client:id_cliente,Razon_Social,telefono,email,direccion',
            'creator:id,firstname,lastname,names,email',
            'details:id,support_id,subject,description,priority,type,status,reservation_time,attended_at,derived,Manzana,Lote,attachment,project_id,area_id,id_motivos_cita,id_tipo_cita,id_dia_espera,internal_state_id,external_state_id,type_id',
            'details.area:id_area,descripcion',
            'details.project:id_proyecto,descripcion',
            'details.motivoCita:id_motivos_cita,nombre_motivo',
            'details.tipoCita:id_tipo_cita,tipo',
            'details.diaEspera:id_dias_espera,dias',
            'details.internalState:id,description',
            'details.externalState:id,description',
            'details.supportType:id,description',
        ]);

        // 📢 Emitir evento
        broadcast(new RecordChanged('Support', 'updated', $support->toArray()));

        $clientEmail = $support->client->email; // es solo un string, correcto

        dispatch(function () use ($support, $clientEmail) {
            $supportLoaded = $support->load([
                'client:id_cliente,Razon_Social,telefono,email,direccion',
                'creator:id,firstname,lastname,names,email',
                'details:id,support_id,subject,description,priority,type,status,reservation_time,attended_at,derived,Manzana,Lote,attachment,project_id,area_id,id_motivos_cita,id_tipo_cita,id_dia_espera,internal_state_id,external_state_id,type_id',
                'details.area:id_area,descripcion',
                'details.project:id_proyecto,descripcion',
                'details.motivoCita:id_motivos_cita,nombre_motivo',
                'details.tipoCita:id_tipo_cita,tipo',
                'details.diaEspera:id_dias_espera,dias',
                'details.internalState:id,description',
                'details.externalState:id,description',
                'details.supportType:id,description',
            ]);

            // Obtener los usuarios con el mismo rol del creador del Solicitud
            $creatorRoles = $supportLoaded->creator->getRoleNames(); // Collection
            $atcUsers = User::role($creatorRoles)->get();    // todos los usuarios con al menos uno de esos roles

            // Preparar lista de notifiables
            $notifiables = $atcUsers->all(); // array de usuarios
            $notifiables[] = Notification::route('mail', $clientEmail); // agrega email directo del cliente

            // Enviar notificación
            Notification::send(
                $notifiables,
                new NewSupportAtcNotification($supportLoaded, 'updated')
            );
        });



        // 📦 Retornar respuesta
        return response()->json([
            'message' => '✅ Ticket de Solicitud actualizada correctamente',
            'support' => $support->load([
                'client:id_cliente,Razon_Social,telefono,email,direccion',
                'creator:id,firstname,lastname,names,email',
                'details:id,support_id,subject,description,priority,type,status,reservation_time,attended_at,derived,Manzana,Lote,attachment,project_id,area_id,id_motivos_cita,id_tipo_cita,id_dia_espera,internal_state_id,external_state_id,type_id',
                'details.area:id_area,descripcion',
                'details.project:id_proyecto,descripcion',
                'details.motivoCita:id_motivos_cita,nombre_motivo',
                'details.tipoCita:id_tipo_cita,tipo',
                'details.diaEspera:id_dias_espera,dias',
                'details.internalState:id,description',
                'details.externalState:id,description',
                'details.supportType:id,description',
            ]),
        ]);
    }

    //     public function updateAreaMotivo(Request $request, $supportDetailId)
// {
//     // 💡 Acceso al ID desde la URL
//     // $supportDetailId es el que viene de /support-details/{supportDetailId}/area-motivo

    //     // 💡 Acceso a los campos enviados en el cuerpo
//     $supportId = $request->input('support_id');
//     $areaId = $request->input('area_id');
//     $motivoId = $request->input('id_motivos_cita');
//     $internalStateId = $request->input('internal_state_id');

    //     // 🛠️ Aquí haces la lógica de actualización
//     $detail = SupportDetail::findOrFail($supportDetailId);
//     $detail->support_id = $supportId;
//     $detail->area_id = $areaId;
//     $detail->id_motivos_cita = $motivoId;
//     $detail->internal_state_id = $internalStateId;
//     $detail->save();

    //     return response()->json($detail);
// }


    // public function updateAreaMotivo(Request $request, $id)
    // {
    //     $request->validate([
    //         'area_id' => 'nullable|exists:areas,id_area',
    //         'id_motivos_cita' => 'nullable|exists:motivos_cita,id_motivos_cita',
    //     ]);

    //     $support = Support::with(['area', 'client', 'project', 'externalState', 'internalState'])->findOrFail($id); // 👈 incluye relaciones necesarias

    //     $support->area_id = $request->area_id;
    //     $support->id_motivos_cita = $request->id_motivos_cita;
    //     $support->save();

    //     // Volver a cargar relaciones actualizadas
    //     $support->load(['area', 'client', 'project', 'externalState', 'internalState']);

    //     // Emitir el evento con los datos como array
    //     broadcast(new RecordChanged('Support', 'updated', $support->toArray())); // 👈 aquí convertimos el modelo a array

    //     return response()->json(['message' => 'Actualizado correctamente', 'support' => $support]);
    // }


    // public function fetchPaginated()
    // {
    //     $supports = Support::with([
    //         'area:id_area,descripcion',
    //         'creator:id,firstname,lastname,names',
    //         'client:id_cliente,Razon_Social,dni,telefono,email,direccion',
    //         'motivoCita:id_motivos_cita,nombre_motivo',
    //         'tipoCita:id_tipo_cita,tipo',
    //         'diaEspera:id_dias_espera,dias',
    //         'internalState:id,description',
    //         'externalState:id,description',
    //         'supportType:id,description',
    //         'project:id_proyecto,descripcion',
    //         'externalState:id,description',

    //         'internalState:id,description'
    //     ])->latest()->paginate(7);

    //     return response()->json([
    //         'supports' => $supports
    //     ]);
    // }



    // public function store(Request $request)
    // {
    //     $support = new Support();

    //     $support->subject = $request->input('subject', 'Nuevo Ticket de Solicitud');
    //     $support->description = $request->input('description', 'Descripción del ticket de Solicitud');
    //     $support->client_id = $request->input('client_id', 1);
    //     $support->cellphone = $request->input('cellphone', '0000000000');
    //     $support->priority = $request->input('priority');
    //     $support->status = 'Pendiente';
    //     $support->reservation_time = now();
    //     $support->attended_at = now()->addHour();
    //     $support->created_by = Auth::id();
    //     $support->external_state_id = 1;
    //     $support->internal_state_id = 2;
    //     $support->id_tipo_cita = 1;
    //     $support->type_id = 3;
    //     $support->area_id = 1;
    //     $support->derived = $request->input('derived', '');
    //     $support->id_motivos_cita = 28;
    //     $support->project_id = $request->project_id;
    //     $support->Manzana = $request->input('Manzana');
    //     $support->Lote = $request->input('Lote');


    //     if ($request->hasFile('attachment')) {
    //         $support->attachment = fileStore($request->file('attachment'), 'uploads');
    //     }

    //     $support->save();

    //     // 🔄 Cargar relaciones igual que en el index
    //     $support->load([
    //         'area:id_area,descripcion',
    //         'creator:id,firstname,lastname,names',
    //         'client:id_cliente,Razon_Social',
    //         'motivoCita:id_motivos_cita,nombre_motivo',
    //         'tipoCita:id_tipo_cita,tipo',
    //         'diaEspera:id_dias_espera,dias',
    //         'internalState:id,description',
    //         'externalState:id,description',
    //         'supportType:id,description',
    //         'project:id_proyecto,descripcion',



    //     ]);

    //     broadcast(new RecordChanged('Support', 'created', $support->toArray()))->toOthers();


    //     $clientId = $request->input('client_id');
    //     $data = $request->only(['dni', 'cellphone', 'email', 'address']);

    //     dispatch(function () use ($clientId, $data) {
    //         $client = Client::find($clientId);
    //         if ($client) {
    //             $client->updateFromSupport($data);
    //         }
    //     });

    //     ///////////////////////

    //     dispatch(function () use ($support) {
    //         $atcUsers = User::role('ATC')->get();
    //         $support->load([
    //             'creator:id,names,email',
    //             'client:id_cliente,Razon_Social',
    //             'area:id_area,descripcion',
    //             'project:id_proyecto,descripcion'
    //         ]);

    //         Notification::send($atcUsers, new NewSupportAtcNotification($support, 'created'));

    //     });

    //     return response()->json([
    //         'message' => '✅ Ticket de Solicitud creado correctamente',
    //         'support' => $support,
    //     ]);
    // }




    // public function update(Request $request, $id)
    // {
    //     $support = Support::findOrFail($id);

    //     // Asignación específica SIN valores por defecto
    //     $support->subject = $request->input('subject');
    //     $support->description = $request->input('description');
    //     $support->client_id = $request->input('client_id');
    //     $support->cellphone = $request->input('cellphone');
    //     $support->priority = $request->input('priority');
    //     $support->status = $request->input('status');
    //     $support->reservation_time = $request->input('reservation_time');
    //     $support->attended_at = $request->input('attended_at');
    //     $support->area_id = $request->input('area_id');
    //     $support->derived = $request->input('derived');
    //     $support->id_motivos_cita = $request->input('id_motivos_cita');
    //     $support->id_tipo_cita = $request->input('id_tipo_cita');
    //     $support->id_dia_espera = $request->input('id_dia_espera');
    //     $support->internal_state_id = $request->input('internal_state_id');
    //     $support->external_state_id = $request->input('external_state_id');
    //     $support->type_id = $request->input('type_id');
    //     $support->project_id = $request->input('project_id');
    //     $support->Manzana = $request->input('Manzana');
    //     $support->Lote = $request->input('Lote');

    //     // Procesar archivo adjunto
    //     if ($request->hasFile('attachment')) {
    //         $support->attachment = fileUpdate($request->file('attachment'), 'attachments', $support->attachment);
    //     }

    //     $support->save();

    //     // Log para auditoría
    //     Log::info('📝 Solicitud actualizado', [
    //         'support_id' => $support->id,
    //         'user_id' => Auth::id(),
    //         'updated_fields' => $request->except(['_method', '_token']),
    //     ]);
    //     $support->load([
    //         'area:id_area,descripcion',
    //         'creator:id,firstname,lastname,names',
    //         'client:id_cliente,Razon_Social',
    //         'motivoCita:id_motivos_cita,nombre_motivo',
    //         'tipoCita:id_tipo_cita,tipo',
    //         'diaEspera:id_dias_espera,dias',
    //         'internalState:id,description',
    //         'externalState:id,description',
    //         'supportType:id,description',
    //         'project:id_proyecto,descripcion',
    //     ]);


    //     $areaRoleName = $support->area->descripcion ?? null;

    //     // Verificar que el rol existe antes de usarlo
    //     if ($areaRoleName && Role::where('name', $areaRoleName)->where('guard_name', 'web')->exists()) {
    //         $usersToNotify = User::role($areaRoleName)->get();

    //         Log::info("🔔 Notificando a usuarios con rol '{$areaRoleName}' tras actualización del Solicitud #{$support->id}", [
    //             'user_ids' => $usersToNotify->pluck('id'),
    //             'user_emails' => $usersToNotify->pluck('email'),
    //             'user_names' => $usersToNotify->pluck('name'),
    //             'support_id' => $support->id,
    //         ]);

    //         dispatch(function () use ($usersToNotify, $support) {
    //             Notification::send($usersToNotify, new NewSupportAtcNotification($support, 'updated'));


    //         });
    //     } else {
    //         Log::warning("⚠️ No se notificó a ningún usuario porque no existe un rol '{$areaRoleName}'");
    //     }

    //     // Emitir evento
    //     broadcast(new RecordChanged('Support', 'updated', $support->toArray()))->toOthers();

    //     return response()->json([
    //         'message' => '✅ Ticket de Solicitud actualizado correctamente',
    //         'support' => $support,
    //     ]);
    // }


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
            'supportType',
            'project'
        ])->findOrFail($id);

        // 👉 reemplaza el cliente crudo por uno transformado
        $supportArray = $support->toArray();
        $supportArray['client'] = $support->client ? $support->client->toFrontend() : null;

        return response()->json(['support' => $supportArray]);
    }



    // public function destroy($id)
    // {
    //     $support = Support::findOrFail($id);
    //     $support->delete();

    //     broadcast(new RecordChanged('Support', 'deleted', ['id' => $support->id]));

    //     return response()->json(['success' => true]);
    // }
public function destroy($id)
{
    $detail = SupportDetail::findOrFail($id);
    $supportId = $detail->support_id;

    $detail->delete();

    // 🔄 Cargar el Solicitud actualizado con sus relaciones
    $support =  Support::with([
        'client:id_cliente,Razon_Social,dni,telefono,email,direccion',
        'creator:id,firstname,lastname,names,email',
        'details:id,support_id,subject,description,priority,type,status,reservation_time,attended_at,derived,Manzana,Lote,attachment,project_id,area_id,id_motivos_cita,id_tipo_cita,id_dia_espera,internal_state_id,external_state_id,type_id',
        'details.project:id_proyecto,descripcion',
        'details.area:id_area,descripcion',
        'details.motivoCita:id_motivos_cita,nombre_motivo',
        'details.tipoCita:id_tipo_cita,tipo',
        'details.diaEspera:id_dias_espera,dias',
        'details.internalState:id,description',
        'details.externalState:id,description',
        'details.supportType:id,description',
    ])->findOrFail($supportId);

    // 📡 Emitir el Solicitud actualizado
    broadcast(new RecordChanged('Support', 'detail_deleted', $support->toArray()));

    return redirect()->back()->with('success', 'Solicitud eliminada correctamente.');
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


    public function exportAll()
    {
        return Excel::download(new SupportExport, 'supports.xlsx');
    }
}
