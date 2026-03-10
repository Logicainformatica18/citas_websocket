<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\AI\DashboardAIController;
use App\Http\Controllers\AI\AITrainingController;
use App\Http\Controllers\CourseController;

Route::prefix('ai')->group(function () {
    Route::post('/training/start', [AITrainingController::class, 'startTraining']);
    Route::post('/training/test', [AITrainingController::class, 'testSql']);
    Route::post('/training/finalize', [AITrainingController::class, "finalizeTraining"]);
});
Route::prefix('ai')->group(function () {
    Route::get('/suggestions', [DashboardAIController::class, 'suggestions']);
      Route::post('/chat', [DashboardAIController::class, 'chat']);
        // 🎙️ Voz → texto (transcripción)
    Route::post('voice/transcribe', [DashboardAIController::class, 'transcribe']);

    // 🗣️ Texto → voz (TTS)
    Route::post('voice/speak', [DashboardAIController::class, 'speak']);

    // 📎 Análisis de archivos
    Route::post('file/analyze', [DashboardAIController::class, 'analyzeFile']);
});
Route::get('/courses/list', [CourseController::class, 'listAll']);
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
