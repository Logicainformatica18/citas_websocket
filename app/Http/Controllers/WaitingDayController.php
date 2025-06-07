<?php

namespace App\Http\Controllers;

use App\Models\WaitingDay;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WaitingDayController extends Controller
{
    public function index()
    {
        $waitingDays = WaitingDay::orderBy('id_dias_espera', 'desc')->paginate(10);
        return Inertia::render('waiting-days/index', ['waitingDays' => $waitingDays]);
    }

    public function fetchPaginated()
    {
        return response()->json(WaitingDay::orderBy('id_dias_espera', 'desc')->paginate(10));
    }

    public function store(Request $request)
    {
        $request->validate([
            'dias' => 'required|string|max:255',
            'habilitado' => 'required|boolean',
        ]);

        $item = WaitingDay::create($request->only('dias', 'habilitado'));

        return response()->json([
            'message' => '✅ Waiting day created',
            'waitingDay' => $item,
        ]);
    }

    public function update(Request $request, $id)
    {
        $item = WaitingDay::findOrFail($id);

        $request->validate([
            'dias' => 'required|string|max:255',
            'habilitado' => 'required|boolean',
        ]);

        $item->update($request->only('dias', 'habilitado'));

        return response()->json([
            'message' => '✅ Updated',
            'waitingDay' => $item,
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'waitingDay' => WaitingDay::findOrFail($id),
        ]);
    }

    public function destroy($id)
    {
        WaitingDay::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        WaitingDay::whereIn('id_dias_espera', $ids)->delete();
        return response()->json(['message' => '✅ Deleted']);
    }
}
