<?php

namespace App\Http\Controllers\AI\StackOverflow;

use App\Http\Controllers\Controller;
use App\Models\StackOverflowSurvey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileAgeAIController extends Controller
{
    public function metadata()
    {
        return response()->json([
            'years' => StackOverflowSurvey::select('year')->distinct()->orderBy('year', 'desc')->pluck('year'),
            'countries' => StackOverflowSurvey::select('country')->whereNotNull('country')->distinct()->orderBy('country')->pluck('country'),
        ]);
    }

    public function getData(Request $request)
    {
        $year = $request->get('year', 2024);
        $countries = $request->get('countries', []);

        $query = StackOverflowSurvey::query()->where('year', $year);
        if (!empty($countries)) $query->whereIn('country', $countries);

        $ages = $query
            ->select('age', DB::raw('COUNT(*) as total'))
            ->whereNotNull('age')
            ->groupBy('age')
            ->pluck('total', 'age');

        return response()->json([
            'total' => $query->count(),
            'age_groups' => $ages,
        ]);
    }
}
