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
// Mismos permisos que routes/web.php (ver el comentario de cabecera allí).
// ------------------------------
$admin = 'permission:administrar';
$sol_ver = 'permission:administrar|solicitudes.ver';
$sol_actualizar = 'permission:administrar|solicitudes.actualizar';
$sol_avanzadas = 'permission:administrar|solicitudes.acciones_avanzadas';
$sol_eliminar = 'permission:administrar|solicitudes.eliminar';
$clientes = 'permission:administrar|clientes';
$inv_agregar = 'permission:administrar|agregar';
$inv_editar = 'permission:administrar|editar|actualizar';
$inv_eliminar = 'permission:administrar|eliminar';

Route::middleware('auth:sanctum')->group(function () use (
    $admin, $sol_ver, $sol_actualizar, $sol_avanzadas, $sol_eliminar,
    $clientes, $inv_agregar, $inv_editar, $inv_eliminar
) {

    // 🔐 Logout
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => '✅ Logout exitoso']);
    });

    // 🔐 Usuario actual
    Route::get('/me', fn (Request $request) => response()->json($request->user()));

    // ----------------------------
    // 👤 Usuarios y 🔐 Roles (solo administrar)
    // ----------------------------
    Route::middleware($admin)->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/fetch', [UserController::class, 'fetchPaginated'])->name('api.users.fetch');
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::post('/users/{id}/roles', [UserController::class, 'syncRoles']);

        Route::get('/roles', [RoleController::class, 'index']);
        Route::get('/roles/fetch', [RoleController::class, 'fetchPaginated']);
        Route::get('/roles/{id}', [RoleController::class, 'show']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{id}', [RoleController::class, 'update']);
        Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
    });

    // ----------------------------
    // 📦 Productos
    // ----------------------------
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/fetch', [ProductController::class, 'fetchPaginated'])->name('api.products.fetch');
    Route::get('/products/search', [ProductController::class, 'searchByDescription']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::post('/products', [ProductController::class, 'store'])->middleware($inv_agregar);
    Route::put('/products/{id}', [ProductController::class, 'update'])->middleware($inv_editar);
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])->middleware($inv_eliminar);

    // ----------------------------
    // 📋 Artículos
    // ----------------------------
    Route::get('/articles/fetch', [ArticleController::class, 'fetchPaginated']);
    Route::get('/articles/{id}', [ArticleController::class, 'show']);
    Route::get('/articles/export/{transfer_id}', [ArticleController::class, 'exportExcel']);
    Route::post('/articles/bulk-delete', [ArticleController::class, 'bulkDelete'])->middleware($inv_eliminar);
    Route::post('/articles/bulk-store', [ArticleController::class, 'bulkStore'])->middleware($inv_agregar);
    Route::post('/articles', [ArticleController::class, 'store'])->middleware($inv_agregar);
    Route::post('/articles/{id}', [ArticleController::class, 'update'])->middleware($inv_editar); // PUT vía FormData
    Route::delete('/articles/{id}', [ArticleController::class, 'destroy'])->middleware($inv_eliminar);

    // ----------------------------
    // 🔄 Transferencias
    // ----------------------------
    Route::get('/transfers', [TransferController::class, 'index']);
    Route::get('/transfers/fetch', [TransferController::class, 'fetchPaginated'])->name('api.transfers.fetch');
    Route::get('/transfers/{id}', [TransferController::class, 'show']);
    Route::post('/transfers', [TransferController::class, 'store'])->middleware($inv_agregar);
    Route::put('/transfers/{id}', [TransferController::class, 'update'])->middleware($inv_editar);
    Route::delete('/transfers/{id}', [TransferController::class, 'destroy'])->middleware($inv_eliminar);

    // ----------------------------
    // 📁 Soportes
    // ----------------------------
    Route::get('/supports/export-all', [SupportController::class, 'exportAll'])->middleware($admin);
    Route::middleware($sol_ver)->group(function () {
        Route::get('/supports', [SupportController::class, 'index']);
        Route::get('/supports/fetch', [SupportController::class, 'fetchPaginated'])->name('api.supports.fetch');
        Route::get('/supports/{id}', [SupportController::class, 'show']);
        Route::post('/supports', [SupportController::class, 'store']);
        Route::get('/reports/{id}', [ReportController::class, 'show']);
    });
    Route::put('/supports/{id}', [SupportController::class, 'update'])->middleware($sol_actualizar);
    // NOTA: SupportController no tiene updateAreaMotivo (está en SupportDetailController
    // y recibe el id del detalle). Esta ruta responde 500; se deja tal cual.
    Route::put('/supports/{id}/area-motivo', [SupportController::class, 'updateAreaMotivo'])->middleware($sol_avanzadas);
    Route::middleware($sol_eliminar)->group(function () {
        Route::delete('/supports/{id}', [SupportController::class, 'destroy']);
        Route::post('/supports/bulk-delete', [SupportController::class, 'bulkDelete']);
    });

    // ----------------------------
    // 👥 Clientes
    // ----------------------------
    Route::get('/clients/search', [ClientController::class, 'searchByName'])
        ->middleware('permission:administrar|solicitudes.ver|clientes');
    Route::middleware($clientes)->group(function () {
        Route::get('/clients', [ClientController::class, 'index']);
        Route::get('/clients/fetch', [ClientController::class, 'fetchPaginated']);
        Route::get('/clients/{id}', [ClientController::class, 'show']);
        Route::post('/clients', [ClientController::class, 'store']);
        Route::put('/clients/{id}', [ClientController::class, 'update']);
    });
    Route::middleware($admin)->group(function () {
        Route::delete('/clients/{id}', [ClientController::class, 'destroy']);
        Route::post('/clients/bulk-delete', [ClientController::class, 'bulkDelete']);
    });

    // ----------------------------
    // 📚 Catálogos: lectura libre, escritura administrar
    // ----------------------------
    Route::get('/areas/all', [AreaController::class, 'getAllEnabled']);
    Route::get('/motivos-cita/all', [MotiveController::class, 'getAllEnabled']);

    $catalogs = [
        'areas' => AreaController::class,
        'internal-states' => InternalStateController::class,
        'external-states' => ExternalStateController::class,
        'appointment-types' => AppointmentTypeController::class,
        'waiting-days' => WaitingDayController::class,
        'motives' => MotiveController::class,
        'types' => TypeController::class,
        'projects' => ProjectController::class,
    ];
    foreach ($catalogs as $uri => $controller) {
        Route::get("/{$uri}", [$controller, 'index']);
        Route::get("/{$uri}/fetch", [$controller, 'fetchPaginated']);
        Route::get("/{$uri}/{id}", [$controller, 'show']);
        Route::middleware($admin)->group(function () use ($uri, $controller) {
            Route::post("/{$uri}", [$controller, 'store']);
            Route::put("/{$uri}/{id}", [$controller, 'update']);
            Route::delete("/{$uri}/{id}", [$controller, 'destroy']);
            Route::post("/{$uri}/bulk-delete", [$controller, 'bulkDelete']);
        });
    }

    // ----------------------------
    // 💬 Chat
    // ----------------------------
    Route::get('/chat/messages', [ChatMessageController::class, 'fetch']);
    Route::post('/chat/messages', [ChatMessageController::class, 'store']);
});
