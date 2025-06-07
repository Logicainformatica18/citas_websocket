<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\RoleController;

use App\Http\Controllers\PermissionController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;


// Login API
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

// Logout API
Route::middleware('auth:sanctum')->post('/logout', function (Request $request) {
    $request->user()->currentAccessToken()->delete();
    return response()->json(['message' => '✅ Logout exitoso']);
});

// Usuario autenticado
Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return response()->json($request->user());
});

// Rutas protegidas de usuarios
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/fetch', [UserController::class, 'fetchPaginated'])->name('users.fetch');
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::post('/users/{id}/roles', [UserController::class, 'syncRoles']);



  Route::get('/transfers/fetch', [TransferController::class, 'fetchPaginated'])->name('transfers.fetch');
    Route::get('/transfers', [TransferController::class, 'index']);
    Route::post('/transfers', [TransferController::class, 'store']);
    Route::get('/transfers/{id}', [TransferController::class, 'show']);
    Route::put('/transfers/{id}', [TransferController::class, 'update']);
    Route::delete('/transfers/{id}', [TransferController::class, 'destroy']);

 Route::get('/products/search', [ProductController::class, 'searchByDescription']);

Route::get('/products/fetch', [ProductController::class, 'fetchPaginated'])->name('products.fetch');
Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::put('/products/{id}', [ProductController::class, 'update']);
Route::delete('/products/{id}', [ProductController::class, 'destroy']);


Route::get('/articles/fetch', [ArticleController::class, 'fetchPaginated']); // ?transfer_id=ID&page=X
Route::get('/articles/{id}', [ArticleController::class, 'show']);
Route::post('/articles', [ArticleController::class, 'store']);
Route::post('/articles/{id}', [ArticleController::class, 'update']); // método POST + _method=PUT
Route::delete('/articles/{id}', [ArticleController::class, 'destroy']);
Route::post('/articles/bulk-delete', [ArticleController::class, 'bulkDelete']);
Route::post('/articles/bulk-store', [ArticleController::class, 'bulkStore']);
Route::get('/articles/export/{transfer_id}', [ArticleController::class, 'exportExcel']);


    });


Route::get('/test', function () {
    return response()->json([
        'message' => '✅ API funcionando correctamente',
        'timestamp' => now(),
    ]);
});

