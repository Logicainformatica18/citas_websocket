<?php

namespace App\Http\Controllers;

use App\Models\Type;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TypeController extends Controller
{
    public function index()
    {
        $types = Type::orderByDesc('id')->paginate(10);

        return Inertia::render('types/index', [
            'types' => $types,
        ]);
    }

    public function fetchPaginated()
    {
        return response()->json(
            Type::orderByDesc('id')->paginate(10)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'detail' => 'nullable|string',
        ]);

        $type = Type::create($validated);

        return response()->json(['message' => '✅ Type created', 'type' => $type]);
    }

    public function update(Request $request, $id)
    {
        $type = Type::findOrFail($id);

        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'detail' => 'nullable|string',
        ]);

        $type->update($validated);

        return response()->json(['message' => '✅ Updated', 'type' => $type]);
    }

    public function show($id)
    {
        return response()->json(['type' => Type::findOrFail($id)]);
    }

public function destroy($id)
{
    $type = Type::findOrFail($id);
    $type->delete();

    return response()->json(['message' => 'Deleted successfully']);
}


    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        Type::whereIn('id', $ids)->delete();
        return response()->json(['message' => '✅ Deleted']);
    }
}
