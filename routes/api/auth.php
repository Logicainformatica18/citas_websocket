<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

Route::post('/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['message' => '❌ Credenciales inválidas'], 401);
    }

    return response()->json([
        'message' => '✅ Login exitoso',
        'token'   => $user->createToken('authToken')->plainTextToken,
        'user'    => $user,
    ]);
});
