<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request, $id)
    {
        $payload = $this->buildPayload($request, $id);

        return Inertia::render('reports/index', $payload);
    }

    public function fetchPaginated(Request $request, $id)
    {
        return response()->json($this->buildPayload($request, $id));
    }

    protected function buildPayload(Request $request, $id): array
    {
        $survey = Survey::findOrFail($id);

        $questions = DB::table('survey_details')
            ->where('survey_id', $survey->id)
            ->where('visible', 'yes')
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['id', 'question']);

        $totalQuestions = $questions->count();
        $stats = $this->participantStats($survey->id);

        $perPage = in_array((int) $request->query('per_page', 25), [25, 50, 100], true)
            ? (int) $request->query('per_page', 25)
            : 25;

        $onlyIncomplete = $request->boolean('only_incomplete');
        $filtered = $stats->filter(function ($row) use ($totalQuestions, $onlyIncomplete) {
            $answered = (int) $row->answered;

            return !$onlyIncomplete || $answered < $totalQuestions;
        })->values();

        $page = max((int) $request->query('page', 1), 1);
        $lastPage = $filtered->isEmpty() ? 1 : (int) ceil($filtered->count() / $perPage);
        $page = min($page, $lastPage);

        $offset = ($page - 1) * $perPage;
        $pagedClientIds = $filtered->slice($offset, $perPage)->pluck('client_id')->values()->all();

        $results = $this->rowsForClients($survey->id, $questions, $pagedClientIds);

        return [
            'survey' => [
                'id' => (int) $survey->id,
                'title' => $survey->title,
            ],
            'questions' => $questions->map(function ($question) {
                return [
                    'id' => (int) $question->id,
                    'question' => $question->question,
                ];
            })->all(),
            'totalQuestions' => $totalQuestions,
            'results' => $results,
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $filtered->count(),
            ],
            'summary' => [
                'participants' => $stats->count(),
                'incomplete' => $stats->filter(function ($row) use ($totalQuestions) {
                    return (int) $row->answered < $totalQuestions;
                })->count(),
            ],
            'filters' => [
                'only_incomplete' => $onlyIncomplete,
                'per_page' => $perPage,
            ],
        ];
    }

    protected function participantStats($surveyId)
    {
        return DB::table('clients as c')
            ->join('survey_clients as sc', 'sc.client_id', '=', 'c.id')
            ->join('survey_details as sd', 'sd.id', '=', 'sc.survey_detail_id')
            ->where('sd.survey_id', $surveyId)
            ->where('sd.visible', 'yes')
            ->whereNotNull('c.completed_at')
            ->groupBy('c.id')
            ->select(
                'c.id as client_id',
                DB::raw('COUNT(CASE WHEN sc.answer IS NOT NULL AND TRIM(CAST(sc.answer AS CHAR)) <> "" AND sc.answer <> "no_respondido" THEN 1 END) as answered')
            )
            ->get();
    }

    protected function rowsForClients($surveyId, $questions, array $clientIds): array
    {
        if ($questions->isEmpty() || empty($clientIds)) {
            return [];
        }

        $selects = [
            'c.id as client_id',
            DB::raw('COUNT(CASE WHEN sc.answer IS NOT NULL AND TRIM(CAST(sc.answer AS CHAR)) <> "" AND sc.answer <> "no_respondido" THEN 1 END) as answered'),
        ];

        foreach ($questions as $question) {
            $questionId = (int) $question->id;
            $selects[] = DB::raw("MAX(CASE WHEN sd.id = {$questionId} THEN sc.answer END) AS answer_{$questionId}");
        }

        $rows = DB::table('clients as c')
            ->join('survey_clients as sc', 'sc.client_id', '=', 'c.id')
            ->join('survey_details as sd', 'sd.id', '=', 'sc.survey_detail_id')
            ->where('sd.survey_id', $surveyId)
            ->where('sd.visible', 'yes')
            ->whereNotNull('c.completed_at')
            ->whereIn('c.id', $clientIds)
            ->groupBy('c.id')
            ->select($selects)
            ->orderBy('c.id')
            ->get();

        return $rows->map(function ($row) use ($questions) {
            $payload = [
                'client_id' => (int) $row->client_id,
                'answered' => (int) $row->answered,
            ];

            foreach ($questions as $question) {
                $questionId = (int) $question->id;
                $payload["answer_{$questionId}"] = property_exists($row, "answer_{$questionId}") ? $row->{"answer_{$questionId}"} : null;
            }

            return $payload;
        })->all();
    }
}
