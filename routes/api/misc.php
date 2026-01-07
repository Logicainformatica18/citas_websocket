<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\JobStatsController;
use App\Http\Controllers\SQLDashboardController;
use App\Http\Controllers\SegmentAnalyzerController;

Route::get('/job-stats', [JobStatsController::class, 'index']);
Route::post('/sqltrainings/dashboard/execute', [SQLDashboardController::class, 'runAll']);

Route::post('/ai/dashboard-widgets/{id}/segment', [SegmentAnalyzerController::class, 'segmentData']);
Route::get('/segment/analyze/{id}', [SegmentAnalyzerController::class, 'analyze']);
Route::post('/segment/execute/{id}', [SegmentAnalyzerController::class, 'execute']);

Route::get('/chart-types', function () {
    return DB::table('chart_types')->select('id','name','slug')->orderBy('id')->get();
});
