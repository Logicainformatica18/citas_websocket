<?php

use App\Http\Controllers\UserController;

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\RoleController;


use App\Http\Controllers\ScrapingController;
use App\Http\Controllers\ScrapingFieldController;

Route::get('/', function () {
    return redirect()->route('scrapings.index');
});



Route::middleware(['auth', 'verified'])->group(function () {




    Route::get('/users/fetch', [UserController::class, 'fetchPaginated'])->name('users.fetch')->middleware('permission:administrar');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:administrar');
    Route::get('/users', [UserController::class, 'index'])->name('users.index')->middleware(['auth', 'verified'])->middleware('permission:administrar');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('permission:administrar');
    Route::put('/users/{id}', [UserController::class, 'update'])->middleware('permission:administrar');
    Route::get('/users/{id}', [UserController::class, 'show'])->middleware('permission:administrar');
    Route::put('/users/{id}/sync-roles', [UserController::class, 'syncRoles'])->middleware('permission:administrar');



    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/fetch', [RoleController::class, 'fetchPaginated'])->name('roles.fetch');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{id}', [RoleController::class, 'show'])->name('roles.show');
    Route::put('/roles/{id}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->name('roles.destroy');



// SCRAPINGS
Route::get('/scrapings/fetch', [ScrapingController::class, 'fetchPaginated'])
    ->name('scrapings.fetch')
    ->middleware('permission:administrar');

Route::post('/scrapings', [ScrapingController::class, 'store'])
    ->middleware('permission:administrar');

Route::get('/scrapings', [ScrapingController::class, 'index'])
    ->name('scrapings.index')
    ->middleware(['auth', 'verified'])
    ->middleware('permission:administrar');

Route::delete('/scrapings/{id}', [ScrapingController::class, 'destroy'])
    ->middleware('permission:administrar');

Route::put('/scrapings/{id}', [ScrapingController::class, 'update'])
    ->middleware('permission:administrar');

Route::get('/scrapings/{id}', [ScrapingController::class, 'show'])
    ->middleware('permission:administrar');


// CAMPOS (HIJOS)
Route::get('/scrapings/{scraping}/fields/fetch', [ScrapingFieldController::class, 'fetchPaginated'])
    ->name('scraping_fields.fetch')
    ->middleware('permission:administrar');

Route::post('/scrapings/{scraping}/fields', [ScrapingFieldController::class, 'store'])
    ->middleware('permission:administrar');

Route::get('/scrapings/{scraping}/fields', [ScrapingFieldController::class, 'index'])
    ->name('scraping_fields.index')
    ->middleware(['auth', 'verified'])
    ->middleware('permission:administrar');

Route::delete('/scrapings/{scraping}/fields/{id}', [ScrapingFieldController::class, 'destroy'])
    ->middleware('permission:administrar');

Route::put('/scrapings/{scraping}/fields/{id}', [ScrapingFieldController::class, 'update'])
    ->middleware('permission:administrar');

Route::get('/scrapings/{scraping}/fields/{id}', [ScrapingFieldController::class, 'show'])
    ->middleware('permission:administrar');






Route::post('/scrapings/{id}/run', [ScrapingController::class, 'run'])
    ->name('scrapings.run')
    ->middleware('permission:administrar');







});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';

/*
agregar modulos products
agregar modulo usuarios

en el formulario de articulos que busque el producto y usuario tipo receptor

*/

