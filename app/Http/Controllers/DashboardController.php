<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $data = [
            'summary' => $this->summary(),
            'surveyBreakdown' => $this->surveyBreakdown(),
            'recentActivity' => $this->recentActivity(),
            'alerts' => $this->alerts(),
        ];

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return Inertia::render('dashboard', $data);
    }

    private function summary(): array
    {
        $surveys = DB::table('surveys');

        $totalSurveys = (clone $surveys)->count();
        $publicSurveys = (clone $surveys)->where('visible', 1)->count();
        $hiddenSurveys = (clone $surveys)->where('visible', 0)->count();
        $respondents = DB::table('clients')->whereNotNull('completed_at')->count();
        $submittedAnswers = DB::table('survey_clients')->count();
        $dueSoon = (clone $surveys)
            ->whereNotNull('date_end')
            ->where('date_end', '>=', now()->toDateString())
            ->where('date_end', '<=', now()->addDays(7)->toDateString())
            ->count();
        $expired = (clone $surveys)
            ->whereNotNull('date_end')
            ->where('date_end', '<', now()->toDateString())
            ->count();

        return [
            'total_surveys' => (int) $totalSurveys,
            'public_surveys' => (int) $publicSurveys,
            'hidden_surveys' => (int) $hiddenSurveys,
            'respondents' => (int) $respondents,
            'submitted_answers' => (int) $submittedAnswers,
            'due_soon' => (int) $dueSoon,
            'expired' => (int) $expired,
        ];
    }

    private function surveyBreakdown(): array
    {
        return DB::table('surveys as s')
            ->leftJoin('survey_details as sd', 'sd.survey_id', '=', 's.id')
            ->leftJoin('survey_clients as sc', 'sc.survey_detail_id', '=', 'sd.id')
            ->leftJoin('clients as c', 'c.id', '=', 'sc.client_id')
            ->select(
                's.id',
                's.title',
                's.state',
                's.date_start',
                's.date_end',
                DB::raw('COUNT(DISTINCT sd.id) as questions'),
                DB::raw('COUNT(DISTINCT sc.id) as answers'),
                DB::raw('COUNT(DISTINCT CASE WHEN c.completed_at IS NOT NULL THEN c.id END) as respondents')
            )
            ->groupBy('s.id', 's.title', 's.state', 's.date_start', 's.date_end')
            ->orderByDesc('s.id')
            ->get()
            ->map(function ($survey) {
                return [
                    'id' => (int) $survey->id,
                    'title' => $survey->title,
                    'state' => $survey->state ?? 'public',
                    'date_start' => $survey->date_start,
                    'date_end' => $survey->date_end,
                    'questions' => (int) $survey->questions,
                    'answers' => (int) $survey->answers,
                    'respondents' => (int) $survey->respondents,
                ];
            })
            ->all();
    }

    private function recentActivity(): array
    {
        return DB::table('survey_clients as sc')
            ->join('survey_details as sd', 'sd.id', '=', 'sc.survey_detail_id')
            ->join('surveys as s', 's.id', '=', 'sd.survey_id')
            ->join('clients as c', 'c.id', '=', 'sc.client_id')
            ->select(
                's.id as survey_id',
                's.title as survey_title',
                'c.completed_at as completed_at',
                DB::raw('COUNT(sc.id) as answers')
            )
            ->whereNotNull('c.completed_at')
            ->groupBy('s.id', 's.title', 'c.completed_at')
            ->orderByDesc('c.completed_at')
            ->limit(6)
            ->get()
            ->map(function ($item) {
                return [
                    'survey_id' => (int) $item->survey_id,
                    'survey_title' => $item->survey_title,
                    'completed_at' => $item->completed_at,
                    'answers' => (int) $item->answers,
                ];
            })
            ->all();
    }

    private function alerts(): array
    {
        $alerts = [];

        $dueSoon = DB::table('surveys')
            ->whereNotNull('date_end')
            ->where('date_end', '>=', now()->toDateString())
            ->where('date_end', '<=', now()->addDays(7)->toDateString())
            ->orderBy('date_end')
            ->get();

        foreach ($dueSoon as $survey) {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'Encuesta próxima a cerrar',
                'message' => sprintf('%s vence el %s.', $survey->title, \Carbon\Carbon::parse($survey->date_end)->translatedFormat('d/m/Y')),
            ];
        }

        $expired = DB::table('surveys')
            ->whereNotNull('date_end')
            ->where('date_end', '<', now()->toDateString())
            ->orderBy('date_end')
            ->get();

        foreach ($expired as $survey) {
            $alerts[] = [
                'level' => 'danger',
                'title' => 'Encuesta vencida',
                'message' => sprintf('%s ya venció el %s.', $survey->title, \Carbon\Carbon::parse($survey->date_end)->translatedFormat('d/m/Y')),
            ];
        }

        $withoutResponses = DB::table('surveys as s')
            ->leftJoin('survey_details as sd', 'sd.survey_id', '=', 's.id')
            ->leftJoin('survey_clients as sc', 'sc.survey_detail_id', '=', 'sd.id')
            ->select('s.id', 's.title', DB::raw('COUNT(DISTINCT sc.id) as answers'))
            ->groupBy('s.id', 's.title')
            ->havingRaw('COUNT(DISTINCT sc.id) = 0')
            ->orderBy('s.id')
            ->get();

        foreach ($withoutResponses as $survey) {
            $alerts[] = [
                'level' => 'info',
                'title' => 'Encuesta sin respuestas',
                'message' => sprintf('%s aún no recibió respuestas.', $survey->title),
            ];
        }

        if (empty($alerts)) {
            $alerts[] = [
                'level' => 'success',
                'title' => 'Sistema estable',
                'message' => 'No hay alertas activas: encuestas vigentes y con actividad normal.',
            ];
        }

        return array_slice($alerts, 0, 4);
    }
}
