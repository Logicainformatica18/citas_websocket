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
class SupportController extends Controller
{
    public function index()
    {
        $supports = Support::with([
            'creator:id,firstname,lastname,names',
            'client:id_cliente,Razon_Social,dni,telefono,email',

            // Todos los detalles y sus relaciones

            'details.area:id_area,descripcion',
            'details.project:id_proyecto,descripcion',
            'details.motivoCita:id_motivos_cita,nombre_motivo',
            'details.tipoCita:id_tipo_cita,tipo',
            'details.diaEspera:id_dias_espera,dias',
            'details.internalState:id,description',
            'details.externalState:id,description',
            'details.supportType:id,description',
            'details.type:id,description',
        ])
            ->latest()
            ->paginate(7);

        // Opciones para selects
        $motives = Motive::select('id_motivos_cita as id', 'nombre_motivo')->get();
        $appointmentTypes = AppointmentType::select('id_tipo_cita as id', 'tipo')->get();
        $waitingDays = WaitingDay::select('id_dias_espera as id', 'dias')->get();
        $internalStates = InternalState::select('id', 'description')->get();
        $externalStates = ExternalState::select('id', 'description')->get();
        $types = Type::select('id', 'description')->get();
        $projects = Project::select('id_proyecto', 'descripcion')->get();
        $areas = Area::select('id_area', 'descripcion')->get();

        return Inertia::render('supports/index', [
            'supports' => $supports,
            'motives' => $motives,
            'appointmentTypes' => $appointmentTypes,
            'waitingDays' => $waitingDays,
            'internalStates' => $internalStates,
            'externalStates' => $externalStates,
            'types' => $types,
            'projects' => $projects,
            'areas' => $areas,
            'users' => User::select('id', 'names', 'email')->get(),
        ]);
        //     return response()->json([
//     'supports' => $supports,
//     'motives' => $motives,
//     'appointmentTypes' => $appointmentTypes,
//     'waitingDays' => $waitingDays,
//     'internalStates' => $internalStates,
//     'externalStates' => $externalStates,
//     'types' => $types,
//     'projects' => $projects,
//     'areas' => $areas,
//     'users' => User::select('id', 'names', 'email')->get(),
// ]);

    }





    public function updateAreaMotivo(Request $request, $id)
    {
        $request->validate([
            'area_id' => 'nullable|exists:areas,id_area',
            'id_motivos_cita' => 'nullable|exists:motivos_cita,id_motivos_cita',
        ]);

        $support = Support::with(['area', 'client', 'project', 'externalState', 'internalState'])->findOrFail($id); // 👈 incluye relaciones necesarias

        $support->area_id = $request->area_id;
        $support->id_motivos_cita = $request->id_motivos_cita;
        $support->save();

        // Volver a cargar relaciones actualizadas
        $support->load(['area', 'client', 'project', 'externalState', 'internalState']);

        // Emitir el evento con los datos como array
        broadcast(new RecordChanged('Support', 'updated', $support->toArray())); // 👈 aquí convertimos el modelo a array

        return response()->json(['message' => 'Actualizado correctamente', 'support' => $support]);
    }


    public function fetchPaginated()
    {
        $supports = Support::with([
            'client:id_cliente,Razon_Social,dni,telefono,email,direccion',
            'creator:id,firstname,lastname,names',
            'details.project:id_proyecto,descripcion',
            'details.area:id_area,descripcion',
            'details.motivoCita:id_motivos_cita,nombre_motivo',
            'details.tipoCita:id_tipo_cita,tipo',
            'details.diaEspera:id_dias_espera,dias',
            'details.internalState:id,description',
            'details.externalState:id,description',
            'details.supportType:id,description',
        ])
            ->latest()
            ->paginate(7);

        return response()->json([
            'supports' => $supports,
        ]);
    }






