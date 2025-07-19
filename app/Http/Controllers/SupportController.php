<?php

namespace App\Http\Controllers;

use App\Events\RecordChanged;
use App\Models\Area;
use App\Models\Support;
use App\Models\Motive;
use App\Models\Client;
use App\Models\Project;
use App\Models\AppointmentType;
use App\Models\SupportDetail;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
class SupportController extends Controller
{
    public function index(Request $request)
    {
        $supports = Support::with([
            'creator:id,firstname,lastname,names',
            'client:id_cliente,Razon_Social,dni,telefono,email',
            'details.area:id_area,descripcion',
            'details.project:id_proyecto,descripcion',
            'details.motivoCita:id_motivos_cita,nombre_motivo',
            'details.tipoCita:id_tipo_cita,tipo',
            'details.diaEspera:id_dias_espera,dias',
            'details.internalState:id,description',
            'details.externalState:id,description',
            'details.supportType:id,description',
            'details.type:id,description',
            'details.lastComment.internalState:id,description',

        ])
            ->latest()
            ->paginate(7);

        // Datos auxiliares
        $motives = Motive::select('id_motivos_cita as id', 'nombre_motivo')->get();
        $appointmentTypes = AppointmentType::select('id_tipo_cita as id', 'tipo')->get();
        $waitingDays = WaitingDay::select('id_dias_espera as id', 'dias')->get();
        $internalStates = InternalState::select('id', 'description')
            ->where('description', '!=', 'Atendido')
            ->get();

        $externalStates = ExternalState::select('id', 'description')->get();
        $types = Type::select('id', 'description')->get();
        $projects = Project::select('id_proyecto', 'descripcion')->get();
        $areas = Area::select('id_area', 'descripcion')->get();
        $users = User::select('id', 'names', 'email')->get();

        // 📱 Si es API (ej. desde React Native), devuelve JSON
        if ($request->wantsJson()) {
            return response()->json([
                'supports' => $supports,
                'motives' => $motives,
                'appointmentTypes' => $appointmentTypes,
                'waitingDays' => $waitingDays,
                'internalStates' => $internalStates,
                'externalStates' => $externalStates,
                'types' => $types,
                'projects' => $projects,
                'areas' => $areas,
                'users' => $users,
            ]);
        }

        // 💻 Si es Inertia (web), renderiza la vista
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
            'users' => $users,
        ]);
    }



    // SupportController.php
    public function fetch(Request $request)
    {

        $query = $request->input('q');
        $detailId = null;
        Log::info('🔍 Valor recibido de q:', ['q' => $query]);
        if (preg_match('/tr[-]?0*(\d+)/i', $query, $matches)) {
            $detailId = (int) $matches[1];
        }

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
            'details.type:id,description',
            'details.lastComment.internalState:id,description',
        ])
            ->when($query, function ($q) use ($query, $detailId) {
                $q->where(function ($subQuery) use ($query, $detailId) {
                    $subQuery->whereHas('client', function ($sub) use ($query) {
                        $sub->where('dni', 'like', "%{$query}%")
                            ->orWhere('Razon_Social', 'like', "%{$query}%");
                    });

                    if ($detailId) {
                        $subQuery->orWhereHas('details', function ($sub) use ($detailId) {
                            $sub->where('id', $detailId);
                        });
                    }
                });
            })
            ->latest()
            ->paginate(7);


        return response()->json([
            'supports' => $supports,
        ]);
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
            'details.type:id,description',
            'details.lastComment.internalState:id,description',
        ])
            ->latest()
            ->paginate(7);

        return response()->json([
            'supports' => $supports,
        ]);
    }









    public function store(Request $request)
    {

        $details = json_decode($request->details, true);
        $firstDetail = json_decode($request->details, true)[0] ?? null;

        if (
            $firstDetail &&
            ($duplicateCode = $this->hasDuplicateSupportDetailWithCode(
                $request->client_id,
                $firstDetail['project_id'] ?? null,
                $firstDetail['subject'] ?? '',
                $firstDetail['Manzana'] ?? ''
            ))
        ) {
            return response()->json([
                'message' => "Ya existe un soporte similar registrado  {$duplicateCode}.",
            ]);
        }

        // 1. Crear soporte base
        $support = Support::create([
            'client_id' => $request->client_id,
            'state' => $request->state,
            'status_global' => $request->status_global ? $request->status_global : 'Incompleto',
            'created_by' => Auth::id(),
        ]);


        if (!is_array($details)) {
            Log::error('❌ Error: details no es un array válido', ['details' => $request->details]);
            return response()->json(['message' => 'Detalles inválidos'], 422);
        }

        // Obtener archivos múltiples (puede venir como null si no se cargó nada)
        $attachments = $request->file('attachments'); // Puede ser array o null
///////////////////////////////////////////////////////////////////////////////////////////////////
        $generateTicket = auth()->user()?->can('generar_ticket');


        $ticket = null;

        if ($generateTicket) {
            $ticket = (new SupportDetailController)->generateNextSupportTicket();
        }
        ///////////////////////////////////////////////////////////////////////////////////////////////////////
        $roles = Auth::user()->getRoleNames();

        if ($roles->contains('ATC_Oficina')) {
            // Rol ATC_Oficina → ignorar canal
            $channelName = null;
            Log::info("ℹ️ Canal ignorado para rol ATC_Oficina");
        } else {
            // Otros roles → detectar canal
            $permissions = Auth::user()->getAllPermissions();

            $channelPermission = $permissions->first(function ($perm) {
                return str_starts_with($perm->name, 'Canal.');
            });

            $channelName = $channelPermission ? str_replace('Canal.', '', $channelPermission->name) : null;
        }



        foreach ($details as $index => $detail) {
            Log::info("🧪 Verificando detalle #{$index}:", [
                'subject' => $detail['subject'] ?? null,
                'project_id' => $detail['project_id'] ?? null,
                'Manzana' => $detail['Manzana'] ?? null,
            ]);
            // Buscar el archivo correspondiente a este índice (si existe)
            $attachment = isset($attachments[$index]) ? fileStore($attachments[$index], 'uploads') : null;

            $createdDetail = $support->details()->create([
                'subject' => $detail['subject'],
                'description' => $detail['description'] ?? null,
                'priority' => $detail['priority'] ?? 'Baja',
                'type' => $detail['type'] ?? 'Consulta',
                'status' => $detail['status'] ?? 'Pendiente',
                'reservation_time' => isset($detail['reservation_time']) ? Carbon::parse($detail['reservation_time'])->format('Y-m-d H:i:s') : now(),
                'attended_at' => isset($detail['attended_at']) ? Carbon::parse($detail['attended_at'])->format('Y-m-d H:i:s') : now()->addHour(),
                'derived' => $detail['derived'] ?? null,
                'project_id' => $detail['project_id'] ?? null,
                'area_id' => $detail['area_id'] ?? 1,
                'id_motivos_cita' => $detail['id_motivos_cita'] ?? null,
                'id_tipo_cita' => $detail['id_tipo_cita'] ?? null,
                'id_dia_espera' => $detail['id_dia_espera'] ?? null,
                'internal_state_id' => $detail['internal_state_id'] ?? 3,
                'external_state_id' => $detail['external_state_id'] ?? 1,
                'type_id' => $detail['type_id'] ?? null,
                'Manzana' => $detail['Manzana'] ?? null,
                'comment' => $detail['comment'] ?? null,
                'attachment' => $attachment,
                'ticket' => $ticket ?? "TK-",
              'channel' => $channelName ?? ($detail['channel'] ?? null),



            ]);
            // Si el usuario tiene el permiso, genera el ticket tipo "TK-<id>"
            if (Auth::user()->can('generar_ticket')) {
                $createdDetail->update([
                    'ticket_start' => Carbon::now('America/Lima'),
                ]);
            }
        }


        $support->load([
            'client:id_cliente,Razon_Social,dni,telefono,email,direccion',
            'creator:id,firstname,lastname,names',

            'details:id,support_id,subject,description,priority,type,status,reservation_time,attended_at,derived,Manzana,comment,attachment,project_id,area_id,id_motivos_cita,id_tipo_cita,id_dia_espera,internal_state_id,external_state_id,type_id,ticket,channel',

            'details.area:id_area,descripcion',
            'details.project:id_proyecto,descripcion',
            'details.motivoCita:id_motivos_cita,nombre_motivo',
            'details.tipoCita:id_tipo_cita,tipo',
            'details.diaEspera:id_dias_espera,dias',
            'details.internalState:id,description',
            'details.externalState:id,description',
            'details.supportType:id,description',
            'details.type:id,description',
            'details.lastComment.internalState:id,description',

        ]);



        // 🔊 Emitir evento por WebSocket (con relaciones ya cargadas)
        broadcast(new RecordChanged('Support', 'created', $support->toArray()))->toOthers();

        $clientId = $request->input('client_id');
        $data = $request->only(['dni', 'cellphone', 'email', 'address']);

        dispatch(function () use ($clientId, $data) {
            $client = Client::find($clientId);
            if ($client) {
                $client->updateFromSupport($data);
            }
        });



        // // 📨 Notificar a usuarios ATC por correo usando cola
        // dispatch(function () use ($support) {
        //     try {
        //         Log::info('[ATC Notification] Iniciando proceso de notificación por cola.');

        //         $atcUsers = User::role('ATC')->get();
        //         Log::info('[ATC Notification] Usuarios con rol ATC:', $atcUsers->pluck('email')->toArray());

        //         $supportLoaded = $support->load([
        //             'client:id_cliente,Razon_Social,dni,Telefono,Email,Direccion',
        //             'creator:id,firstname,lastname,names,email',
        //             'details:id,support_id,subject,description,priority,type,status,reservation_time,attended_at,derived,Manzana,comment,attachment,project_id,area_id,id_motivos_cita,id_tipo_cita,id_dia_espera,internal_state_id,external_state_id,type_id',
        //             'details.area:id_area,descripcion',
        //             'details.project:id_proyecto,descripcion',
        //             'details.motivoCita:id_motivos_cita,nombre_motivo',
        //             'details.tipoCita:id_tipo_cita,tipo',
        //             'details.diaEspera:id_dias_espera,dias',
        //             'details.internalState:id,description',
        //             'details.externalState:id,description',
        //             'details.supportType:id,description',
        //         ]);

        //         Log::info('[ATC Notification] Soporte cargado para notificación:', ['id' => $supportLoaded->id]);

        //         Notification::send(
        //             $atcUsers,
        //             new NewSupportAtcNotification($supportLoaded, 'created')
        //         );

        //         Log::info('[ATC Notification] Notificaciones enviadas correctamente.');
        //     } catch (\Throwable $e) {
        //         Log::error('[ATC Notification] Error al enviar notificaciones:', [
        //             'message' => $e->getMessage(),
        //             'trace' => $e->getTraceAsString(),
        //         ]);
        //     }
        // });

        // ✅ Retornar respuesta con soporte y relaciones cargadas
        return response()->json([
            'message' => '✅ Ticket de soporte creado con sus detalles',
            'support' => $support->load([
                'client:id_cliente,Razon_Social,telefono,email,direccion,dni',
                'creator:id,firstname,lastname,names,email',
                'details:id,support_id,subject,description,priority,type,status,reservation_time,attended_at,derived,Manzana,comment,attachment,project_id,area_id,id_motivos_cita,id_tipo_cita,id_dia_espera,internal_state_id,external_state_id,type_id,ticket,channel',
                'details.area:id_area,descripcion',
                'details.project:id_proyecto,descripcion',
                'details.motivoCita:id_motivos_cita,nombre_motivo',
                'details.tipoCita:id_tipo_cita,tipo',
                'details.diaEspera:id_dias_espera,dias',
                'details.internalState:id,description',
                'details.externalState:id,description',
                'details.supportType:id,description',
                'details.type:id,description',
                'details.lastComment.internalState:id,description',
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
        //  $support->details()->delete();

        $details = $request->input('details', []);
        if (is_string($details)) {
            $details = json_decode($details, true);
        }

       $detailData = $details[0] ?? null;
Log::info('🔍 Detalle recibido para actualizar:', ['data' => $detailData]); // ✅ siempre array


        if ($detailData) {
            $existingDetail = $support->details()->first(); // Solo tomamos el primero

            if ($existingDetail) {
                $existingDetail->update([
                    'subject' => $detailData['subject'] ?? '',
                    'description' => $detailData['description'] ?? '',
                    'priority' => $detailData['priority'] ?? '',
                    'type' => $detailData['type'] ?? '',
                    'status' => $detailData['status'] ?? '',
                    'reservation_time' => $detailData['reservation_time'] ?? null,
                    'attended_at' => $detailData['attended_at'] ?? null,
                    'derived' => $detailData['derived'] ?? '',
                    'Manzana' => $detailData['Manzana'] ?? '',
                    'comment' => $detailData['comment'] ?? '',
                    'project_id' => $detailData['project_id'] ?? null,
                    'area_id' => $detailData['area_id'] ?? null,
                    'id_motivos_cita' => $detailData['id_motivos_cita'] ?? null,
                    'id_tipo_cita' => $detailData['id_tipo_cita'] ?? null,
                    'id_dia_espera' => $detailData['id_dia_espera'] ?? null,
                    'internal_state_id' => $detailData['internal_state_id'] ?? null,
                    'external_state_id' => $detailData['external_state_id'] ?? null,
                    'type_id' => $detailData['type_id'] ?? null,
                    // OJO: no tocar el ticket
                ]);

                // Procesar archivo
                if ($request->hasFile("attachments.0")) {
                    $existingDetail->attachment = fileUpdate(
                        $request->file("attachments.0"),
                        'attachments',
                        $existingDetail->attachment
                    );
                    $existingDetail->save();
                }
            }
        }



        // 4. Recargar relaciones necesarias para el frontend
        $support->load([
            'client:id_cliente,Razon_Social,telefono,email,direccion,dni',
            'creator:id,firstname,lastname,names',

            'details:id,support_id,subject,description,priority,type,status,reservation_time,attended_at,derived,Manzana,comment,attachment,project_id,area_id,id_motivos_cita,id_tipo_cita,id_dia_espera,internal_state_id,external_state_id,type_id,ticket,channel',

            'details.area:id_area,descripcion',
            'details.project:id_proyecto,descripcion',
            'details.motivoCita:id_motivos_cita,nombre_motivo',
            'details.tipoCita:id_tipo_cita,tipo',
            'details.diaEspera:id_dias_espera,dias',
            'details.internalState:id,description',
            'details.externalState:id,description',
            'details.supportType:id,description',
            'details.type:id,description',
            'details.lastComment.internalState:id,description',
        ]);


        // 5. Registrar en log
        Log::info('📝 Soporte actualizado', [
            'support_id' => $support->id,
            'user_id' => Auth::id(),
            'updated_fields' => $request->except(['_method', '_token']),
        ]);

        // 6. Emitir evento
        broadcast(new RecordChanged('Support', 'updated', $support->toArray()))->toOthers();

        // dispatch(function () use ($support) {
        //     try {
        //         Log::info('[ATC Notification] Iniciando proceso de notificación por cola.');

        //         $atcUsers = User::role('ATC')->get();
        //         Log::info('[ATC Notification] Usuarios con rol ATC:', $atcUsers->pluck('email')->toArray());

        //         $supportLoaded = $support->load([
        //             'client:id_cliente,Razon_Social,telefono,email,Direccion,dni',
        //             'creator:id,firstname,lastname,names,email',
        //             'details:id,support_id,subject,description,priority,type,status,reservation_time,attended_at,derived,Manzana,comment,attachment,project_id,area_id,id_motivos_cita,id_tipo_cita,id_dia_espera,internal_state_id,external_state_id,type_id,ticket,channel',
        //             'details.area:id_area,descripcion',
        //             'details.project:id_proyecto,descripcion',
        //             'details.motivoCita:id_motivos_cita,nombre_motivo',
        //             'details.tipoCita:id_tipo_cita,tipo',
        //             'details.diaEspera:id_dias_espera,dias',
        //             'details.internalState:id,description',
        //             'details.externalState:id,description',
        //             'details.supportType:id,description',
        //             'details.type:id,description',
        //             'details.lastComment.internalState:id,description',
        //         ]);

        //         Log::info('[ATC Notification] Soporte cargado para notificación:', ['id' => $supportLoaded->id]);

        //         Notification::send(
        //             array_merge(
        //                 $atcUsers->all(), // usuarios notificables
        //                 [
        //                     Notification::route('mail', $supportLoaded->client->Email)
        //                 ]
        //             ),
        //             new NewSupportAtcNotification($supportLoaded, 'updated')
        //         );


        //         Log::info('[ATC Notification] Notificaciones enviadas correctamente.');
        //     } catch (\Throwable $e) {
        //         Log::error('[ATC Notification] Error al enviar notificaciones:', [
        //             'message' => $e->getMessage(),
        //             'trace' => $e->getTraceAsString(),
        //         ]);
        //     }
        // });

        return response()->json([
            'message' => '✅ Ticket de soporte actualizado correctamente',
            'support' => $support->load([
                'client:id_cliente,Razon_Social,telefono,email,direccion,dni',
                'creator:id,firstname,lastname,names,email',
                'details:id,support_id,subject,description,priority,type,status,reservation_time,attended_at,derived,Manzana,comment,attachment,project_id,area_id,id_motivos_cita,id_tipo_cita,id_dia_espera,internal_state_id,external_state_id,type_id,ticket,channel',
                'details.area:id_area,descripcion',
                'details.project:id_proyecto,descripcion',
                'details.motivoCita:id_motivos_cita,nombre_motivo',
                'details.tipoCita:id_tipo_cita,tipo',
                'details.diaEspera:id_dias_espera,dias',
                'details.internalState:id,description',
                'details.externalState:id,description',
                'details.supportType:id,description',
                'details.type:id,description',
                'details.lastComment.internalState:id,description',
            ]),
        ]);
    }

    private function hasDuplicateSupportDetailWithCode($clientId, $projectId, $subject, $manzana): ?string
    {
        $detail = SupportDetail::whereHas('support', function ($query) use ($clientId) {
            $query->where('client_id', $clientId);
        })
            ->where('project_id', $projectId)
            ->where('subject', $subject)
            ->where('Manzana', $manzana)
            ->first();

        if ($detail) {
            return $detail->ticket ? $detail->ticket : null;
        }

        return null;
    }






    public function show($id)
    {
        $support = Support::with([
            // Cliente y sus ventas con proyecto incluido
            'client:id_cliente,Razon_Social,dni,Telefono,Email,Direccion',
            'client.sales' => function ($query) {
                $query->select('id', 'id_cliente', 'project_id', 'mz_lote')
                    ->with('project:id_proyecto,descripcion');
            },

            // Usuario que creó
            'creator:id,firstname,lastname,names',

            // Detalles del soporte y sus relaciones
            'details',
            'details.area:id_area,descripcion',
            'details.project:id_proyecto,descripcion',
            'details.motivoCita:id_motivos_cita,nombre_motivo',
            'details.tipoCita:id_tipo_cita,tipo',
            'details.diaEspera:id_dias_espera,dias',
            'details.internalState:id,description',
            'details.externalState:id,description',
            'details.supportType:id,description',
            'details.type:id,description',
            'details.lastComment.internalState:id,description',
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
            'priority' => 'required|in:Baja,Media,Alta',
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
