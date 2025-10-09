<?php

namespace App\Http\Controllers\AI\StackOverflow;

use App\Http\Controllers\Controller;
use App\Models\StackOverflowSurvey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileAIController extends Controller
{
    public function metadata()
    {
        return response()->json([
            'years' => StackOverflowSurvey::select('year')->distinct()->orderBy('year', 'desc')->pluck('year'),
            'countries' => StackOverflowSurvey::select('country')->whereNotNull('country')->distinct()->pluck('country'),
            'industries' => StackOverflowSurvey::select('industry')->whereNotNull('industry')->distinct()->pluck('industry'),
        ]);
    }

    public function getData(Request $r)
    {
        $year = $r->get('year', 2024);
        $country = $r->get('country');
        $industry = $r->get('industry');

        $query = StackOverflowSurvey::where('year', $year);

        if ($country) $query->where('country', $country);
        if ($industry) $query->where('industry', $industry);

        return response()->json([
            'work_modes' => $query->select('remote_work', DB::raw('COUNT(*) as total'))
                ->whereNotNull('remote_work')->groupBy('remote_work')->pluck('total', 'remote_work'),
            'education' => $query->select('ed_level', DB::raw('COUNT(*) as total'))
                ->whereNotNull('ed_level')->groupBy('ed_level')->pluck('total', 'ed_level'),
            'age_groups' => $query->select('age', DB::raw('COUNT(*) as total'))
                ->whereNotNull('age')->groupBy('age')->pluck('total', 'age'),
        ]);
    }
}
