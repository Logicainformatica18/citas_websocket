<?php

namespace App\Http\Controllers;

use App\Models\Motive;
use App\Models\AppointmentType;
use App\Models\WaitingDay;
use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MotiveController extends Controller
{
    public function index()
    {
        $motives = Motive::with([
                'tipoCita:id_tipo_cita,tipo',
                'diaEspera:id_dias_espera,dias',
                'area:id_area,descripcion',          // área “principal”
                'areas:id_area,descripcion',         // áreas por pivote
            ])
            ->orderBy('nombre_motivo')
            ->paginate(7);

        return Inertia::render('motives/index', [
            'motives' => $motives->through(function ($motive) {
                return [
                    'id_motivos_cita' => $motive->id_motivos_cita,
                    'nombre_motivo'   => $motive->nombre_motivo,
                    'detail'          => $motive->detail,
                    'detail_2'        => $motive->detail_2,
                    'id_tipo_cita'    => $motive->id_tipo_cita,
                    'id_dia_espera'   => $motive->id_dia_espera,
                    'id_area'         => $motive->id_area,
                    'habilitado'      => (bool) $motive->habilitado,
                    'tipoCita'        => $motive->tipoCita?->only(['id_tipo_cita','tipo']),
                    'diaEspera'       => $motive->diaEspera?->only(['id_dias_espera','dias']),
                    'area'            => $motive->area?->only(['id_area','descripcion']),
                    'areas_pivot'     => $motive->areas->map(fn($a)=>$a->only(['id_area','descripcion']))->values(),
                ];
            }),
            'appointmentTypes' => AppointmentType::all(['id_tipo_cita', 'tipo']),
            'waitingDays'      => WaitingDay::all(['id_dias_espera', 'dias']),
            'areas'            => Area::orderBy('descripcion')->get(['id_area', 'descripcion']),
        ]);
    }

    public function fetchPaginated()
    {
        $motives = Motive::with(['tipoCita','diaEspera','area','areas'])
             ->orderBy('nombre_motivo')
            ->paginate(7);

        $formatted = $motives->through(function ($motive) {
            return [
                'id_motivos_cita' => $motive->id_motivos_cita,
                'nombre_motivo'   => $motive->nombre_motivo,
                'detail'          => $motive->detail,
                'detail_2'        => $motive->detail_2,
                'id_tipo_cita'    => $motive->id_tipo_cita,
                'id_dia_espera'   => $motive->id_dia_espera,
                'id_area'         => $motive->id_area,
                'habilitado'      => (bool) $motive->habilitado,
                'tipoCita'        => $motive->tipoCita?->only(['id_tipo_cita','tipo']),
                'diaEspera'       => $motive->diaEspera?->only(['id_dias_espera','dias']),
                'area'            => $motive->area?->only(['id_area','descripcion']),
                'areas_pivot'     => $motive->areas->map(fn($a)=>$a->only(['id_area','descripcion']))->values(),
            ];
        });

        return response()->json($formatted);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_motivo' => 'required|string|max:255',
            'detail'        => 'nullable|string',
            'detail_2'      => 'nullable|string',
            'id_tipo_cita'  => 'nullable|exists:tipos_cita,id_tipo_cita',
            'id_dia_espera' => 'nullable|exists:dias_espera,id_dias_espera',
            'id_area'       => 'required|exists:areas,id_area',  // área principal
            'habilitado'    => 'required|boolean',
            'areas_ids'     => 'array',                          // áreas por pivote
            'areas_ids.*'   => 'integer|exists:areas,id_area',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $motive = Motive::create($validated);

            // áreas N:M independientes (NO incluye el id_area principal)
            $areasIds = array_map('intval', (array) $request->input('areas_ids', []));
            $motive->areas()->sync(array_values(array_unique($areasIds)));

            $motive->load(['tipoCita','diaEspera','area','areas:id_area,descripcion']);

            return response()->json([
                'message' => '✅ Motivo creado correctamente',
                'motive'  => [
                    'id_motivos_cita' => $motive->id_motivos_cita,
                    'nombre_motivo'   => $motive->nombre_motivo,
                    'detail'          => $motive->detail,
                    'detail_2'        => $motive->detail_2,
                    'id_tipo_cita'    => $motive->id_tipo_cita,
                    'id_dia_espera'   => $motive->id_dia_espera,
                    'id_area'         => $motive->id_area,
                    'habilitado'      => (bool) $motive->habilitado,
                    'tipoCita'        => $motive->tipoCita?->only(['id_tipo_cita','tipo']),
                    'diaEspera'       => $motive->diaEspera?->only(['id_dias_espera','dias']),
                    'area'            => $motive->area?->only(['id_area','descripcion']),
                    'areas_pivot'     => $motive->areas->map(fn($a)=>$a->only(['id_area','descripcion']))->values(),
                ],
            ], 201);
        });
    }

    public function show($id)
    {
        $motive = Motive::with(['tipoCita','diaEspera','area','areas:id_area,descripcion'])
            ->findOrFail($id);

        return response()->json([
            'motive' => [
                'id_motivos_cita' => $motive->id_motivos_cita,
                'nombre_motivo'   => $motive->nombre_motivo,
                'detail'          => $motive->detail,
                'detail_2'        => $motive->detail_2,
                'id_tipo_cita'    => $motive->id_tipo_cita,
                'id_dia_espera'   => $motive->id_dia_espera,
                'id_area'         => $motive->id_area,
                'habilitado'      => (bool) $motive->habilitado,
                'tipoCita'        => $motive->tipoCita?->only(['id_tipo_cita','tipo']),
                'diaEspera'       => $motive->diaEspera?->only(['id_dias_espera','dias']),
                'area'            => $motive->area?->only(['id_area','descripcion']),
                'areas_pivot'     => $motive->areas->map(fn($a)=>$a->only(['id_area','descripcion']))->values(),
                'areas_ids'       => $motive->areas->pluck('id_area'), // para checkboxes
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'nombre_motivo' => 'required|string|max:255',
            'detail'        => 'nullable|string',
            'detail_2'      => 'nullable|string',
            'id_tipo_cita'  => 'nullable|exists:tipos_cita,id_tipo_cita',
            'id_dia_espera' => 'nullable|exists:dias_espera,id_dias_espera',
            'id_area'       => 'required|exists:areas,id_area',
            'habilitado'    => 'required|boolean',
            'areas_ids'     => 'array',
            'areas_ids.*'   => 'integer|exists:areas,id_area',
        ]);

        return DB::transaction(function () use ($id, $validated, $request) {
            $motive = Motive::findOrFail($id);
            $motive->update($validated);

            // áreas N:M independientes (NO incluye el id_area principal)
            $areasIds = array_map('intval', (array) $request->input('areas_ids', []));
            $motive->areas()->sync(array_values(array_unique($areasIds)));

            $motive->load(['tipoCita','diaEspera','area','areas:id_area,descripcion']);

            return response()->json([
                'message' => '✅ Motivo actualizado correctamente',
                'motive'  => [
                    'id_motivos_cita' => $motive->id_motivos_cita,
                    'nombre_motivo'   => $motive->nombre_motivo,
                    'detail'          => $motive->detail,
                    'detail_2'        => $motive->detail_2,
                    'id_tipo_cita'    => $motive->id_tipo_cita,
                    'id_dia_espera'   => $motive->id_dia_espera,
                    'id_area'         => $motive->id_area,
                    'habilitado'      => (bool) $motive->habilitado,
                    'tipoCita'        => $motive->tipoCita?->only(['id_tipo_cita','tipo']),
                    'diaEspera'       => $motive->diaEspera?->only(['id_dias_espera','dias']),
                    'area'            => $motive->area?->only(['id_area','descripcion']),
                    'areas_pivot'     => $motive->areas->map(fn($a)=>$a->only(['id_area','descripcion']))->values(),
                ],
            ]);
        });
    }

    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $motive = Motive::findOrFail($id);

            $motive->areas()->detach();
            $motive->delete();

            return response()->json(['message' => 'Motivo eliminado correctamente']);
        });
    }

    public function bulkDelete(Request $request)
    {
        $ids = (array) $request->input('ids', []);

        return DB::transaction(function () use ($ids) {
            $motives = Motive::whereIn('id_motivos_cita', $ids)->get();

            foreach ($motives as $m) {
                $m->areas()->detach();
                $m->delete();
            }

            return response()->json(['message' => 'Motivos eliminados correctamente']);
        });
    }

    public function syncAreas(Request $request, $id)
    {
        $validated = $request->validate([
            'areas_ids'   => 'array',
            'areas_ids.*' => 'integer|exists:areas,id_area',
        ]);

        $motive = Motive::findOrFail($id);

        $areasIds = array_map('intval', (array) $request->input('areas_ids', []));
        $motive->areas()->sync(array_values(array_unique($areasIds)));

        return response()->json([
            'message'    => 'Áreas sincronizadas correctamente',
            'areas_ids'  => $motive->areas()->pluck('areas.id_area'),
        ]);
    }

    public function getAllEnabled()
    {
        $motives = Motive::get(['id_motivos_cita as id', 'nombre_motivo']);
        return response()->json($motives);
    }
}
