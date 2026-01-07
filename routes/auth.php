<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;

/*
|--------------------------------------------------------------------------
| GUEST ROUTES
|--------------------------------------------------------------------------
*/
// Route::post('app/saml2/callback', [
//     AuthenticatedSessionController::class,
//     'samlCallback'
// ])->name('login.saml.callback');

Route::middleware('guest')->group(function () {

    // 🔑 LOGIN CLÁSICO
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // 🔐 LOGIN SAML (WSO2 / Banner)
    Route::get('login/saml', [AuthenticatedSessionController::class, 'redirectToSaml'])
        ->name('login.saml');



    // Route::post('app/saml2/callback', [AuthenticatedSessionController::class, 'samlCallback'])
    //     ->name('login.saml.callback');

    // 🔁 RECUPERAR PASSWORD
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // 📧 VERIFICACIÓN EMAIL
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // 🔐 CONFIRMAR PASSWORD
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    // 🔄 ACTUALIZAR PASSWORD
    // Route::put('password', [PasswordController::class, 'update'])
    //     ->name('password.update');

    // 🚪 LOGOUT LOCAL
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

/*
|--------------------------------------------------------------------------
| SAML SERVICE ROUTES (NO auth / NO guest)
|--------------------------------------------------------------------------
*/
Route::prefix('app')->group(function () {

    // ➜ Redirección al IdP
    Route::get('saml2/redirect', function () {
        return Socialite::driver('saml2')->redirect();
    })->name('saml.redirect');

    // 📄 Metadata SP
    Route::get('saml2/metadata', function () {
        return Socialite::driver('saml2')->getServiceProviderMetadata();
    })->name('saml.metadata');

    // ⬅ Callback SAML
    Route::post('saml2/callback', [AuthenticatedSessionController::class, 'samlCallback']);

    // ➜ Logout Request (SP → IdP)
    Route::get('saml2/logout', function () {
        return Socialite::driver('saml2')->logoutRequest();
    })->name('saml.logout');

    // ⬅ Logout Response (IdP → SP)
    Route::get('saml2/logout-response', function () {
        return Socialite::driver('saml2')->logoutResponse();
    })->name('saml.logout.response');
});
