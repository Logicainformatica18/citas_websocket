<?php

use App\Http\Controllers\AppointmentTypeController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExternalStateController;
use App\Http\Controllers\InternalStateController;
use App\Http\Controllers\MotiveController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\SupportDetailController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\TypeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WaitingDayController;
use App\Http\Controllers\WebSocketTestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Autorización (Spatie)
|--------------------------------------------------------------------------
| Se usa "permission:a|b" (basta con tener UNO). "administrar" siempre se
| incluye para que el rol Administrador conserve acceso aunque en la BD no
| tenga asignados los permisos finos (el seeder original solo le daba
| "administrar").
|
| - Usuarios, roles, escritura de catálogos, exportación, debug → administrar
| - Solicitudes (ver/crear/comentar)      → solicitudes.ver
| - Editar solicitud                      → solicitudes.actualizar
| - Cambiar área/motivo/estado interno    → solicitudes.acciones_avanzadas
| - Generar ticket                        → generar_ticket
| - Eliminar solicitudes o detalles       → solicitudes.eliminar
| - Clientes y sus lotes (ver/crear/editar) → clientes (eliminar: solo administrar)
| - Inventario: crear/editar/eliminar     → agregar / editar|actualizar / eliminar
| - Lectura de catálogos, dashboard, chat → cualquier usuario autenticado
*/

$admin = 'permission:administrar';
$sol_ver = 'permission:administrar|solicitudes.ver';
$sol_actualizar = 'permission:administrar|solicitudes.actualizar';
$sol_avanzadas = 'permission:administrar|solicitudes.acciones_avanzadas';
$sol_eliminar = 'permission:administrar|solicitudes.eliminar';
$sol_ticket = 'permission:administrar|generar_ticket';
$clientes = 'permission:administrar|clientes';
$inv_agregar = 'permission:administrar|agregar';
$inv_editar = 'permission:administrar|editar|actualizar';
$inv_eliminar = 'permission:administrar|eliminar';

