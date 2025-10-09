<?php

namespace App\Http\Controllers\AI\StackOverflow;

use App\Http\Controllers\Controller;
use App\Models\StackOverflowSurvey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiOpinionsAIController extends Controller
{
    public function getData(Request $r)
    {
        $year = $r->get('year', 2024);
        $country = $r->get('country');

        $query = StackOverflowSurvey::where('year', $year);
        if ($country) $query->where('country', $country);

        return response()->json([
            'ai_sentiment' => $query->select('ai_sentiment', DB::raw('COUNT(*) as total'))
                ->whereNotNull('ai_sentiment')->groupBy('ai_sentiment')->pluck('total', 'ai_sentiment'),
            'job_satisfaction' => $query->select('job_satisfaction', DB::raw('COUNT(*) as total'))
                ->whereNotNull('job_satisfaction')->groupBy('job_satisfaction')->pluck('total', 'job_satisfaction'),
        ]);
    }
}
