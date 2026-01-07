<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AI\DashboardWidgetController;

Route::prefix('ai/dashboards/{dashboard}')->group(function () {

    Route::get('widgets', [DashboardWidgetController::class, 'index']);
    Route::post('widgets/from-training', [DashboardWidgetController::class, 'storeFromTraining']);

    Route::put('widgets/{widget}', [DashboardWidgetController::class, 'update']);
    Route::delete('widgets/{widget}', [DashboardWidgetController::class, 'destroy']);

    Route::post('widgets/reorder', [DashboardWidgetController::class, 'reorder']);
    Route::patch('widgets/{widget}/color', [DashboardWidgetController::class, 'updateColor']);
    Route::post('widgets/{widget}/filters', [DashboardWidgetController::class, 'saveFilters']);
});
