<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

require __DIR__.'/api/auth.php';

Route::middleware('auth:sanctum')->group(function () {

    require __DIR__.'/api/users.php';
    require __DIR__.'/api/dashboards.php';
    require __DIR__.'/api/dashboard_widgets.php';
    require __DIR__.'/api/dashboard_sections.php';

    require __DIR__.'/api/ai_metrics.php';
    require __DIR__.'/api/ai_sources.php';
    require __DIR__.'/api/misc.php';
});
