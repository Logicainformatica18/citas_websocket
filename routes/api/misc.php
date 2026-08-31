<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| API · varios
|--------------------------------------------------------------------------
|
| Se sacaron 5 rutas que apuntaban a controladores borrados en d9d5424:
|
|   GET  /job-stats                          JobStatsController@index
|   POST /sqltrainings/dashboard/execute     SQLDashboardController@runAll
|   POST /ai/dashboard-widgets/{id}/segment  SegmentAnalyzerController@segmentData
|   GET  /segment/analyze/{id}               SegmentAnalyzerController@analyze
|   POST /segment/execute/{id}               SegmentAnalyzerController@execute
|
| Ninguna de las tres clases existe en app/Http/Controllers. Las rutas ya
| respondían 500 ("Target class does not exist") a cualquiera que las
| llamara; lo que además hacían era romper `php artisan route:list` entero.
| Si el módulo vuelve, vuelven junto con sus controladores.
|
*/

Route::get('/chart-types', function () {
    return DB::table('chart_types')->select('id','name','slug')->orderBy('id')->get();
});
