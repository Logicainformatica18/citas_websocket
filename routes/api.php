<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

// Controladores
use App\Http\Controllers\UserController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\InternalStateController;
use App\Http\Controllers\ExternalStateController;
use App\Http\Controllers\AppointmentTypeController;
use App\Http\Controllers\WaitingDayController;
use App\Http\Controllers\MotiveController;
use App\Http\Controllers\TypeController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DashboardController;


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

    // 🔐 Logout
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

    // ----------------------------
    // 📦 Productos
    // ----------------------------
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/fetch', [ProductController::class, 'fetchPaginated'])->name('products.fetch');
    Route::get('/products/search', [ProductController::class, 'searchByDescription']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    // ----------------------------
    // 📋 Artículos
    // ----------------------------
    Route::get('/articles/fetch', [ArticleController::class, 'fetchPaginated']);
    Route::get('/articles/{id}', [ArticleController::class, 'show']);
    Route::post('/articles', [ArticleController::class, 'store']);
    Route::post('/articles/{id}', [ArticleController::class, 'update']); // PUT vía FormData
    Route::delete('/articles/{id}', [ArticleController::class, 'destroy']);
    Route::post('/articles/bulk-delete', [ArticleController::class, 'bulkDelete']);
    Route::post('/articles/bulk-store', [ArticleController::class, 'bulkStore']);
    Route::get('/articles/export/{transfer_id}', [ArticleController::class, 'exportExcel']);

    // ----------------------------
    // 🔄 Transferencias
    // ----------------------------
    Route::get('/transfers', [TransferController::class, 'index']);
    Route::get('/transfers/fetch', [TransferController::class, 'fetchPaginated'])->name('transfers.fetch');
    Route::get('/transfers/{id}', [TransferController::class, 'show']);
    Route::post('/transfers', [TransferController::class, 'store']);
    Route::put('/transfers/{id}', [TransferController::class, 'update']);
    Route::delete('/transfers/{id}', [TransferController::class, 'destroy']);

    // Puedes seguir aquí con más módulos: supports, clients, etc.
     // ----------------------------
    // 📁 Soportes
    // ----------------------------
    Route::get('/supports', [SupportController::class, 'index']);
    Route::get('/supports/fetch', [SupportController::class, 'fetchPaginated'])->name('supports.fetch');
    Route::middleware('auth:sanctum')->get('/supports/search', [SupportController::class, 'fetch']);

    Route::get('/supports/{id}', [SupportController::class, 'show']);
    Route::post('/supports', [SupportController::class, 'store']);
    Route::put('/supports/{id}', [SupportController::class, 'update']);
    Route::delete('/supports/{id}', [SupportController::class, 'destroy']);
    Route::post('/supports/bulk-delete', [SupportController::class, 'bulkDelete']);
    Route::put('/supports/{id}/area-motivo', [SupportController::class, 'updateAreaMotivo']);
    Route::get('/supports/export-all', [SupportController::class, 'exportAll']);

    // ----------------------------
    // 👥 Clientes
    // ----------------------------
    Route::get('/clients', [ClientController::class, 'index']);
    Route::get('/clients/fetch', [ClientController::class, 'fetchPaginated']);
    Route::get('/clients/search', [ClientController::class, 'searchByName']);
    Route::get('/clients/{id}', [ClientController::class, 'show']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::put('/clients/{id}', [ClientController::class, 'update']);
    Route::delete('/clients/{id}', [ClientController::class, 'destroy']);
    Route::post('/clients/bulk-delete', [ClientController::class, 'bulkDelete']);

    // ----------------------------
    // 🏢 Áreas
    // ----------------------------
    Route::get('/areas', [AreaController::class, 'index']);
    Route::get('/areas/fetch', [AreaController::class, 'fetchPaginated']);
    Route::get('/areas/all', [AreaController::class, 'getAllEnabled']);
    Route::get('/areas/{id}', [AreaController::class, 'show']);
    Route::post('/areas', [AreaController::class, 'store']);
    Route::put('/areas/{id}', [AreaController::class, 'update']);
    Route::delete('/areas/{id}', [AreaController::class, 'destroy']);
    Route::post('/areas/bulk-delete', [AreaController::class, 'bulkDelete']);

    // ----------------------------
    // 🔐 Roles y Permisos
    // ----------------------------
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/roles/fetch', [RoleController::class, 'fetchPaginated']);
    Route::get('/roles/{id}', [RoleController::class, 'show']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::put('/roles/{id}', [RoleController::class, 'update']);
    Route::delete('/roles/{id}', [RoleController::class, 'destroy']);

    // ----------------------------
    // 🟢 Estado Interno
    // ----------------------------
    Route::get('/internal-states', [InternalStateController::class, 'index']);
    Route::get('/internal-states/fetch', [InternalStateController::class, 'fetchPaginated']);
    Route::get('/internal-states/{id}', [InternalStateController::class, 'show']);
    Route::post('/internal-states', [InternalStateController::class, 'store']);
    Route::put('/internal-states/{id}', [InternalStateController::class, 'update']);
    Route::delete('/internal-states/{id}', [InternalStateController::class, 'destroy']);
    Route::post('/internal-states/bulk-delete', [InternalStateController::class, 'bulkDelete']);

    // ----------------------------
    // 🔴 Estado Externo
    // ----------------------------
    Route::get('/external-states', [ExternalStateController::class, 'index']);
    Route::get('/external-states/fetch', [ExternalStateController::class, 'fetchPaginated']);
    Route::get('/external-states/{id}', [ExternalStateController::class, 'show']);
    Route::post('/external-states', [ExternalStateController::class, 'store']);
    Route::put('/external-states/{id}', [ExternalStateController::class, 'update']);
    Route::delete('/external-states/{id}', [ExternalStateController::class, 'destroy']);
    Route::post('/external-states/bulk-delete', [ExternalStateController::class, 'bulkDelete']);

    // ----------------------------
    // 📆 Tipos de Cita
    // ----------------------------
    Route::get('/appointment-types', [AppointmentTypeController::class, 'index']);
    Route::get('/appointment-types/fetch', [AppointmentTypeController::class, 'fetchPaginated']);
    Route::get('/appointment-types/{id}', [AppointmentTypeController::class, 'show']);
    Route::post('/appointment-types', [AppointmentTypeController::class, 'store']);
    Route::put('/appointment-types/{id}', [AppointmentTypeController::class, 'update']);
    Route::delete('/appointment-types/{id}', [AppointmentTypeController::class, 'destroy']);
    Route::post('/appointment-types/bulk-delete', [AppointmentTypeController::class, 'bulkDelete']);

    // ----------------------------
    // 🕐 Días de Espera
    // ----------------------------
    Route::get('/waiting-days', [WaitingDayController::class, 'index']);
    Route::get('/waiting-days/fetch', [WaitingDayController::class, 'fetchPaginated']);
    Route::get('/waiting-days/{id}', [WaitingDayController::class, 'show']);
    Route::post('/waiting-days', [WaitingDayController::class, 'store']);
    Route::put('/waiting-days/{id}', [WaitingDayController::class, 'update']);
    Route::delete('/waiting-days/{id}', [WaitingDayController::class, 'destroy']);
    Route::post('/waiting-days/bulk-delete', [WaitingDayController::class, 'bulkDelete']);

    // ----------------------------
    // 📋 Motivos
    // ----------------------------
    Route::get('/motives', [MotiveController::class, 'index']);
    Route::get('/motives/fetch', [MotiveController::class, 'fetchPaginated']);
    Route::get('/motivos-cita/all', [MotiveController::class, 'getAllEnabled']);
    Route::get('/motives/{id}', [MotiveController::class, 'show']);
    Route::post('/motives', [MotiveController::class, 'store']);
    Route::put('/motives/{id}', [MotiveController::class, 'update']);
    Route::delete('/motives/{id}', [MotiveController::class, 'destroy']);
    Route::post('/motives/bulk-delete', [MotiveController::class, 'bulkDelete']);

    // ----------------------------
    // 🧾 Tipos
    // ----------------------------
    Route::get('/types', [TypeController::class, 'index']);
    Route::get('/types/fetch', [TypeController::class, 'fetchPaginated']);
    Route::get('/types/{id}', [TypeController::class, 'show']);
    Route::post('/types', [TypeController::class, 'store']);
    Route::put('/types/{id}', [TypeController::class, 'update']);
    Route::delete('/types/{id}', [TypeController::class, 'destroy']);
    Route::post('/types/bulk-delete', [TypeController::class, 'bulkDelete']);

    // ----------------------------
    // 🏗️ Proyectos
    // ----------------------------
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/fetch', [ProjectController::class, 'fetchPaginated']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::put('/projects/{id}', [ProjectController::class, 'update']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
    Route::post('/projects/bulk-delete', [ProjectController::class, 'bulkDelete']);

    // ----------------------------
    // 💬 Chat
    // ----------------------------
    Route::get('/chat/messages', [ChatMessageController::class, 'fetch']);
    Route::post('/chat/messages', [ChatMessageController::class, 'store']);

    // ----------------------------
    // 📊 Reportes
    // ----------------------------
    Route::get('/reports/{id}', [ReportController::class, 'show']);
});
