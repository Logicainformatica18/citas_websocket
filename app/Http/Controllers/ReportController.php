<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index($id)
    {
        $survey = Survey::findOrFail($id);
        $questions = DB::table('survey_details')
            ->where('survey_id', $survey->id)
            ->where('visible', 'yes')
            ->orderBy('created_at')
            ->get(['id', 'question']);

        $selects = ['sc.client_id'];
        $having = [];

        foreach ($questions as $question) {
            $questionId = (int) $question->id;
            $selects[] = DB::raw("MAX(CASE WHEN sd.id = {$questionId} THEN sc.answer END) AS answer_{$questionId}");
            $selects[] = DB::raw("MAX(CASE WHEN sd.id = {$questionId} THEN sc.option END) AS option_{$questionId}");
            $having[] = "MAX(CASE WHEN sd.id = {$questionId} THEN sc.answer END) IS NOT NULL";
        }

        $results = collect();
        if ($questions->isNotEmpty()) {
            $results = DB::table('survey_clients as sc')
                ->join('survey_details as sd', 'sc.survey_detail_id', '=', 'sd.id')
                ->where('sd.survey_id', $survey->id)
                ->groupBy('sc.client_id')
                ->havingRaw(implode(' OR ', $having))
                ->orderByDesc('sc.client_id')
                ->select($selects)
                ->get();
        }

        return Inertia::render('reports/index', [
            'survey' => $survey,
            'questions' => $questions,
            'results' => $results,
        ]);
    }
}