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
        $areas = Area::select('id_area', 'descripcion')
            ->whereIn('id_area', [1, 2, 7, 10])
            ->get();

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
        $ticketCode = null;
 

        // TR-00048 → ID (support_detail.id)
        if (preg_match('/^tr[-]?0*(\d+)$/i', $query, $matches)) {
            $detailId = (int) $matches[1];
        }

        // TK-00048 → ticket exacto (support_detail.ticket)
        if (preg_match('/^tk[-]?0*(\d+)$/i', $query, $matches)) {
            $ticketCode = 'TK-' . str_pad((int) $matches[1], 5, '0', STR_PAD_LEFT);
        }

        $supportsQuery = Support::with([
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
        ]);

        // 🥇 Prioridad 1: buscar por ticket (TK-xxxx)
        if ($ticketCode) {
            $supportsQuery->whereHas('details', function ($sub) use ($ticketCode) {
                $sub->where('ticket', $ticketCode);
            });
        }
        // 🥈 Prioridad 2: buscar por ID (TR-xxxx)
        elseif ($detailId) {
            $supportsQuery->whereHas('details', function ($sub) use ($detailId) {
                $sub->where('id', $detailId);
            });
        }
        // 🥉 Prioridad 3: búsqueda libre por cliente
        elseif ($query) {
            $supportsQuery->whereHas('client', function ($sub) use ($query) {
                $sub->where('dni', 'like', "%{$query}%")
                    ->orWhere('Razon_Social', 'like', "%{$query}%");
            });
        }

        $supports = $supportsQuery->latest()->paginate(7);

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
        
        $details = json_decode($request->input('details'), true);
       

        try {
            $detailsParsed = json_decode($request->details, true);
            
        } catch (\Throwable $e) {
            Log::error('❌ Error al parsear request->details', [
                'error' => $e->getMessage(),
                'raw' => $request->details,
            ]);
        }


        $details = json_decode($request->details, true);
        $firstDetail = $details[0] ?? null;

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

        $support = Support::create([
            'client_id' => $request->client_id,
            'state' => $request->state,
            'status_global' => $request->status_global ?? 'Incompleto',
            'created_by' => Auth::id(),
        ]);

        if (!is_array($details) || !$firstDetail) {
        
            return response()->json(['message' => 'Detalles inválidos'], 422);
        }

        $attachments = $request->file('attachments'); // puede ser array o null
        $generateTicket = auth()->user()?->can('generar_ticket');
        $ticket = $generateTicket ? (new SupportDetailController)->generateNextSupportTicket() : null;

        $channelName = null;

        $permissions = Auth::user()->getAllPermissions();

        $channelPermission = $permissions->first(function ($perm) {
            // Evalúa solo permisos que comienzan con "Canal." y NO contienen guion bajo en la parte del canal
            return str_starts_with($perm->name, 'Canal.')
                && !str_contains(str_replace('Canal.', '', $perm->name), '_');
        });

        $channelName = $channelPermission
            ? str_replace('Canal.', '', $channelPermission->name)
            : ($request->input('channel') ?? null);


        $attachment = isset($attachments[0]) ? fileStore($attachments[0], 'uploads') : null;

        $createdDetail = $support->details()->create([
            'subject' => $firstDetail['subject'],
            'description' => $firstDetail['description'] ?? null,
            'priority' => $firstDetail['priority'] ?? 'Baja',
            'type' => $firstDetail['type'] ?? 'Consulta',
            'status' => $firstDetail['status'] ?? 'Pendiente',
            'reservation_time' => isset($firstDetail['reservation_time']) ? Carbon::parse($firstDetail['reservation_time']) : now(),
            'attended_at' => isset($firstDetail['attended_at']) ? Carbon::parse($firstDetail['attended_at']) : now()->addHour(),
            'derived' => $firstDetail['derived'] ?? null,
            'project_id' => $firstDetail['project_id'] ?? null,
            'area_id' => $firstDetail['area_id'] ?? 1,
            'id_motivos_cita' => $firstDetail['id_motivos_cita'] ?? null,
            'id_tipo_cita' => $firstDetail['id_tipo_cita'] ?? null,
            'id_dia_espera' => $firstDetail['id_dia_espera'] ?? null,
            'internal_state_id' => $firstDetail['internal_state_id'] ?? 3,
            'external_state_id' => $firstDetail['external_state_id'] ?? 1,
            'type_id' => $firstDetail['type_id'] ?? null,
            'Manzana' => $firstDetail['Manzana'] ?? null,
            'comment' => $firstDetail['comment'] ?? null,
            'attachment' => $attachment,
            'ticket' => $ticket ?? "TK-",
            'channel' => $channelName ?? ($firstDetail['channel'] ?? null),
        ]);

        if ($generateTicket) {
            $createdDetail->update([
                'ticket_start' => Carbon::now('America/Lima'),
            ]);
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

        broadcast(new RecordChanged('Support', 'created', $support->toArray()))->toOthers();

        $clientId = $request->input('client_id');
        $data = $request->only(['dni', 'cellphone', 'email', 'address']);

        dispatch(function () use ($clientId, $data) {
            $client = Client::find($clientId);
            if ($client) {
                $client->updateFromSupport($data);
            }
        });
        ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

        dispatch(function () use ($support) {
            try {
                
                $supportLoaded = $support->load([
                      'client:id_cliente,Razon_Social,Telefono,Email,Direccion,DNI',
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
                ]);

               

                $detail = $supportLoaded->details->first();
                if (!$detail) {
                  
                    return;
                }

                $areaId = $detail->area_id;
                $toEmail = match ($areaId) {
                    1 => 'GESTIONATC@aybarsac.com',                         // ATC
                    2 => 'GESTIONATCLEGAL@aybarsac.com',                    // Legal
                    7 => 'GESTIONATCVIVIENDAPARATODOS@aybarsac.com',        // Vivienda para Todos
                    10 => 'GESTIONATCBO@aybarsac.com',                       // BackOffice
                    default => null,
                };

                // Notificación combinada si el área es Legal
                if (in_array($areaId, [2])) {
                    $toEmail = 'GESTIONATCLEGAL@aybarsac.com';
                }

                if (!$toEmail) {
                    
                    return;
                }

               



                // Enviar notificación
                Notification::route('mail', $toEmail)
                    ->notify(new NewSupportAtcNotification($supportLoaded, 'created'));

                 
                // Notificar al cliente si tiene email válido
                $clientEmail = $supportLoaded->client->email ?? null;

                if ($clientEmail && filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
                   
                    Notification::route('mail', $clientEmail)
                        ->notify(new NewSupportAtcNotification($supportLoaded, 'created'));
                }


            } catch (\Throwable $e) {
                Log::error('[ATC Notification] Error al enviar notificación:', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        });



        ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

        return response()->json([
            'message' => '✅ Ticket de soporte creado correctamente',
            'support' => $support,
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
                if (($detailData['internal_state_id'] ?? null) == 5) {
                    $existingDetail->ticket_end = \Carbon\Carbon::now('America/Lima');

                    $existingDetail->save();
                }

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


       

        // 6. Emitir evento
        broadcast(new RecordChanged('Support', 'updated', $support->toArray()))->toOthers();

        //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        dispatch(function () use ($support) {
            try {
                

                $supportLoaded = $support->load([
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

                $detail = $supportLoaded->details->first();
                if (!$detail) {
                   
                    return;
                }

                $areaId = $detail->area_id;

                $toEmail = match (true) {
                    in_array($areaId, [2]) => 'GESTIONATCLEGAL@aybar.com',
                    $areaId === 7 => 'GESTIONATCVIVIENDAPARATODOS@aybar.com',
                    $areaId === 10 => 'GESTIONATCBO@aybar.com',
                    default => null,
                };

                if (!$toEmail) {
                     
                    return;
                }

                 

                Notification::route('mail', $toEmail)
                    ->notify(new NewSupportAtcNotification($supportLoaded, 'updated'));

                
            } catch (\Throwable $e) {
                Log::error('[ATC Notification] Error al enviar notificación (update):', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        });

        //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

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
