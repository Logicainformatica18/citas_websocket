<?php

namespace App\Http\Controllers;

use App\Models\ExternalState;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExternalStateController extends Controller
{
  public function index()
{
    $externalStates = ExternalState::latest()->paginate(10);

    return Inertia::render('external-states/index', [
        'externalStates' => $externalStates,
    ]);
}


    public function fetchPaginated()
    {
        return response()->json(ExternalState::latest()->paginate(10));
    }

    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'detail' => 'nullable|string',
        ]);

        $externalState = ExternalState::create($request->only('description', 'detail'));

        return response()->json([
            'message' => '✅ External State created successfully',
            'externalState' => $externalState,
        ]);
    }

    public function update(Request $request, $id)
    {
        $externalState = ExternalState::findOrFail($id);

        $request->validate([
            'description' => 'required|string|max:255',
            'detail' => 'nullable|string',
        ]);

        $externalState->update($request->only('description', 'detail'));

        return response()->json([
            'message' => '✅ External State updated successfully',
            'externalState' => $externalState,
        ]);
    }

    public function show($id)
    {
        $externalState = ExternalState::findOrFail($id);
        return response()->json(['externalState' => $externalState]);
    }

    public function destroy($id)
    {
        ExternalState::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        ExternalState::whereIn('id', $ids)->delete();

        return response()->json(['message' => 'External States deleted successfully']);
    }
}
