<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

// Controladores
use App\Http\Controllers\UserController;


   use App\Http\Controllers\JobOfferController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\AI\CityDemandAIController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\AI\MetricsAIController;
use App\Http\Controllers\AI\TechnologiesAIController;
//use App\Http\Controllers\AI\LanguageAlignmentAIController;
use App\Http\Controllers\AI\Metrics\TechnologyAlignmentAIController;
use App\Http\Controllers\AI\Metrics\MethodologyAlignmentAIController;
use App\Http\Controllers\AI\Metrics\CareerLanguageAlignmentAIController;
use App\Http\Controllers\AI\Metrics\CareerTechnologyAlignmentAIController;
use App\Http\Controllers\AI\Metrics\CareerMethodologyAlignmentAIController;




Route::middleware('apikey')->group(function () {
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);

    // routes/api.php
Route::patch('/payments/{payment}/state-slim', [PaymentController::class, 'updateStateSlim']);

Route::get('/ai/city-demand', [CityDemandAIController::class, 'getData']);
});



// Route::get('/ping', function (Request $request) {
//     $header = $request->header('Authorization');
//     $plain = $header ? str_replace('Bearer ', '', $header) : null;

//     return [
//         'header' => $header,
//         'token_match' => $plain ? (bool) \Laravel\Sanctum\PersonalAccessToken::findToken($plain) : false,
//         'user' => $request->user(),
//     ];
// })->middleware('auth:sanctum');


// --------------------
// ✅ Rutas públicas
// --------------------
Route::post('/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['message' => '❌ Credenciales inválidas'], 401);
    }

    $token = $user->createToken('authToken')->plainTextToken;

    return response()->json([
        'message' => '✅ Login exitoso',
        'token' => $token,
        'user' => $user
    ]);
});

Route::get('/test', fn () => response()->json([
    'message' => '✅ API funcionando correctamente',
    'timestamp' => now(),
]));

// ------------------------------
// 🔒 Rutas protegidas con Sanctum
// ------------------------------
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/motivos-cita', function () {
    return \App\Models\Motive::select('id_motivos_cita as id', 'nombre_motivo', 'detail')->get();
});// 🔐 Logout
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => '✅ Logout exitoso']);
    });

    // 🔐 Usuario actual
    Route::get('/me', fn (Request $request) => response()->json($request->user()));

    // ----------------------------
    // 👤 Usuarios
    // ----------------------------
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/fetch', [UserController::class, 'fetchPaginated'])->name('users.fetch');
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::post('/users/{id}/roles', [UserController::class, 'syncRoles']);



Route::post('/job-offers/preview', [JobOfferController::class, 'preview']);

Route::get('/courses/list', [CourseController::class, 'listAll']);
Route::get('/ai/metrics', [MetricsAIController::class, 'index']);



Route::get('ai/technologies', [TechnologiesAIController::class, 'index']);
Route::post('/ai/technologies/enriched/data', [TechnologiesAIController::class, 'getData']);
Route::get('/ai/technologies/enriched/metadata', [TechnologiesAIController::class, 'metadata']);

Route::get('/ai/city-demand/metadata', [CityDemandAIController::class, 'metadata']);
Route::post('/ai/city-demand/data', [CityDemandAIController::class, 'getData']);

Route::prefix('ai/worldbank')->group(function () {
    Route::get('metadata', [App\Http\Controllers\AI\WorldBankAIController::class, 'metadata']);
    Route::get('get-data', [App\Http\Controllers\AI\WorldBankAIController::class, 'getData']);
});





Route::prefix('ai/stackoverflow')->group(function () {
    // Bloque Perfil Profesional
    Route::get('profile/workmode/metadata', [\App\Http\Controllers\AI\StackOverflow\ProfileWorkModeAIController::class, 'metadata']);
    Route::get('profile/workmode', [\App\Http\Controllers\AI\StackOverflow\ProfileWorkModeAIController::class, 'getData']);

    Route::get('profile/education/metadata', [\App\Http\Controllers\AI\StackOverflow\ProfileEducationAIController::class, 'metadata']);
    Route::get('profile/education', [\App\Http\Controllers\AI\StackOverflow\ProfileEducationAIController::class, 'getData']);

    Route::get('profile/age/metadata', [\App\Http\Controllers\AI\StackOverflow\ProfileAgeAIController::class, 'metadata']);
    Route::get('profile/age', [\App\Http\Controllers\AI\StackOverflow\ProfileAgeAIController::class, 'getData']);
});







  // 💬 Lenguajes
    Route::get('ai/career-language-alignment/metadata', [CareerLanguageAlignmentAIController::class, 'metadata']);
    Route::get('ai/career-language-alignment/data', [CareerLanguageAlignmentAIController::class, 'getData']);
    Route::get('ai/career-language-alignment/export', [CareerLanguageAlignmentAIController::class, 'export']);




// ⚙️ Tecnologías
Route::get('ai/career-technology-alignment/metadata', [CareerTechnologyAlignmentAIController::class, 'metadata']);
Route::get('ai/career-technology-alignment/data', [CareerTechnologyAlignmentAIController::class, 'getData']);
Route::get('ai/career-technology-alignment/export', [CareerTechnologyAlignmentAIController::class, 'export']);

// 🧭 Metodologías
Route::get('ai/career-methodology-alignment/metadata', [CareerMethodologyAlignmentAIController::class, 'metadata']);
Route::get('ai/career-methodology-alignment/data', [CareerMethodologyAlignmentAIController::class, 'getData']);
Route::get('ai/career-methodology-alignment/export', [CareerMethodologyAlignmentAIController::class, 'export']);


});