Route::get('/', function () {
    return redirect('dashboard');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () use (
    $admin, $sol_ver, $sol_actualizar, $sol_avanzadas, $sol_eliminar, $sol_ticket,
    $clientes, $inv_agregar, $inv_editar, $inv_eliminar
) {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ---------------- Usuarios y roles (solo administrar) ----------------
    Route::middleware($admin)->group(function () {
        Route::get('/users/fetch', [UserController::class, 'fetchPaginated'])->name('users.fetch');
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}/sync-roles', [UserController::class, 'syncRoles']);

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/roles/fetch', [RoleController::class, 'fetchPaginated'])->name('roles.fetch');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{id}', [RoleController::class, 'show'])->name('roles.show');
        Route::put('/roles/{id}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

    // ---------------- Inventario: artículos, productos, transferencias ----------------
    Route::get('/articles/fetch', [ArticleController::class, 'fetchPaginated'])->name('articles.fetch');
    Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
    Route::get('/articles/{id}', [ArticleController::class, 'show']);
    Route::get('/articles/{id}/export-excel', [ArticleController::class, 'exportExcel']);
    Route::post('/articles', [ArticleController::class, 'store'])->middleware($inv_agregar);
    Route::post('/articles/bulk-store', [ArticleController::class, 'bulkStore'])->middleware($inv_agregar);
    Route::put('/articles/{id}', [ArticleController::class, 'update'])->middleware($inv_editar);
    Route::delete('/articles/{id}', [ArticleController::class, 'destroy'])->middleware($inv_eliminar);
    Route::post('/articles/bulk-delete', [ArticleController::class, 'bulkDelete'])->middleware($inv_eliminar);

    Route::get('/products/search', [ProductController::class, 'searchByDescription']);
    Route::get('/products/fetch', [ProductController::class, 'fetchPaginated'])->name('products.fetch');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::get('/products/{id}/export-excel', [ProductController::class, 'exportExcel']);
    Route::post('/products', [ProductController::class, 'store'])->middleware($inv_agregar);
    Route::put('/products/{id}', [ProductController::class, 'update'])->middleware($inv_editar);
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])->middleware($inv_eliminar);
    Route::post('/products/bulk-delete', [ProductController::class, 'bulkDelete'])->middleware($inv_eliminar);

    Route::get('/transfers/{id}/articles', [TransferController::class, 'articles'])->name('transfers.articles');
    Route::get('/transfers/fetch', [TransferController::class, 'fetchPaginated'])->name('transfers.fetch');
    Route::get('/transfers', [TransferController::class, 'index'])->name('transfers.index');
    Route::get('/transfers/{id}', [TransferController::class, 'show']);
    Route::get('/transfer-confirmation/{token}', [TransferController::class, 'confirm'])->name('transfer.confirm');
    Route::post('/transfers', [TransferController::class, 'store'])->middleware($inv_agregar);
    Route::put('/transfers/{id}', [TransferController::class, 'update'])->middleware($inv_editar);
    Route::post('/transfers/{id}/notify', [TransferController::class, 'notify'])->middleware($inv_editar);
    Route::delete('/transfers/{id}', [TransferController::class, 'destroy'])->middleware($inv_eliminar);
    Route::post('/transfers/bulk-delete', [TransferController::class, 'bulkDelete'])->middleware($inv_eliminar);

    // ---------------- Solicitudes ----------------
    Route::middleware($sol_ver)->group(function () {
        Route::get('/supports/search', [SupportController::class, 'fetch'])->name('supports.search');
        Route::get('/supports/fetch', [SupportController::class, 'fetchPaginated'])->name('supports.fetch');
        Route::get('/supports', [SupportController::class, 'index'])->name('supports.index');
        Route::post('/supports', [SupportController::class, 'store']);
        Route::get('/supports/{id}', [SupportController::class, 'show'])->whereNumber('id');

        Route::get('/support-details/{supportDetail}/comments', [CommentController::class, 'index']);
        Route::post('/support-details/{supportDetail}/comments', [CommentController::class, 'store']);

        Route::get('/reports/{id}', [ReportController::class, 'show'])->name('reports.show');
    });

    Route::get('/supports/export-all', [SupportController::class, 'exportAll'])->name('supports.export')->middleware($admin);
    Route::put('/supports/{id}', [SupportController::class, 'update'])->middleware($sol_actualizar);
    Route::put('/support-details/{id}/area-motivo', [SupportDetailController::class, 'updateAreaMotivo'])->middleware($sol_avanzadas);
    Route::post('/support-details/generate-ticket', [SupportDetailController::class, 'generateTicket'])->middleware($sol_ticket);

    Route::middleware($sol_eliminar)->group(function () {
        Route::delete('/supports/{id}', [SupportController::class, 'destroy']);
        Route::post('/supports/bulk-delete', [SupportController::class, 'bulkDelete']);
        Route::delete('/support-details/{id}', [SupportDetailController::class, 'destroy']);
    });

    // ---------------- Clientes ----------------
    // La búsqueda la usa el formulario de solicitudes.
    Route::get('/clients/search', [ClientController::class, 'searchByName'])
        ->middleware('permission:administrar|solicitudes.ver|clientes');
    Route::middleware($clientes)->group(function () {
        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('/clients/fetch', [ClientController::class, 'fetchPaginated']);
        Route::post('/clients', [ClientController::class, 'store']);
        Route::get('/clients/{id}', [ClientController::class, 'show']);
        Route::put('/clients/{id}', [ClientController::class, 'update']);
    });
    // Borrar un cliente borra en cascada sus solicitudes → solo administrar.
    Route::middleware($admin)->group(function () {
        Route::delete('/clients/{id}', [ClientController::class, 'destroy']);
        Route::post('/clients/bulk-delete', [ClientController::class, 'bulkDelete']);
    });

    // ---------------- Detalle cliente (ventas/lotes) ----------------
    Route::middleware($sol_ver)->group(function () {
        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/fetch', [SaleController::class, 'fetchPaginated']);
        Route::get('/sales/{id}', [SaleController::class, 'show']);
    });
    // Registrar/editar lotes: también agentes con "clientes" (atajo desde la solicitud).
    Route::middleware($clientes)->group(function () {
        Route::post('/sales', [SaleController::class, 'store']);
        Route::put('/sales/{id}', [SaleController::class, 'update']);
    });
    Route::middleware($admin)->group(function () {
        Route::delete('/sales/{id}', [SaleController::class, 'destroy']);
        Route::post('/sales/bulk-delete', [SaleController::class, 'bulkDelete']);
    });

    // ---------------- Catálogos: lectura libre, escritura administrar ----------------
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
        Route::get("/{$uri}", [$controller, 'index'])->name("{$uri}.index");
        Route::get("/{$uri}/fetch", [$controller, 'fetchPaginated'])->name("{$uri}.fetch");
        Route::get("/{$uri}/{id}", [$controller, 'show']);
        Route::middleware($admin)->group(function () use ($uri, $controller, $admin) {
            Route::post("/{$uri}", [$controller, 'store']);
            Route::put("/{$uri}/{id}", [$controller, 'update']);
            Route::delete("/{$uri}/{id}", [$controller, 'destroy']);
            Route::post("/{$uri}/bulk-delete", [$controller, 'bulkDelete']);
        });
    }

    // ---------------- Chat ----------------
    Route::get('/chat', [ChatMessageController::class, 'index'])->name('chat.index');
    Route::get('/chat/messages', [ChatMessageController::class, 'fetch'])->name('chat.fetch');
    Route::post('/chat/messages', [ChatMessageController::class, 'store'])->name('chat.store');

    // ---------------- Depuración de WebSockets (antes eran públicas) ----------------
    Route::middleware($admin)->group(function () {
        Route::get('/ws/test', [WebSocketTestController::class, 'send']);

        Route::get('/debug-broadcast', function () {
            return response()->json([
                'broadcast_driver' => config('broadcasting.default'),
                'reverb_config' => config('broadcasting.connections.reverb'),
                'pusher_config' => config('broadcasting.connections.pusher'),
            ]);
        });

        Route::get('/ws_pueba', function () {
            broadcast(new \App\Events\RecordChanged('Support', 'created', [
                'id' => 998,
                'subject' => 'Echo Final OK',
            ]));

            return response()->json(['success' => true, 'message' => 'Evento emitido']);
        });
    });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
