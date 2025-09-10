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
use App\Http\Controllers\SupportDetailController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\CommentController;

use App\Http\Controllers\ImageAnalysisController;
use App\Exports\ImageAnalysesExport;
use Maatwebsite\Excel\Facades\Excel;
// routes/web.php
use App\Http\Controllers\PdfOcrController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\PaymentsTableController;
use App\Http\Controllers\TransactionController;


Route::get('/pagos', [PaymentsController::class, 'index']);

// 👇 Nueva ruta para procesar solo el reconocimiento de voucher (sin guardar todavía)

Route::post('/vouchers/recognize', [PaymentsController::class, 'recognize']);


// Ruta para registrar pagos (POST)
Route::post('/payment', [PaymentsController::class, 'store'])->middleware('throttle:20,1440')->name('payments.store');





Route::get('/', function () {
    return redirect("dashboard");
    //return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {


Route::post('/ocr/pdf/upload',   [PdfOcrController::class, 'uploadAndOcr'])->name('ocr.pdf.upload');
Route::post('/ocr/pdf/existing', [PdfOcrController::class, 'ocrExisting'])->name('ocr.pdf.existing');
Route::get('/ocr/pdf', function () {
    return Inertia::render('OcrPdf/OcrPdf'); // resources/js/Pages/OcrPdf.tsx
})->name('ocr.pdf.page');





 Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');



    Route::get('/users/fetch', [UserController::class, 'fetchPaginated'])->name('users.fetch')->middleware('permission:administrar');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:administrar');
    Route::get('/users', [UserController::class, 'index'])->name('users.index')->middleware(['auth', 'verified'])->middleware('permission:administrar');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('permission:administrar');
    Route::put('/users/{id}', [UserController::class, 'update'])->middleware('permission:administrar');
    Route::get('/users/{id}', [UserController::class, 'show'])->middleware('permission:administrar');
    Route::put('/users/{id}/sync-roles', [UserController::class, 'syncRoles'])->middleware('permission:administrar');



Route::get('/supports/search', [SupportController::class, 'fetch'])->name('supports.search')->middleware('permission:solicitudes.buscar');

Route::get('/supports/filtros', [SupportController::class, 'filter']);

    Route::get('/supports/fetch', [SupportController::class, 'fetchPaginated'])->name('supports.fetch');


    Route::get('/supports', [SupportController::class, 'index'])->name('supports.index')->middleware('permission:solicitudes.ver');
    Route::get('/supports/export-all', [SupportController::class, 'exportAll'])->name('supports.export')->middleware('permission:solicitudes.exportar');

    Route::post('/supports', [SupportController::class, 'store'])->middleware('permission:solicitudes.crear');
    Route::get('/supports/{id}', [SupportController::class, 'show']);
    Route::put('/supports/{id}', [SupportController::class, 'update'])->middleware('permission:solicitudes.actualizar');
    Route::delete('/supports/{id}', [SupportController::class, 'destroy'])->middleware('permission:solicitudes.eliminar');
    Route::post('/supports/bulk-delete', [SupportController::class, 'bulkDelete']);

Route::put('/support-details/{id}/area-motivo', [SupportDetailController::class, 'updateAreaMotivo']);
Route::delete('/support-details/{id}', [SupportDetailController::class, 'destroy']);



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
Route::post('/motives', [MotiveController::class, 'store'])->name('motives.store');
Route::get('/motives/{id}', [MotiveController::class, 'show']);
Route::put('/motives/{id}', [MotiveController::class, 'update'])->name('motives.update');
Route::delete('/motives/{id}', [MotiveController::class, 'destroy']);
Route::post('/motives/bulk-delete', [MotiveController::class, 'bulkDelete']);
Route::post('/motives/{id}/sync-areas', [MotiveController::class, 'syncAreas']);





Route::get('/types', [TypeController::class, 'index'])->name('types.index');
Route::get('/types/fetch', [TypeController::class, 'fetchPaginated']);
Route::get('/types/{id}', [TypeController::class, 'show']);
Route::post('/types', [TypeController::class, 'store']);
Route::put('/types/{id}', [TypeController::class, 'update']);
Route::delete('/types/{id}', [TypeController::class, 'destroy']);
Route::post('/types/bulk-delete', [TypeController::class, 'bulkDelete']);



Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
Route::get('/sales/fetch', [SaleController::class, 'fetchPaginated']);
Route::get('/sales/{id}', [SaleController::class, 'show']);
Route::post('/sales', [SaleController::class, 'store']);
Route::put('/sales/{id}', [SaleController::class, 'update']);
Route::delete('/sales/{id}', [SaleController::class, 'destroy']);
Route::post('/sales/bulk-delete', [SaleController::class, 'bulkDelete']);



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



Route::post('generate_ticket', [SupportDetailController::class, 'generateTicket']);
Route::get('/support-details/{supportDetail}/comments', [CommentController::class, 'index']);
Route::post('/support-details/{supportDetail}/comments', [CommentController::class, 'store']);


Route::get('/bot', [ImageAnalysisController::class, 'index']);
Route::get('/analyses/fetch', [ImageAnalysisController::class, 'fetchPaginated']);
Route::get('/analyses/filenames', [ImageAnalysisController::class, 'filenames']);
Route::post('/analyze-images', [ImageAnalysisController::class, 'analyzeImages']);
Route::delete('/analyses/{id}', [ImageAnalysisController::class, 'destroy']);

Route::get('/image-analyses', [ImageAnalysisController::class, 'index'])->name('image-analyses.index');
Route::get('/image-analyses/fetch', [ImageAnalysisController::class, 'fetchPaginated']);

Route::get('/export/image-analyses', function () {
    return Excel::download(new ImageAnalysesExport, 'image_analyses.xlsx');
});


// 1. Index general
Route::get('/payments/table', [PaymentsTableController::class, 'index'])
    ->name('payments.table');

// 2. Primero las rutas "fijas" de string, para que no choquen con {id}
Route::get('/payments/table/paginate', [PaymentsTableController::class, 'fetchPaginated'])
    ->name('payments.table.paginate');

// 3. Editar (usa {id}/edit, no choca con paginate porque ya está arriba)
Route::get('/payments/table/{id}/edit', [PaymentsTableController::class, 'edit'])
    ->name('payments.table.edit');

// 4. Eliminar individual (usa {id}, debe ir DESPUÉS de {id}/edit)
Route::delete('/payments/table/{id}', [PaymentsTableController::class, 'destroy'])
    ->name('payments.table.destroy');

// 5. Eliminación en lote (POST)
Route::post('/payments/table/bulk-delete', [PaymentsTableController::class, 'bulkDelete'])
    ->name('payments.table.bulkDelete');








// 📌 Listado principal (vista con tabla y Echo)
Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');

// 📌 Fetch inicial de líneas de transacciones (para llenar tabla al entrar)
Route::get('/transactions/fetch-lines', [TransactionController::class, 'fetchLines'])->name('transactions.fetchLines');

// 📌 Subida de imagen completa → divide en bloques → dispara job con GPT
Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');

// 📌 Editar (solo vista o JSON de una transacción específica)
Route::get('/transactions/{id}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');

// 📌 Eliminar una transacción
Route::delete('/transactions/{id}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

// 📌 Eliminación masiva
Route::delete('/transactions', [TransactionController::class, 'bulkDelete'])->name('transactions.bulkDelete');





});
Route::get('/report/supports', [ReportController::class, 'report']);

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
Route::get('/aybar_app', function () {
    $file = public_path('aybar_app.apk');
    return response()->download($file, 'aybar_app.apk', [
        'Content-Type' => 'application/vnd.android.package-archive',
    ]);
});

