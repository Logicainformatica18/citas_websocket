<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AI\Metrics\MetricsDashboardController;

Route::prefix('metrics')->group(function () {
    Route::get('global-alignment', [MetricsDashboardController::class, 'globalAlignment']);
    Route::get('ai-integration', [MetricsDashboardController::class, 'aiIntegration']);
    Route::get('curricular-updates', [MetricsDashboardController::class, 'curricularUpdates']);
    Route::get('tech-growth', [MetricsDashboardController::class, 'techGrowth']);
    Route::get('obsolescence-index', [MetricsDashboardController::class, 'obsolescenceIndex']);
    Route::get('career-improvement', [MetricsDashboardController::class, 'careerImprovement']);
});
