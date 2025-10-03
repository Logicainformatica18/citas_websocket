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
});
