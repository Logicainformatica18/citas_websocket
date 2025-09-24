<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ScrapingController;
use App\Http\Controllers\ScrapingFieldController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\JobOfferController;




Route::get('/', function () {
    return redirect("dashboard");
});

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:administrar');

    // USERS
    Route::get('/users/fetch', [UserController::class, 'fetchPaginated'])->name('users.fetch')->middleware('permission:administrar');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:administrar');
    Route::get('/users', [UserController::class, 'index'])->name('users.index')->middleware('permission:administrar');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('permission:administrar');
    Route::put('/users/{id}', [UserController::class, 'update'])->middleware('permission:administrar');
    Route::get('/users/{id}', [UserController::class, 'show'])->middleware('permission:administrar');
    Route::put('/users/{id}/sync-roles', [UserController::class, 'syncRoles'])->middleware('permission:administrar');

    // ROLES
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/fetch', [RoleController::class, 'fetchPaginated'])->name('roles.fetch');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{id}', [RoleController::class, 'show'])->name('roles.show');
    Route::put('/roles/{id}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->name('roles.destroy');

    // SCRAPINGS
    Route::get('/scrapings/fetch', [ScrapingController::class, 'fetchPaginated'])->name('scrapings.fetch')->middleware('permission:administrar');
    Route::post('/scrapings', [ScrapingController::class, 'store'])->middleware('permission:administrar');
    Route::get('/scrapings', [ScrapingController::class, 'index'])->name('scrapings.index')->middleware('permission:administrar');
    Route::delete('/scrapings/{id}', [ScrapingController::class, 'destroy'])->middleware('permission:administrar');
    Route::put('/scrapings/{id}', [ScrapingController::class, 'update'])->middleware('permission:administrar');
    Route::get('/scrapings/{id}', [ScrapingController::class, 'show'])->middleware('permission:administrar');

    // SCRAPING FIELDS (ahora con jerarquía parent_id)
    Route::get('/scrapings/{scraping}/fields/fetch', [ScrapingFieldController::class, 'fetchPaginated'])->name('scraping_fields.fetch')->middleware('permission:administrar');
    Route::post('/scrapings/{scraping}/fields', [ScrapingFieldController::class, 'store'])->middleware('permission:administrar');
    Route::get('/scrapings/{scraping}/fields', [ScrapingFieldController::class, 'index'])->name('scraping_fields.index')->middleware('permission:administrar');
    Route::delete('/scrapings/{scraping}/fields/{id}', [ScrapingFieldController::class, 'destroy'])->middleware('permission:administrar');
    Route::put('/scrapings/{scraping}/fields/{id}', [ScrapingFieldController::class, 'update'])->middleware('permission:administrar');
    Route::get('/scrapings/{scraping}/fields/{id}', [ScrapingFieldController::class, 'show'])->middleware('permission:administrar');

    Route::post('scrapings/{scraping}/backups/bulk', [BackupController::class, 'storeMany'])
    ->name('backups.storeMany');
    // RUN SCRAPING
    Route::post('/scrapings/{id}/run', [ScrapingController::class, 'run'])->name('scrapings.run')->middleware('permission:administrar');

Route::prefix('scrapings/{scraping}')->group(function () {
    Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
    Route::patch('backups/{backup}/toggle', [BackupController::class, 'toggleReviewed'])->name('backups.toggle');
    Route::delete('backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');
    Route::get('backups/export', [BackupController::class, 'export'])->name('backups.export');
});

Route::post('/ai/chat', [AIController::class, 'chat'])->middleware('permission:administrar');


Route::get('/job-offers/fetch', [JobOfferController::class, 'fetchPaginated'])
    ->name('job_offers.fetch')
    ->middleware('permission:administrar');

Route::post('/job-offers', [JobOfferController::class, 'store'])
    ->name('job_offers.store')
    ->middleware('permission:administrar');

Route::get('/job-offers', [JobOfferController::class, 'index'])
    ->name('job_offers.index')
    ->middleware('permission:administrar');

Route::delete('/job-offers/{id}', [JobOfferController::class, 'destroy'])
    ->name('job_offers.destroy')
    ->middleware('permission:administrar');

Route::put('/job-offers/{id}', [JobOfferController::class, 'update'])
    ->name('job_offers.update')
    ->middleware('permission:administrar');

Route::get('/job-offers/{id}', [JobOfferController::class, 'show'])
    ->name('job_offers.show')
    ->middleware('permission:administrar');

Route::delete('/job-offers', [JobOfferController::class, 'bulkDelete'])
    ->name('job_offers.bulkDelete')
    ->middleware('permission:administrar');


    Route::post('/job-offers/import', [JobOfferController::class, 'import'])
    ->name('job-offers.import')
    ->middleware('permission:administrar');

    
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
