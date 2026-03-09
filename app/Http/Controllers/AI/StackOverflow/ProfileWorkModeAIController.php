<?php

namespace App\Http\Controllers\AI\StackOverflow;

use App\Http\Controllers\Controller;
use App\Models\StackOverflowSurvey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileWorkModeAIController extends Controller
{
   public function metadata()
{
    return response()->json([
        'years' => StackOverflowSurvey::select('year')->distinct()->orderBy('year', 'desc')->pluck('year'),
        'countries' => StackOverflowSurvey::select('country')->whereNotNull('country')->distinct()->orderBy('country')->pluck('country'),
        'industries' => StackOverflowSurvey::select('industry')->whereNotNull('industry')->distinct()->orderBy('industry')->pluck('industry'),
        'ed_levels' => StackOverflowSurvey::select('ed_level')->whereNotNull('ed_level')->distinct()->orderBy('ed_level')->pluck('ed_level'),
        'employment' => StackOverflowSurvey::select('employment')->whereNotNull('employment')->distinct()->orderBy('employment')->pluck('employment'),
    ]);
}


public function getData(Request $request)
{
    $year = $request->get('year', 2026);
    $countries = $request->get('countries', []);
    $industries = $request->get('industries', []);
    $ed_levels = $request->get('ed_levels', []);
    $employment = $request->get('employment', []);

    $query = StackOverflowSurvey::query()->where('year', $year);

    if (!empty($countries)) $query->whereIn('country', $countries);
    if (!empty($industries)) $query->whereIn('industry', $industries);
    if (!empty($ed_levels)) $query->whereIn('ed_level', $ed_levels);
    if (!empty($employment)) $query->whereIn('employment', $employment);

    // ✅ Normalización de valores viejos y nuevos
    $workModes = $query
        ->selectRaw("
            CASE
                WHEN remote_work LIKE 'Hybrid%' OR remote_work LIKE 'Your choice%' THEN 'Hybrid'
                WHEN remote_work LIKE 'Remote%' THEN 'Remote'
                WHEN remote_work LIKE 'In-person%' THEN 'In-person'
                ELSE 'Other'
            END as mode,
            COUNT(*) as total
        ")
        ->whereNotNull('remote_work')
        ->groupBy('mode')
        ->orderBy('mode')
        ->get();

    // 🔹 Calcular totales y porcentajes
    $total = $workModes->sum('total');

    $formatted = $workModes->map(function ($row) use ($total) {
        return [
            'name' => $row->mode,
            'total' => $row->total,
            'percent' => $total > 0 ? round(($row->total / $total) * 100, 1) : 0,
        ];
    });

    return response()->json([
        'year' => $year,
        'total' => $total,
        'work_modes' => $formatted,
    ]);
}




}
