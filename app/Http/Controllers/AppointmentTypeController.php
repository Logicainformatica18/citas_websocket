<?php 
namespace App\Http\Controllers;

use App\Models\AppointmentType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AppointmentTypeController extends Controller
{
    public function index()
    {
        $appointmentTypes = AppointmentType::orderBy('id_tipo_cita', 'desc')->paginate(10);
        return Inertia::render('appointment-types/index', ['appointmentTypes' => $appointmentTypes]);
    }

    public function fetchPaginated()
    {
        return response()->json(AppointmentType::orderBy('id_tipo_cita', 'desc')->paginate(10));
    }

    public function store(Request $request)
    {
        $request->validate([
            'tipo' => 'required|string|max:255',
            'habilitado' => 'required|boolean',
        ]);

        $item = AppointmentType::create($request->only('tipo', 'habilitado'));

        return response()->json(['message' => '✅ Appointment Type created', 'appointmentType' => $item]);
    }

    public function update(Request $request, $id)
    {
        $item = AppointmentType::findOrFail($id);
        $request->validate([
            'tipo' => 'required|string|max:255',
            'habilitado' => 'required|boolean',
        ]);

        $item->update($request->only('tipo', 'habilitado'));

        return response()->json(['message' => '✅ Updated', 'appointmentType' => $item]);
    }

    public function show($id)
    {
        return response()->json(['appointmentType' => AppointmentType::findOrFail($id)]);
    }

    public function destroy($id)
    {
        AppointmentType::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        AppointmentType::whereIn('id_tipo_cita', $ids)->delete();
        return response()->json(['message' => '✅ Deleted']);
    }
}
