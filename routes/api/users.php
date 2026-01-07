<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::get('/me', fn ($r) => response()->json($r->user()));

Route::post('/logout', function ($request) {
    $request->user()->currentAccessToken()->delete();
    return response()->json(['message' => '✅ Logout exitoso']);
});

Route::get('/users', [UserController::class, 'index']);
Route::get('/users/fetch', [UserController::class, 'fetchPaginated']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::post('/users', [UserController::class, 'store']);
Route::put('/users/{id}', [UserController::class, 'update']);
Route::delete('/users/{id}', [UserController::class, 'destroy']);
Route::post('/users/{id}/roles', [UserController::class, 'syncRoles']);
