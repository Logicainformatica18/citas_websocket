<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AI\DashboardAIController;
Route::get('/ai/chat/history', [DashboardAIController::class, 'history']);
Route::prefix('ai/dashboards')->group(function () {
    Route::get('/', [DashboardAIController::class, 'index']);
});
