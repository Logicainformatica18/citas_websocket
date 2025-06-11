<?php
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\InternalStateController;
use App\Http\Controllers\ExternalStateController;
use App\Http\Controllers\AppointmentTypeController;
use App\Http\Controllers\MotiveController;
use App\Http\Controllers\WaitingDayController;
use App\Http\Controllers\TypeController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\ProjectController;
 use App\Http\Controllers\ReportController;
use App\Http\Controllers\DashboardController;



Route::get('/', function () {
    return redirect("dashboard");
    //return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    
 Route::get('/dashboard', [DashboardController::class, 'index']);

    // Route::get('dashboard', function () {
    //     return Inertia::render('dashboard');
    // })->name('dashboard');



    Route::get('/users/fetch', [UserController::class, 'fetchPaginated'])->name('users.fetch');
    Route::post('/users', [UserController::class, 'store'])->middleware(['auth', 'verified']);
    Route::get('/users', [UserController::class, 'index'])->middleware(['auth', 'verified'])->name('users.index');
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}/sync-roles', [UserController::class, 'syncRoles']);




    Route::get('/articles/fetch', [ArticleController::class, 'fetchPaginated'])->name('articles.fetch');
    Route::post('/articles', [ArticleController::class, 'store'])->middleware(['auth', 'verified']);
    Route::get('/articles', [ArticleController::class, 'index'])->middleware(['auth', 'verified'])->name('articles.index');
    Route::delete('/articles/{id}', [ArticleController::class, 'destroy']);
    Route::put('/articles/{id}', [ArticleController::class, 'update']);
    Route::get('/articles/{id}', [ArticleController::class, 'show']);
    Route::post('/articles/bulk-delete', [ArticleController::class, 'bulkDelete']);
    Route::get('/articles/{id}/export-excel', [ArticleController::class, 'exportExcel']);




    Route::get('/products/search', [ProductController::class, 'searchByDescription']);

    Route::get('/products/fetch', [ProductController::class, 'fetchPaginated'])->name('products.fetch');
    Route::post('/products', [ProductController::class, 'store'])->middleware(['auth', 'verified']);
    Route::get('/products', [ProductController::class, 'index'])->middleware(['auth', 'verified'])->name('products.index');
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::post('/products/bulk-delete', [ProductController::class, 'bulkDelete']);
    Route::get('/products/{id}/export-excel', [ProductController::class, 'exportExcel']);

    Route::post('/articles/bulk-store', [ArticleController::class, 'bulkStore']);











    Route::get('/transfers/{id}/articles', [TransferController::class, 'articles'])->name('transfers.articles');



    Route::get('/transfers/fetch', [TransferController::class, 'fetchPaginated'])->name('transfers.fetch');
    Route::post('/transfers', [TransferController::class, 'store'])->middleware(['auth', 'verified']);
    Route::get('/transfers', [TransferController::class, 'index'])->middleware(['auth', 'verified'])->name('transfers.index');
    Route::delete('/transfers/{id}', [TransferController::class, 'destroy']);
    Route::put('/transfers/{id}', [TransferController::class, 'update']);
    Route::get('/transfers/{id}', [TransferController::class, 'show']);
    Route::post('/transfers/bulk-delete', [TransferController::class, 'bulkDelete']);
    Route::get('/transfer-confirmation/{token}', [TransferController::class, 'confirm'])->name('transfer.confirm');



    Route::post('/transfers/{id}/notify', [TransferController::class, 'notify']);


    Route::get('/supports/fetch', [SupportController::class, 'fetchPaginated'])->name('supports.fetch');
    Route::get('/supports', [SupportController::class, 'index'])->name('supports.index');
    Route::get('/supports/export-all', [SupportController::class, 'exportAll'])->name('supports.export');

    Route::post('/supports', [SupportController::class, 'store']);
    Route::get('/supports/{id}', [SupportController::class, 'show']);
    Route::put('/supports/{id}', [SupportController::class, 'update']);
    Route::delete('/supports/{id}', [SupportController::class, 'destroy']);
    Route::post('/supports/bulk-delete', [SupportController::class, 'bulkDelete']);

Route::put('/supports/{id}/area-motivo', [SupportController::class, 'updateAreaMotivo']);




// Vista inicial e index con Inertia
Route::get('/clients', [ClientController::class, 'index'])->middleware(['auth', 'verified'])->name('clients.index');

    Route::get('/clients/search', [ClientController::class, 'searchByName']);
// Fetch para paginación desde React
Route::get('/clients/fetch', [ClientController::class, 'fetchPaginated'])->middleware(['auth', 'verified']);

// CRUD
Route::post('/clients', [ClientController::class, 'store'])->middleware(['auth', 'verified']);
Route::get('/clients/{id}', [ClientController::class, 'show'])->middleware(['auth', 'verified']);
Route::put('/clients/{id}', [ClientController::class, 'update'])->middleware(['auth', 'verified']);
Route::delete('/clients/{id}', [ClientController::class, 'destroy'])->middleware(['auth', 'verified']);

// Eliminación masiva
Route::post('/clients/bulk-delete', [ClientController::class, 'bulkDelete'])->middleware(['auth', 'verified']);





    // routes/web.php
    Route::get('/areas/all', [AreaController::class, 'getAllEnabled']);
   Route::get('/motivos-cita/all', [MotiveController::class, 'getAllEnabled']);



    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/fetch', [RoleController::class, 'fetchPaginated'])->name('roles.fetch');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{id}', [RoleController::class, 'show'])->name('roles.show');
    Route::put('/roles/{id}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->name('roles.destroy');

    Route::get('/areas/fetch', [AreaController::class, 'fetchPaginated'])->name('areas.fetch');
    Route::get('/areas', [AreaController::class, 'index'])->name('areas.index');
    Route::post('/areas', [AreaController::class, 'store']);
    Route::get('/areas/{id}', [AreaController::class, 'show']);
    Route::put('/areas/{id}', [AreaController::class, 'update']);
    Route::delete('/areas/{id}', [AreaController::class, 'destroy']);
    Route::post('/areas/bulk-delete', [AreaController::class, 'bulkDelete']);




    Route::get('/internal-states/fetch', [InternalStateController::class, 'fetchPaginated'])->name('internal-states.fetch');
    Route::get('/internal-states', [InternalStateController::class, 'index'])->name('internal-states.index');
    Route::post('/internal-states', [InternalStateController::class, 'store']);
    Route::get('/internal-states/{id}', [InternalStateController::class, 'show']);
    Route::put('/internal-states/{id}', [InternalStateController::class, 'update']);
    Route::delete('/internal-states/{id}', [InternalStateController::class, 'destroy']);
    Route::post('/internal-states/bulk-delete', [InternalStateController::class, 'bulkDelete']);

    Route::get('/external-states/fetch', [ExternalStateController::class, 'fetchPaginated'])->name('external-states.fetch');
    Route::get('/external-states', [ExternalStateController::class, 'index'])->name('external-states.index');
    Route::post('/external-states', [ExternalStateController::class, 'store']);
    Route::get('/external-states/{id}', [ExternalStateController::class, 'show']);
    Route::put('/external-states/{id}', [ExternalStateController::class, 'update']);
    Route::delete('/external-states/{id}', [ExternalStateController::class, 'destroy']);
    Route::post('/external-states/bulk-delete', [ExternalStateController::class, 'bulkDelete']);



Route::prefix('appointment-types')->group(function () {
    Route::get('/', [AppointmentTypeController::class, 'index'])->name('appointment-types.index');
    Route::get('/fetch', [AppointmentTypeController::class, 'fetchPaginated']);
    Route::post('/', [AppointmentTypeController::class, 'store']);
    Route::get('/{id}', [AppointmentTypeController::class, 'show']);
    Route::put('/{id}', [AppointmentTypeController::class, 'update']);
    Route::delete('/{id}', [AppointmentTypeController::class, 'destroy']);
    Route::post('/bulk-delete', [AppointmentTypeController::class, 'bulkDelete']);


});

 Route::get('/waiting-days', [WaitingDayController::class, 'index'])->name('waiting-days.index');
Route::get('/waiting-days/fetch', [WaitingDayController::class, 'fetchPaginated']);
Route::get('/waiting-days/{id}', [WaitingDayController::class, 'show']);
Route::post('/waiting-days', [WaitingDayController::class, 'store']);
Route::put('/waiting-days/{id}', [WaitingDayController::class, 'update']);
Route::delete('/waiting-days/{id}', [WaitingDayController::class, 'destroy']);
Route::post('/waiting-days/bulk-delete', [WaitingDayController::class, 'bulkDelete']);









Route::get('/motives', [MotiveController::class, 'index'])->name('motives.index');
Route::get('/motives/fetch', [MotiveController::class, 'fetchPaginated']);
Route::get('/motives/{id}', [MotiveController::class, 'show']);
Route::post('/motives', [MotiveController::class, 'store']);
Route::put('/motives/{id}', [MotiveController::class, 'update']);
Route::delete('/motives/{id}', [MotiveController::class, 'destroy']);
Route::post('/motives/bulk-delete', [MotiveController::class, 'bulkDelete']);




Route::get('/types', [TypeController::class, 'index'])->name('types.index');
Route::get('/types/fetch', [TypeController::class, 'fetchPaginated']);
Route::get('/types/{id}', [TypeController::class, 'show']);
Route::post('/types', [TypeController::class, 'store']);
Route::put('/types/{id}', [TypeController::class, 'update']);
Route::delete('/types/{id}', [TypeController::class, 'destroy']);
Route::post('/types/bulk-delete', [TypeController::class, 'bulkDelete']);






Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/fetch', [ProjectController::class, 'fetchPaginated']);
Route::get('/projects/{id}', [ProjectController::class, 'show']);
Route::post('/projects', [ProjectController::class, 'store']);
Route::put('/projects/{id}', [ProjectController::class, 'update']);
Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
Route::post('/projects/bulk-delete', [ProjectController::class, 'bulkDelete']);




      Route::get('/chat', [ChatMessageController::class, 'index'])->name('chat.index');
    Route::get('/chat/messages', [ChatMessageController::class, 'fetch'])->name('chat.fetch');
    Route::post('/chat/messages', [ChatMessageController::class, 'store'])->name('chat.store');

    Route::get('/reports/{id}', [ReportController::class, 'show'])->name('reports.show');

});

use App\Http\Controllers\WebSocketTestController;

 Route::get('/ws/test', [WebSocketTestController::class, 'send']);



require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';

/*
agregar modulos products
agregar modulo usuarios

en el formulario de articulos que busque el producto y usuario tipo receptor

*/

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
        'subject' => 'Echo Final OK'
    ]));

    return response()->json(['success' => true, 'message' => 'Evento emitido']);
});
