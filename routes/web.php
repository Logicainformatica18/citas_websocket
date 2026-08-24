<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

/*
|--------------------------------------------------------------------------
| Público
|--------------------------------------------------------------------------
*/

Route::get('/unauthorized', function () {
    return 'Acceso no autorizado. Contacta con sistemas.';
});

Route::get('/', function () {
    return redirect('/dashboard');
});

/*
|--------------------------------------------------------------------------
| SAML2
|--------------------------------------------------------------------------
*/

Route::middleware('web')->group(function () {

    Route::get('/login/saml', [AuthenticatedSessionController::class, 'redirectToSaml']);

    Route::post('app/saml2/callback', [AuthenticatedSessionController::class, 'samlCallback']);

    Route::get('/logout', [AuthenticatedSessionController::class, 'logout'])
        ->name('logout');
});

/*
|--------------------------------------------------------------------------
| Autenticadas
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    // USERS
    Route::get('/users/fetch', [UserController::class, 'fetchPaginated'])->name('users.fetch')->middleware('permission:administrar');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:administrar');
    Route::get('/users', [UserController::class, 'index'])->name('users.index')->middleware('permission:administrar');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('permission:administrar');
    Route::put('/users/{id}', [UserController::class, 'update'])->middleware('permission:administrar');
    Route::get('/users/{id}', [UserController::class, 'show'])->middleware('permission:administrar');
    Route::put('/users/{id}/sync-roles', [UserController::class, 'syncRoles'])->middleware('permission:administrar');

    // ROLES
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('permission:administrar');
    Route::get('/roles/fetch', [RoleController::class, 'fetchPaginated'])->name('roles.fetch')->middleware('permission:administrar');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store')->middleware('permission:administrar');
    Route::get('/roles/{id}', [RoleController::class, 'show'])->name('roles.show')->middleware('permission:administrar');
    Route::put('/roles/{id}', [RoleController::class, 'update'])->name('roles.update')->middleware('permission:administrar');
    Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('permission:administrar');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
