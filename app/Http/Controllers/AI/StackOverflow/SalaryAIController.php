<?php

namespace App\Http\Controllers\AI\StackOverflow;

use App\Http\Controllers\Controller;
use App\Models\StackOverflowSurvey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalaryAIController extends Controller
{
    public function getData(Request $r)
    {
        $year = $r->get('year', 2024);
        $country = $r->get('country');
        $remote = $r->get('remote_work');

        $query = StackOverflowSurvey::where('year', $year);

        if ($country) $query->where('country', $country);
        if ($remote) $query->where('remote_work', $remote);

        return response()->json([
            'avg_salary' => round($query->whereNotNull('comp_total')->avg('comp_total'), 2),
            'experience_avg' => round($query->whereNotNull('years_code_pro')->avg(DB::raw('CAST(years_code_pro AS DECIMAL(5,2))')), 2),
            'salary_by_remote' => $query->select('remote_work', DB::raw('AVG(comp_total) as avg_salary'))
                ->whereNotNull('remote_work')->groupBy('remote_work')->pluck('avg_salary', 'remote_work'),
        ]);
    }
}
