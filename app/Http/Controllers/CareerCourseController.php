<?php

namespace App\Http\Controllers;

use App\Models\CareerCourse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CareerCourseController extends Controller
{
    public function index()
    {
        $relations = CareerCourse::with(['career', 'course'])
            ->orderBy('id', 'desc')
            ->paginate(10);

        return response()->json($relations->through(fn ($r) => [
            'id'           => $r->id,
            'career'       => $r->career->name ?? null,
            'course'       => $r->course->name ?? null,
            'semester'     => $r->semester,
            'is_mandatory' => $r->is_mandatory,
            'created_at'   => optional($r->created_at)->format('Y-m-d'),
        ]));
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'semester'     => 'nullable|string|max:50',
            'is_mandatory' => 'boolean',
        ]);

        return DB::transaction(function () use ($validated, $id) {
            $relation = CareerCourse::findOrFail($id);
            $relation->update($validated);

            return response()->json(['message' => '✅ Relation updated successfully']);
        });
    }

    public function destroy($id)
    {
        $relation = CareerCourse::findOrFail($id);
        $relation->delete();

        return response()->json(['message' => 'Relation deleted successfully']);
    }
}
