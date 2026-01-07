<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AI\DashboardSectionController;

Route::prefix('ai/dashboards/{dashboard}')->group(function () {

    Route::get('sections', [DashboardSectionController::class, 'index']);
    Route::post('sections', [DashboardSectionController::class, 'store']);
    Route::post('sections/{id}/update', [DashboardSectionController::class, 'update']);
    Route::delete('sections/{id}', [DashboardSectionController::class, 'destroy']);
});
