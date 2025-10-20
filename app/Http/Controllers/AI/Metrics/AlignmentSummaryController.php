<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class AlignmentSummaryController extends Controller
{
    public function index()
    {
        $summary = [
            'languages' => DB::table('language_metrics')->max('run_date'),
            'technologies' => DB::table('technology_metrics')->max('run_date'),
            'methodologies' => DB::table('methodology_metrics')->max('run_date'),
        ];

        $averages = [
            'languages' => DB::table('language_metrics')->avg('jobs_found_count'),
            'technologies' => DB::table('technology_metrics')->avg('jobs_found_count'),
            'methodologies' => DB::table('methodology_metrics')->avg('jobs_found_count'),
        ];

        return inertia('Dashboard/AlignmentSummary', compact('summary', 'averages'));
    }
}
