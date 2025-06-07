<?php

namespace App\Http\Controllers;

use App\Models\InternalState;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InternalStateController extends Controller
{
    public function index()
    {
        $internalStates = InternalState::latest()->paginate(10);
        return Inertia::render('internal-states/index', [
            'internalStates' => $internalStates,
        ]);
    }

    public function fetchPaginated()
    {
        return response()->json(InternalState::latest()->paginate(10));
    }

    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'detail' => 'nullable|string',
        ]);

        $internalState = InternalState::create($request->only('description', 'detail'));

        return response()->json([
            'message' => '✅ Internal State created successfully',
            'internalState' => $internalState,
        ]);
    }

    public function update(Request $request, $id)
    {
        $internalState = InternalState::findOrFail($id);

        $request->validate([
            'description' => 'required|string|max:255',
            'detail' => 'nullable|string',
        ]);

        $internalState->update($request->only('description', 'detail'));

        return response()->json([
            'message' => '✅ Internal State updated successfully',
            'internalState' => $internalState,
        ]);
    }

    public function show($id)
    {
        $internalState = InternalState::findOrFail($id);
        return response()->json(['internalState' => $internalState]);
    }

    public function destroy($id)
    {
        InternalState::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        InternalState::whereIn('id', $ids)->delete();

        return response()->json(['message' => 'Internal States deleted successfully']);
    }
}
