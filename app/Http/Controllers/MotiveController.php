<?php

namespace App\Http\Controllers;

use App\Models\Motive;
use App\Models\AppointmentType;
use App\Models\WaitingDay;
use App\Models\Area;
use Illuminate\Http\Request;
use Inertia\Inertia;
  use Illuminate\Support\Facades\Log;
class MotiveController extends Controller
{
    public function index()
    {
        $motives = Motive::with(['tipoCita:id_tipo_cita,tipo', 'diaEspera:id_dias_espera,dias', 'area:id_area,descripcion'])
            ->orderByDesc('id_motivos_cita')
            ->paginate(15);

        return Inertia::render('motives/index', [
            'motives' => $motives->through(function ($motive) {
                return [
                    'id_motivos_cita' => $motive->id_motivos_cita,
                    'nombre_motivo' => $motive->nombre_motivo,
                    'id_tipo_cita' => $motive->id_tipo_cita,
                    'id_dia_espera' => $motive->id_dia_espera,
                    'id_area' => $motive->id_area,
                    'habilitado' => (bool) $motive->habilitado,
                    'tipoCita' => $motive->tipoCita ? ['tipo' => $motive->tipoCita->tipo] : null,
                    'diaEspera' => $motive->diaEspera ? ['dias' => $motive->diaEspera->dias] : null,
                    'area' => $motive->area ? ['descripcion' => $motive->area->descripcion] : null,
                ];
            }),
            'appointmentTypes' => AppointmentType::all(['id_tipo_cita', 'tipo']),
            'waitingDays' => WaitingDay::all(['id_dias_espera', 'dias']),
            'areas' => Area::all(['id_area', 'descripcion']),
        ]);

    }

public function fetchPaginated()
{
    $motives = Motive::with(['tipoCita', 'diaEspera', 'area'])
        ->orderByDesc('id_motivos_cita')
        ->paginate(10);

    $formatted = $motives->through(function ($motive) {
        return [
            'id_motivos_cita' => $motive->id_motivos_cita,
            'nombre_motivo' => $motive->nombre_motivo,
            'id_tipo_cita' => $motive->id_tipo_cita,
            'id_dia_espera' => $motive->id_dia_espera,
            'id_area' => $motive->id_area,
            'habilitado' => (bool) $motive->habilitado,
            'tipoCita' => $motive->tipoCita ? ['tipo' => $motive->tipoCita->tipo] : null,
            'diaEspera' => $motive->diaEspera ? ['dias' => $motive->diaEspera->dias] : null,
            'area' => $motive->area ? ['descripcion' => $motive->area->descripcion] : null,
        ];
    });

    return response()->json($formatted);
}







public function store(Request $request)
{
    $validated = $request->validate([
        'nombre_motivo' => 'required|string|max:255',
        'id_tipo_cita' => 'nullable|exists:tipos_cita,id_tipo_cita',
        'id_dia_espera' => 'nullable|exists:dias_espera,id_dias_espera',
        'id_area' => 'required|exists:areas,id_area',
        'habilitado' => 'required|boolean',
    ]);

    $validated['id_areap'] = 1; // Se fuerza desde backend

    // Log opcional
    Log::info('🎯 Creando motivo de cita con datos:', $validated);

    $motive = Motive::create($validated);

    return response()->json([
        'message' => '✅ Motivo creado correctamente',
        'motive' => $motive->load(['tipoCita', 'diaEspera', 'area']),
    ]);
}


    public function show($id)
    {
        $motive = Motive::with(['tipoCita', 'diaEspera', 'area'])->findOrFail($id);
        return response()->json(['motive' => $motive]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'nombre_motivo' => 'required|string|max:255',
            'id_tipo_cita' => 'nullable|exists:tipos_cita,id_tipo_cita',
            'id_dia_espera' => 'nullable|exists:dias_espera,id_dias_espera',
            'id_area' => 'required|exists:areas,id_area',
            'habilitado' => 'required|boolean',
        ]);

        $motive = Motive::findOrFail($id);
        $motive->update($validated);

        return response()->json([
            'message' => 'Motivo actualizado correctamente',
            'motive' => $motive->load(['tipoCita', 'diaEspera', 'area']),
        ]);
    }

    public function destroy($id)
    {
        $motive = Motive::findOrFail($id);
        $motive->delete();

        return response()->json(['message' => 'Motivo eliminado correctamente']);
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        Motive::whereIn('id_motivos_cita', $ids)->delete();

        return response()->json(['message' => 'Motivos eliminados correctamente']);
    }
}
