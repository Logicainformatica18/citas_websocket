<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AI\DashboardSectionController;

Route::prefix('ai/dashboards/{dashboard}')
    ->middleware('auth:sanctum')
    ->group(function () {

        Route::get('sections', [DashboardSectionController::class, 'index']);
        Route::post('sections', [DashboardSectionController::class, 'store']);
        Route::put('sections/{id}', [DashboardSectionController::class, 'update']);

        // ✅ ESTA ES LA CLAVE
        Route::delete('sections/{id}', [DashboardSectionController::class, 'destroy']);
});