    public function store(Request $request)
    {
        // ✅ Loguear todo el request entrante (útil para depuración)
        Log::info('📥 Datos recibidos en store():', $request->all());

        // 1. Crear soporte base
        $support = Support::create([
            'client_id' => $request->client_id,
            'state' => $request->state,
            'status_global' => $request->status_global, // <- asegúrate que esté bien escrito
            'created_by' => Auth::id(),
        ]);

        Log::info('✅ Soporte creado:', $support->toArray());

        $details = json_decode($request->details, true); // Convertir a array asociativo

        if (!is_array($details)) {
            Log::error('❌ Error: details no es un array válido', ['details' => $request->details]);
            return response()->json(['message' => 'Detalles inválidos'], 422);
        }

        // Obtener archivos múltiples (puede venir como null si no se cargó nada)
        $attachments = $request->file('attachments'); // Puede ser array o null

        foreach ($details as $index => $detail) {
            // Buscar el archivo correspondiente a este índice (si existe)
            $attachment = isset($attachments[$index]) ? fileStore($attachments[$index], 'uploads') : null;

            $support->details()->create([
                'subject' => $detail['subject'],
                'description' => $detail['description'] ?? null,
                'priority' => $detail['priority'] ?? 'Normal',
                'type' => $detail['type'] ?? 'Consulta',
                'status' => $detail['status'] ?? 'Pendiente',
                'reservation_time' => $detail['reservation_time'] ?? now(),
                'attended_at' => $detail['attended_at'] ?? now()->addHour(),
                'derived' => $detail['derived'] ?? null,
                'project_id' => $detail['project_id'] ?? null,
                'area_id' => $detail['area_id'] ?? null,
                'id_motivos_cita' => $detail['id_motivos_cita'] ?? null,
                'id_tipo_cita' => $detail['id_tipo_cita'] ?? null,
                'id_dia_espera' => $detail['id_dia_espera'] ?? null,
                'internal_state_id' => $detail['internal_state_id'] ?? null,
                'external_state_id' => $detail['external_state_id'] ?? null,
                'type_id' => $detail['type_id'] ?? null,
                'Manzana' => $detail['Manzana'] ?? null,
                'Lote' => $detail['Lote'] ?? null,
                'attachment' => $attachment,
            ]);
        }




        // 🔁 Cargar todas las relaciones necesarias (una sola vez)
        $support->load([
            'client:id_cliente,Razon_Social,Telefono,Email,Direccion',
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

        // 🔊 Emitir evento por WebSocket (con relaciones ya cargadas)
        broadcast(new RecordChanged('Support', 'created', $support->toArray()))->toOthers();

        $clientId = $request->input('client_id');
        $data = $request->only(['dni', 'cellphone', 'email', 'address']);

        dispatch(function () use ($clientId, $data) {
            $client = \App\Models\Client::find($clientId);
            if ($client) {
                $client->updateFromSupport($data);
            }
        });



        // 📨 Notificar a usuarios ATC por correo usando cola
        dispatch(function () use ($support) {
            try {
                Log::info('[ATC Notification] Iniciando proceso de notificación por cola.');

                $atcUsers = User::role('ATC')->get();
                Log::info('[ATC Notification] Usuarios con rol ATC:', $atcUsers->pluck('email')->toArray());

                $supportLoaded = $support->load([
                    'client:id_cliente,Razon_Social,Telefono,Email,Direccion',
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

                Log::info('[ATC Notification] Soporte cargado para notificación:', ['id' => $supportLoaded->id]);

                Notification::send(
                    $atcUsers,
                    new NewSupportAtcNotification($supportLoaded, 'created')
                );

                Log::info('[ATC Notification] Notificaciones enviadas correctamente.');
            } catch (\Throwable $e) {
                Log::error('[ATC Notification] Error al enviar notificaciones:', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        });

        // ✅ Retornar respuesta con soporte y relaciones cargadas
        return response()->json([
            'message' => '✅ Ticket de soporte creado con sus detalles',
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
            ]), // ya tiene loaded relations
        ]);
    }





    public function update(Request $request, $id)
    {
        $support = Support::findOrFail($id);

        // 1. Actualizar campos del soporte general
        $support->fill([
            // 'client_id' => $request->input('client_id'),
            // 'cellphone' => $request->input('cellphone'),
            'status_global' => $request->input('status_global'),
        ]);

        // 2. Procesar archivo adjunto si se sube uno nuevo
        if ($request->hasFile('attachment')) {
            $support->attachment = fileUpdate($request->file('attachment'), 'attachments', $support->attachment);
        }

        $support->save();



        // Elimina detalles actuales y reemplaza por los nuevos (opcionalmente podrías actualizar uno por uno)
        $support->details()->delete();

        $details = $request->input('details', []);
        // ✅ Solución clave
        if (is_string($details)) {
            $details = json_decode($details, true);
        }

        foreach ($details as $index => $detail) {
            $newDetail = $support->details()->create([
                'subject' => $detail['subject'] ?? '',
                'description' => $detail['description'] ?? '',
                'priority' => $detail['priority'] ?? '',
                'type' => $detail['type'] ?? '',
                'status' => $detail['status'] ?? '',
                'reservation_time' => $detail['reservation_time'] ?? null,
                'attended_at' => $detail['attended_at'] ?? null,
                'derived' => $detail['derived'] ?? '',
                'Manzana' => $detail['Manzana'] ?? '',
                'Lote' => $detail['Lote'] ?? '',
                'project_id' => $detail['project_id'] ?? null,
                'area_id' => $detail['area_id'] ?? null,
                'id_motivos_cita' => $detail['id_motivos_cita'] ?? null,
                'id_tipo_cita' => $detail['id_tipo_cita'] ?? null,
                'id_dia_espera' => $detail['id_dia_espera'] ?? null,
                'internal_state_id' => $detail['internal_state_id'] ?? null,
                'external_state_id' => $detail['external_state_id'] ?? null,
                'type_id' => $detail['type_id'] ?? null,
            ]);

            // Procesar archivo si viene por índice
            if ($request->hasFile("attachments.$index")) {
                $newDetail->attachment = fileUpdate($request->file("attachments.$index"), 'attachments');
                $newDetail->save();
            }
        }


        // 4. Recargar relaciones necesarias para el frontend
        $support->load([
            'client:id_cliente,Razon_Social,telefono,email,direccion',
            'creator:id,firstname,lastname,names',

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


        // 5. Registrar en log
        Log::info('📝 Soporte actualizado', [
            'support_id' => $support->id,
            'user_id' => Auth::id(),
            'updated_fields' => $request->except(['_method', '_token']),
        ]);

        // 6. Emitir evento
        broadcast(new RecordChanged('Support', 'updated', $support->toArray()))->toOthers();

        dispatch(function () use ($support) {
            try {
                Log::info('[ATC Notification] Iniciando proceso de notificación por cola.');

                $atcUsers = User::role('ATC')->get();
                Log::info('[ATC Notification] Usuarios con rol ATC:', $atcUsers->pluck('email')->toArray());

                $supportLoaded = $support->load([
                    'client:id_cliente,Razon_Social,Telefono,Email,Direccion',
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

                Log::info('[ATC Notification] Soporte cargado para notificación:', ['id' => $supportLoaded->id]);

                Notification::send(
                    array_merge(
                        $atcUsers->all(), // usuarios notificables
                        [
                            Notification::route('mail', $supportLoaded->client->Email)
                        ]
                    ),
                    new NewSupportAtcNotification($supportLoaded, 'created')
                );


                Log::info('[ATC Notification] Notificaciones enviadas correctamente.');
            } catch (\Throwable $e) {
                Log::error('[ATC Notification] Error al enviar notificaciones:', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        });

        return response()->json([
            'message' => '✅ Ticket de soporte actualizado correctamente',
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


    // $areaRoleName = $support->area->descripcion ?? null;

    //         // Verificar que el rol existe antes de usarlo
//         if ($areaRoleName && Role::where('name', $areaRoleName)->where('guard_name', 'web')->exists()) {
//             $usersToNotify = User::role($areaRoleName)->get();

    //             Log::info("🔔 Notificando a usuarios con rol '{$areaRoleName}' tras actualización del soporte #{$support->id}", [
//                 'user_ids' => $usersToNotify->pluck('id'),
//                 'user_emails' => $usersToNotify->pluck('email'),
//                 'user_names' => $usersToNotify->pluck('name'),
//                 'support_id' => $support->id,
//             ]);

    //             dispatch(function () use ($usersToNotify, $support) {
//               Notification::send($usersToNotify, new NewSupportAtcNotification($support, 'updated'));


    //             });
//         } else {
//             Log::warning("⚠️ No se notificó a ningún usuario porque no existe un rol '{$areaRoleName}'");
//         }



    public function show($id)
    {
        $support = Support::with([
            'client:id_cliente,Razon_Social,dni,Telefono,Email,Direccion',
            'creator:id,firstname,lastname,names',
            'details',
            'details.area:id_area,descripcion',
            'details.project:id_proyecto,descripcion',
            'details.motivoCita:id_motivos_cita,nombre_motivo',
            'details.tipoCita:id_tipo_cita,tipo',
            'details.diaEspera:id_dias_espera,dias',
            'details.internalState:id,description',
            'details.externalState:id,description',
            'details.supportType:id,description',
        ])->findOrFail($id);

        return response()->json($support);
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


    public function exportAll()
    {
        return Excel::download(new SupportExport, 'supports.xlsx');
    }
}
